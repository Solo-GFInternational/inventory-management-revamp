<?php

namespace Tests\Feature;

use App\Http\Controllers\InventoryController;
use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use Database\Seeders\TypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * stocks.qty is what is on the shelf; it is reduced for exactly the inventory
 * rows that are active, approved and in 'new' condition. The create form adds
 * one single-unit record per person receiving the item.
 */
class InventoryStockTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    /**
     * The create form as posted: the shared fields (names turned into ids)
     * and $people rows — a count of generated people, or the rows themselves.
     */
    private function createForm(array $overrides = [], int|array $people = 2): array
    {
        if (is_int($people)) {
            $people = array_map(fn ($n) => [
                'taken_by' => "Staff {$n}",
                'department' => 'Finance',
                'serial_number' => null,
            ], range(1, $people));
        }

        return $this->withIds($overrides + [
            'date' => now()->toDateString(),
            'item' => 'Monitor',
            'condition' => 'new',
            'site' => 'Dry Goods',
        ]) + ['people' => array_map(fn (array $person) => $this->withIds($person), $people)];
    }

    /** The edit form as posted (names are turned into ids). */
    private function editForm(array $overrides = []): array
    {
        return $this->withIds($overrides + [
            'date' => now()->toDateString(),
            'item' => 'Monitor',
            'serial_number' => null,
            'condition' => 'new',
            'taken_by' => 'Jane Staff',
            'department' => 'Finance',
            'site' => 'Dry Goods',
        ]);
    }

    private function shelf(string $item = 'Monitor'): int
    {
        return (int) Stock::where('item', $item)->value('qty');
    }

    public function test_admin_create_adds_one_approved_record_per_person_and_deducts_stock()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);

        $this->actingAs($admin)->post('/inventory', $this->createForm([], [
            ['taken_by' => 'Ana', 'department' => 'Finance', 'serial_number' => 'SN-1'],
            ['taken_by' => 'Ben', 'department' => 'Sales', 'serial_number' => null],
            ['taken_by' => 'Cara', 'department' => 'Finance', 'serial_number' => 'SN-3'],
        ]))
            ->assertRedirect('/inventory')
            ->assertSessionHas('status', '3 inventory records added and stock updated.');

        $rows = Inventory::orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(['Ana', 'Ben', 'Cara'], $rows->pluck('taken_by')->all());
        $this->assertSame(['SN-1', null, 'SN-3'], $rows->pluck('serial_number')->all());
        $this->assertSame('Sales', $rows[1]->department->type_name);

        foreach ($rows as $row) {
            $this->assertEquals(1, $row->qty);
            $this->assertSame('approved', $row->status);
            $this->assertNull($row->approval_type);
            $this->assertEquals($admin->id, $row->given_by);
            $this->assertSame('Dry Goods', $row->site->type_name);
            $this->assertSame('Monitor', $row->stock->item);
        }

        $this->assertSame(2, $this->shelf());
    }

    public function test_one_person_flash_is_singular()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);

        $this->actingAs($admin)->post('/inventory', $this->createForm([], 1))
            ->assertSessionHas('status', '1 inventory record added and stock updated.');
    }

    public function test_used_items_do_not_touch_stock()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 1]);

        $this->actingAs($admin)->post('/inventory', $this->createForm(['condition' => 'used'], 4))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, Inventory::count());
        $this->assertSame(1, $this->shelf());
    }

    public function test_user_create_waits_for_approval_before_deducting()
    {
        $user = $this->makeUser('user');
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);

        $this->actingAs($user)->post('/inventory', $this->createForm())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '2 inventory records submitted for approval. Stock is deducted once an administrator approves them.');

        $rows = Inventory::orderBy('id')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('pending', $row->status);
            $this->assertSame('new', $row->approval_type);
            $this->assertEquals($user->id, $row->given_by);
        }
        $this->assertSame(5, $this->shelf());

        $this->actingAs($admin)->post("/inventory/{$rows[0]->id}/approve")->assertSessionHas('status');

        $this->assertSame('approved', $rows[0]->fresh()->status);
        $this->assertSame(4, $this->shelf());
    }

    public function test_approval_rechecks_availability()
    {
        $user = $this->makeUser('user');
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 1]);

        $this->actingAs($user)->post('/inventory', $this->createForm([], 1))->assertSessionHasNoErrors();
        $inventory = Inventory::first();

        // Meanwhile an admin issues the last one directly.
        $this->actingAs($admin)->post('/inventory', $this->createForm([], 1))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->shelf());

        $this->actingAs($admin)->post("/inventory/{$inventory->id}/approve")->assertSessionHas('error');

        $this->assertSame('pending', $inventory->fresh()->status);
        $this->assertSame(0, $this->shelf());
    }

    public function test_more_people_than_in_stock_is_rejected_and_nothing_is_saved()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 3]);

        $this->actingAs($admin)->post('/inventory', $this->createForm(['_modal' => 'create-inventory'], 4))
            ->assertSessionHasErrors(['people' => 'Only 3 Monitor available in stock — you asked for 4.']);

        $this->assertSame(0, Inventory::count());
        $this->assertSame(3, $this->shelf());
    }

    public function test_item_with_no_stock_is_rejected()
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post('/inventory', $this->createForm(['item' => 'Projector'], 1))
            ->assertSessionHasErrors(['people' => 'There is no Projector in stock.']);

        $this->assertSame(0, Inventory::count());
    }

    public function test_a_shortfall_part_way_through_rolls_back_the_whole_batch()
    {
        $admin = $this->makeUser('admin');
        $stock = Stock::create(['item' => 'Monitor', 'qty' => 3]);

        // Stock that passes the up-front check but runs out mid-batch (as if
        // someone else took some meanwhile): the ledger refuses the third row.
        Inventory::created(function (Inventory $inventory) use ($stock) {
            if (Inventory::count() === 2) {
                Stock::whereKey($stock->id)->update(['qty' => 0]);
            }
        });

        $this->actingAs($admin)->post('/inventory', $this->createForm([], 3))
            ->assertSessionHasErrors(['people' => 'There is no Monitor in stock.']);

        $this->assertSame(0, Inventory::count());
        $this->assertSame(3, $this->shelf());
    }

    public function test_each_person_row_is_validated()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        $finance = $this->typeFor(Type::DEPARTMENT, 'Finance');

        $form = $this->createForm(['_modal' => 'create-inventory'], [
            ['taken_by' => 'Ana', 'department_id' => $finance->id],
            ['taken_by' => '', 'department_id' => ''],
            ['taken_by' => 'Cara', 'department_id' => 999999, 'serial_number' => str_repeat('x', 256)],
        ]);

        $this->actingAs($admin)->post('/inventory', $form)
            ->assertSessionHasErrors([
                'people.1.taken_by' => 'The taken by (person 2) field is required.',
                'people.1.department_id',
                'people.2.department_id' => 'The department chosen for person 3 is no longer available.',
                'people.2.serial_number',
            ])
            ->assertSessionDoesntHaveErrors(['people.0.taken_by', 'people.0.department_id']);
        $this->assertSame(0, Inventory::count());

        // The modal reopens with every submitted row and its errors.
        $this->actingAs($admin)->from('/inventory')->followingRedirects()->post('/inventory', $form)
            ->assertOk()
            ->assertSee('name="people[2][taken_by]"', false)
            ->assertSee('value="Cara"', false)
            ->assertSee('The taken by (person 2) field is required.');
    }

    public function test_at_least_one_and_at_most_the_cap_of_people()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 100]);

        $this->actingAs($admin)->post('/inventory', $this->createForm([], []))->assertSessionHasErrors('people');

        $this->actingAs($admin)->post('/inventory', $this->createForm([], InventoryController::MAX_PEOPLE + 1))
            ->assertSessionHasErrors(['people' => 'No more than '.InventoryController::MAX_PEOPLE.' people can be added at once.']);

        $this->assertSame(0, Inventory::count());
        $this->assertSame(100, $this->shelf());
    }

    public function test_deactivating_an_approved_new_row_restores_stock()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        $this->actingAs($admin)->post('/inventory', $this->createForm([], 2));
        $inventory = Inventory::first();
        $this->assertSame(3, $this->shelf());

        $this->actingAs($admin)->delete("/inventory/{$inventory->id}")->assertSessionHas('status');

        $this->assertFalse($inventory->fresh()->active_yn);
        $this->assertSame(4, $this->shelf());

        // A second deactivation must not put the stock back twice.
        $this->actingAs($admin)->delete("/inventory/{$inventory->id}")->assertSessionHas('error');
        $this->assertSame(4, $this->shelf());
    }

    public function test_deactivating_a_pending_row_leaves_stock_alone()
    {
        $user = $this->makeUser('user');
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        $this->actingAs($user)->post('/inventory', $this->createForm([], 1));

        $this->actingAs($admin)->delete('/inventory/'.Inventory::first()->id);

        $this->assertSame(5, $this->shelf());
    }

    public function test_admin_edit_moves_stock_when_the_item_or_condition_changes()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        Stock::create(['item' => 'Webcam', 'qty' => 1]);
        Stock::create(['item' => 'Projector', 'qty' => 0]);
        $this->actingAs($admin)->post('/inventory', $this->createForm([], 1));
        $inventory = Inventory::first();
        $this->assertSame(4, $this->shelf());

        // A different item: the Monitor goes back, a Webcam comes off.
        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->editForm(['item' => 'Webcam']))->assertSessionHasNoErrors();
        $this->assertSame(5, $this->shelf());
        $this->assertSame(0, $this->shelf('Webcam'));

        // It may keep what it already holds, but can't switch to an item with none.
        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->editForm(['item' => 'Webcam', 'taken_by' => 'Renamed']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->editForm(['item' => 'Projector']))
            ->assertSessionHasErrors(['stock_id' => 'There is no Projector in stock.']);
        $this->assertSame(0, $this->shelf('Webcam'));

        // Switched to used: it goes back.
        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->editForm(['item' => 'Webcam', 'condition' => 'used']))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->shelf('Webcam'));
        $this->assertSame('approved', $inventory->fresh()->status);
    }

    public function test_edit_does_not_change_quantity()
    {
        $admin = $this->makeUser('admin');
        // A legacy row issued several at once.
        $inventory = $this->makeInventory($admin, ['item' => 'Monitor', 'qty' => 3, 'condition' => 'used', 'taken_by' => 'Jane Staff']);

        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->editForm(['condition' => 'used', 'qty' => 1]))
            ->assertSessionHas('status', 'No changes to save.');
        $this->assertEquals(3, $inventory->fresh()->qty);

        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->editForm(['condition' => 'used', 'qty' => 9, 'taken_by' => 'Someone']))
            ->assertSessionHas('status', 'Inventory updated.');
        $this->assertEquals(3, $inventory->fresh()->qty);
        $this->assertSame('Someone', $inventory->fresh()->taken_by);
    }

    public function test_non_admin_edit_returns_stock_until_reapproved()
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser('user');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        $this->actingAs($admin)->post('/inventory', $this->createForm([], 1));
        $inventory = Inventory::first();
        $this->assertSame(4, $this->shelf());

        $this->actingAs($user)->put("/inventory/{$inventory->id}", $this->editForm(['taken_by' => 'New Name']))->assertSessionHasNoErrors();

        $inventory->refresh();
        $this->assertSame('pending', $inventory->status);
        $this->assertSame('edit', $inventory->approval_type);
        $this->assertEquals(['old' => 'Staff 1', 'new' => 'New Name'], $inventory->change_log['taken_by']);
        $this->assertEquals($user->id, $inventory->edited_by);
        $this->assertSame(5, $this->shelf());

        $this->actingAs($admin)->patch("/inventory/{$inventory->id}/approve")->assertSessionHas('status');

        $inventory->refresh();
        $this->assertSame('approved', $inventory->status);
        $this->assertNull($inventory->change_log);
        $this->assertNull($inventory->approval_type);
        $this->assertSame(4, $this->shelf());
    }

    public function test_index_lists_stock_items_and_managed_options()
    {
        $this->seed(TypeSeeder::class);
        $user = $this->makeUser('user');
        $dock = Stock::create(['item' => 'Docking Station', 'qty' => 7]);
        $site = Type::group(Type::SITE)->where('type_name', 'Korociriciri Packhouse')->first();

        $this->actingAs($user)->get('/inventory')
            ->assertOk()
            ->assertSee('Docking Station (7 in stock)')
            ->assertSee('value="'.$dock->id.'"', false)
            ->assertSee('Korociriciri Packhouse')
            ->assertSee('value="'.$site->id.'"', false)
            ->assertSee('Information Technology')
            ->assertSee('name="stock_id"', false)
            ->assertSee('name="people[0][department_id]"', false)
            ->assertSee('name="people[0][taken_by]"', false)
            ->assertSee('name="site_id"', false)
            ->assertSee('data-people-add', false)
            ->assertDontSee('name="qty"', false)
            ->assertSee('gf-input select-arrow', false);
    }

    public function test_deactivated_or_unknown_choices_are_rejected_on_create()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        $retired = Stock::create(['item' => 'Old Monitor', 'qty' => 5]);
        $retired->forceFill(['active_yn' => false])->save();
        $closed = $this->typeFor(Type::SITE, 'Closed Site');
        $closed->forceFill(['active_yn' => false])->save();
        $department = $this->typeFor(Type::DEPARTMENT, 'Finance');

        $this->actingAs($admin)->post('/inventory', $this->createForm(['stock_id' => $retired->id]))
            ->assertSessionHasErrors(['stock_id' => 'The chosen item is no longer in the stock list.']);
        $this->actingAs($admin)->post('/inventory', $this->createForm(['site_id' => $closed->id]))
            ->assertSessionHasErrors('site_id');
        // A department id is not a site.
        $this->actingAs($admin)->post('/inventory', $this->createForm(['site_id' => $department->id]))
            ->assertSessionHasErrors('site_id');
        $this->actingAs($admin)->post('/inventory', $this->createForm([], [['taken_by' => 'Ana', 'department_id' => 999999]]))
            ->assertSessionHasErrors('people.0.department_id');

        $this->assertSame(0, Inventory::count());
        $this->assertSame(5, $this->shelf());
    }

    public function test_edit_may_keep_a_deactivated_department_site_or_item()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin, [
            'item' => 'Old Monitor', 'department' => 'Gone Dept', 'site' => 'Closed Site', 'condition' => 'used',
        ]);
        $inventory->stock->forceFill(['active_yn' => false])->save();
        $inventory->department->forceFill(['active_yn' => false])->save();
        $inventory->site->forceFill(['active_yn' => false])->save();

        // The edit modal still lists the row's current values.
        $this->actingAs($admin)->get('/inventory')->assertOk()
            ->assertSee('Old Monitor (not in stock list)')
            ->assertSee('Gone Dept')
            ->assertSee('Closed Site');

        $this->actingAs($admin)->put("/inventory/{$inventory->id}", [
            'date' => now()->toDateString(),
            'stock_id' => $inventory->stock_id,
            'condition' => 'used',
            'taken_by' => 'Someone Else',
            'department_id' => $inventory->department_id,
            'site_id' => $inventory->site_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Someone Else', $inventory->fresh()->taken_by);

        // But a new row can't pick them.
        $this->actingAs($admin)->post('/inventory', $this->createForm(['condition' => 'used'], [
            ['taken_by' => 'Ana', 'department_id' => $inventory->department_id],
        ]))->assertSessionHasErrors('people.0.department_id');
    }
}
