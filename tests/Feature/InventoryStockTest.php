<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * stocks.qty is what is on the shelf; it is reduced for exactly the inventory
 * rows that are active, approved and in 'new' condition.
 */
class InventoryStockTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'date' => now()->toDateString(),
            'qty' => 2,
            'item' => 'Monitor',
            'serial_number' => null,
            'condition' => 'new',
            'taken_by' => 'Jane Staff',
            'department' => 'Finance',
            'site' => 'Dry Goods',
        ];
    }

    private function shelf(string $item = 'Monitor'): int
    {
        return (int) Stock::where('item', $item)->value('qty');
    }

    public function test_admin_create_deducts_stock_immediately()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);

        $this->actingAs($admin)->post('/inventory', $this->form())
            ->assertRedirect('/inventory')
            ->assertSessionHas('status');

        $this->assertSame('approved', Inventory::first()->status);
        $this->assertSame(3, $this->shelf());
    }

    public function test_used_items_do_not_touch_stock()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 1]);

        $this->actingAs($admin)->post('/inventory', $this->form(['condition' => 'used', 'qty' => 4]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->shelf());
    }

    public function test_user_create_waits_for_approval_before_deducting()
    {
        $user = $this->makeUser('user');
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);

        $this->actingAs($user)->post('/inventory', $this->form())->assertSessionHasNoErrors();

        $inventory = Inventory::first();
        $this->assertSame('pending', $inventory->status);
        $this->assertSame('new', $inventory->approval_type);
        $this->assertSame(5, $this->shelf());

        $this->actingAs($admin)->post("/inventory/{$inventory->id}/approve")->assertSessionHas('status');

        $this->assertSame('approved', $inventory->fresh()->status);
        $this->assertSame(3, $this->shelf());
    }

    public function test_approval_rechecks_availability()
    {
        $user = $this->makeUser('user');
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 2]);

        $this->actingAs($user)->post('/inventory', $this->form(['qty' => 2]))->assertSessionHasNoErrors();
        $inventory = Inventory::first();

        // Meanwhile an admin issues one directly.
        $this->actingAs($admin)->post('/inventory', $this->form(['qty' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->shelf());

        $this->actingAs($admin)->post("/inventory/{$inventory->id}/approve")->assertSessionHas('error');

        $this->assertSame('pending', $inventory->fresh()->status);
        $this->assertSame(1, $this->shelf());
    }

    public function test_requesting_more_than_is_in_stock_is_rejected()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 3]);

        $this->actingAs($admin)->post('/inventory', $this->form(['qty' => 4, '_modal' => 'create-inventory']))
            ->assertSessionHasErrors(['qty' => 'Only 3 Monitor available in stock — you asked for 4.']);

        $this->assertSame(0, Inventory::count());
        $this->assertSame(3, $this->shelf());
    }

    public function test_item_with_no_stock_is_rejected_and_zero_qty_is_invalid()
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post('/inventory', $this->form(['item' => 'Projector']))
            ->assertSessionHasErrors(['qty' => 'There is no Projector in stock.']);

        Stock::create(['item' => 'Monitor', 'qty' => 3]);
        $this->actingAs($admin)->post('/inventory', $this->form(['qty' => 0]))->assertSessionHasErrors('qty');

        $this->assertSame(0, Inventory::count());
    }

    public function test_deactivating_an_approved_new_row_restores_stock()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        $this->actingAs($admin)->post('/inventory', $this->form(['qty' => 2]));
        $inventory = Inventory::first();
        $this->assertSame(3, $this->shelf());

        $this->actingAs($admin)->delete("/inventory/{$inventory->id}")->assertSessionHas('status');

        $this->assertFalse($inventory->fresh()->active_yn);
        $this->assertSame(5, $this->shelf());

        // A second deactivation must not put the stock back twice.
        $this->actingAs($admin)->delete("/inventory/{$inventory->id}")->assertSessionHas('error');
        $this->assertSame(5, $this->shelf());
    }

    public function test_deactivating_a_pending_row_leaves_stock_alone()
    {
        $user = $this->makeUser('user');
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        $this->actingAs($user)->post('/inventory', $this->form());

        $this->actingAs($admin)->delete('/inventory/'.Inventory::first()->id);

        $this->assertSame(5, $this->shelf());
    }

    public function test_admin_edit_adjusts_stock_by_the_difference()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        Stock::create(['item' => 'Webcam', 'qty' => 5]);
        $this->actingAs($admin)->post('/inventory', $this->form(['qty' => 2]));
        $inventory = Inventory::first();

        // More of the same item: 2 more come off the shelf.
        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->form(['qty' => 4]))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->shelf());

        // It may use what it already holds: 4 held + 1 on the shelf = 5.
        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->form(['qty' => 5]))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->shelf());

        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->form(['qty' => 6]))
            ->assertSessionHasErrors(['qty' => 'Only 5 Monitor available in stock — you asked for 6.']);
        $this->assertSame(0, $this->shelf());

        // A different item: Monitors go back, Webcams come off.
        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->form(['qty' => 3, 'item' => 'Webcam']))->assertSessionHasNoErrors();
        $this->assertSame(5, $this->shelf());
        $this->assertSame(2, $this->shelf('Webcam'));

        // Switched to used: everything goes back.
        $this->actingAs($admin)->put("/inventory/{$inventory->id}", $this->form(['qty' => 3, 'item' => 'Webcam', 'condition' => 'used']))->assertSessionHasNoErrors();
        $this->assertSame(5, $this->shelf('Webcam'));
        $this->assertSame('approved', $inventory->fresh()->status);
    }

    public function test_non_admin_edit_returns_stock_until_reapproved()
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser('user');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);
        $this->actingAs($admin)->post('/inventory', $this->form(['qty' => 2]));
        $inventory = Inventory::first();
        $this->assertSame(3, $this->shelf());

        $this->actingAs($user)->put("/inventory/{$inventory->id}", $this->form(['qty' => 3]))->assertSessionHasNoErrors();

        $inventory->refresh();
        $this->assertSame('pending', $inventory->status);
        $this->assertSame('edit', $inventory->approval_type);
        $this->assertEquals(['old' => '2', 'new' => '3'], $inventory->change_log['qty']);
        $this->assertEquals($user->id, $inventory->edited_by);
        $this->assertSame(5, $this->shelf());

        $this->actingAs($admin)->patch("/inventory/{$inventory->id}/approve")->assertSessionHas('status');

        $inventory->refresh();
        $this->assertSame('approved', $inventory->status);
        $this->assertNull($inventory->change_log);
        $this->assertNull($inventory->approval_type);
        $this->assertSame(2, $this->shelf());
    }

    public function test_index_lists_stock_items_and_configured_options()
    {
        $user = $this->makeUser('user');
        Stock::create(['item' => 'Docking Station', 'qty' => 7]);

        $this->actingAs($user)->get('/inventory')
            ->assertOk()
            ->assertSee('Docking Station (7 in stock)')
            ->assertSee('Korociriciri Packhouse')
            ->assertSee('Information Technology')
            ->assertSee('gf-input select-arrow', false);
    }
}
