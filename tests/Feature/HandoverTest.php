<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryUpdate;
use App\Models\Stock;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * Handing an item to someone else: Return it to stock, then Assign it to the
 * new person on the same record (same barcode). A returned item holds
 * nothing from stock.
 */
class HandoverTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', ['name' => 'Demo Admin']);
    }

    /** An approved new Laptop issued to Sam, with its unit already taken from a shelf of $onShelf. */
    private function issued(int $onShelf = 0, array $overrides = []): Inventory
    {
        $this->stockFor('Laptop')->forceFill(['qty' => $onShelf])->save();

        return $this->makeInventory($this->admin, $overrides + ['item' => 'Laptop', 'taken_by' => 'Sam Example']);
    }

    private function onShelf(string $item = 'Laptop'): int
    {
        return (int) Stock::where('item', $item)->value('qty');
    }

    private function assignment(array $overrides = []): array
    {
        return $this->withIds($overrides + [
            'date' => '2026-01-15',
            'taken_by' => 'Jordan Example',
            'department' => 'Engineering',
            'site' => 'Feed Mill',
        ]);
    }

    private function returned(int $onShelf = 1, array $overrides = []): Inventory
    {
        $inventory = $this->issued($onShelf, $overrides);
        $inventory->forceFill(['returned_at' => now(), 'returned_by' => $this->admin->id])->save();

        return $inventory;
    }

    public function test_return_puts_a_new_item_back_in_stock_and_writes_the_timeline()
    {
        $inventory = $this->issued(0);
        $user = $this->makeUser('user', ['name' => 'Una User']);

        $this->actingAs($user)
            ->patch(route('inventory.return', $inventory), ['reason' => 'Left the company'])
            ->assertRedirect(route('inventory.show', $inventory))
            ->assertSessionHas('status');

        $inventory->refresh();
        $this->assertTrue($inventory->isReturned());
        $this->assertEquals($user->id, $inventory->returned_by);
        $this->assertSame('approved', $inventory->status);
        $this->assertSame(1, $this->onShelf());

        $entry = InventoryUpdate::where('inventory_id', $inventory->id)->sole();
        $this->assertSame(InventoryUpdate::RETURNED, $entry->kind);
        $this->assertSame('Returned to stock by Una User. Previously held by Sam Example (Finance, Dry Goods). Reason: Left the company', $entry->body);
        $this->assertEquals($user->id, $entry->created_by);
    }

    public function test_returning_a_used_item_leaves_stock_alone()
    {
        $inventory = $this->issued(3, ['condition' => 'used']);

        $this->actingAs($this->admin)->patch(route('inventory.return', $inventory))->assertSessionHas('status');

        $this->assertTrue($inventory->fresh()->isReturned());
        $this->assertSame(3, $this->onShelf());
    }

    public function test_only_active_approved_unreturned_records_can_be_returned()
    {
        $pending = $this->issued(0, ['status' => 'pending', 'approval_type' => 'new']);
        $inactive = $this->makeInventory($this->admin, ['item' => 'Laptop', 'active_yn' => false]);
        $returned = $this->makeInventory($this->admin, ['item' => 'Laptop', 'returned_at' => now()]);

        foreach ([$pending, $inactive, $returned] as $inventory) {
            $before = $inventory->fresh()->returned_at;

            $this->actingAs($this->admin)->patch(route('inventory.return', $inventory))->assertSessionHas('error');

            $this->assertEquals($before, $inventory->fresh()->returned_at);
        }

        $this->assertSame(0, $this->onShelf());
        $this->assertSame(0, InventoryUpdate::count());
    }

    public function test_admin_assign_keeps_the_record_and_takes_the_unit_again()
    {
        $inventory = $this->returned(1);
        $barcode = $inventory->barcode;

        $this->actingAs($this->admin)
            ->patch(route('inventory.assign', $inventory), $this->assignment(['notes' => 'With charger']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory.show', $inventory));

        $inventory->refresh();
        $this->assertSame(1, Inventory::count());
        $this->assertSame($barcode, $inventory->barcode);
        $this->assertFalse($inventory->isReturned());
        $this->assertNull($inventory->returned_by);
        $this->assertSame('approved', $inventory->status);
        $this->assertSame('Jordan Example', $inventory->taken_by);
        $this->assertSame('Engineering', $inventory->department->type_name);
        $this->assertSame('Feed Mill', $inventory->site->type_name);
        $this->assertSame('2026-01-15', substr((string) $inventory->date, 0, 10));
        $this->assertSame('new', $inventory->condition);
        $this->assertSame(0, $this->onShelf());

        $entry = InventoryUpdate::where('inventory_id', $inventory->id)->sole();
        $this->assertSame(InventoryUpdate::ASSIGNED, $entry->kind);
        $this->assertSame('Assigned to Jordan Example (Engineering, Feed Mill) on 15 Jan 2026. Notes: With charger', $entry->body);
    }

    public function test_admin_assign_with_nothing_on_the_shelf_saves_nothing()
    {
        $inventory = $this->returned(0);

        $this->actingAs($this->admin)
            ->patch(route('inventory.assign', $inventory), $this->assignment() + ['_modal' => 'assign-inventory'])
            ->assertSessionHasErrors('stock_id');

        $inventory->refresh();
        $this->assertTrue($inventory->isReturned());
        $this->assertSame('Sam Example', $inventory->taken_by);
        $this->assertSame(0, $this->onShelf());
        $this->assertSame(0, InventoryUpdate::count());
    }

    public function test_user_assign_goes_to_approval_and_approval_takes_the_stock()
    {
        $inventory = $this->returned(1);
        $user = $this->makeUser('user');

        $this->actingAs($user)
            ->patch(route('inventory.assign', $inventory), $this->assignment())
            ->assertSessionHasNoErrors();

        $inventory->refresh();
        $this->assertFalse($inventory->isReturned());
        $this->assertSame('pending', $inventory->status);
        $this->assertSame('edit', $inventory->approval_type);
        $this->assertEquals($user->id, $inventory->edited_by);
        $this->assertSame([
            'date' => ['old' => now()->toDateString(), 'new' => '2026-01-15'],
            'taken_by' => ['old' => 'Sam Example', 'new' => 'Jordan Example'],
            'department' => ['old' => 'Finance', 'new' => 'Engineering'],
            'site' => ['old' => 'Dry Goods', 'new' => 'Feed Mill'],
        ], $inventory->change_log);
        $this->assertSame(1, $this->onShelf());
        $this->assertSame(InventoryUpdate::ASSIGNED, InventoryUpdate::sole()->kind);

        $this->actingAs($this->admin)->patch(route('inventory.approve', $inventory))->assertSessionHas('status');

        $this->assertSame('approved', $inventory->fresh()->status);
        $this->assertSame(0, $this->onShelf());
    }

    public function test_only_active_returned_records_can_be_assigned()
    {
        $held = $this->issued(5);
        $pending = $this->makeInventory($this->admin, ['item' => 'Laptop', 'returned_at' => now(), 'status' => 'pending', 'approval_type' => 'edit']);
        $inactive = $this->makeInventory($this->admin, ['item' => 'Laptop', 'returned_at' => now(), 'active_yn' => false]);

        foreach ([$held, $pending, $inactive] as $inventory) {
            $this->actingAs($this->admin)->patch(route('inventory.assign', $inventory), $this->assignment())->assertSessionHas('error');
            $this->assertNotSame('Jordan Example', $inventory->fresh()->taken_by);
        }

        $this->assertSame(5, $this->onShelf());
    }

    public function test_assign_validates_its_fields()
    {
        $inventory = $this->returned(1);
        $gone = $this->typeFor(Type::SITE, 'Closed Site');
        $gone->forceFill(['active_yn' => false])->save();

        $this->actingAs($this->admin)
            ->patch(route('inventory.assign', $inventory), [
                'taken_by' => '',
                'department_id' => $this->typeFor(Type::SITE, 'Dry Goods')->id,
                'site_id' => $gone->id,
            ])
            ->assertSessionHasErrors(['date', 'taken_by', 'department_id', 'site_id']);

        $this->assertTrue($inventory->fresh()->isReturned());
    }

    public function test_given_out_and_dashboard_exclude_returned_rows()
    {
        $this->issued(4);
        $this->makeInventory($this->admin, ['item' => 'Laptop', 'qty' => 2, 'returned_at' => now()]);

        $this->assertEquals(1, Stock::withGivenOut()->where('item', 'Laptop')->first()->givenOut());

        $response = $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $response->assertViewHas('givenOut', fn ($value) => $value == 1);
        $response->assertViewHas('issuedThisMonth', fn ($value) => $value == 1);
        $response->assertViewHas('items', fn ($items) => $items->sum('total_qty') == 1);
    }

    public function test_show_page_offers_the_right_action_for_each_state()
    {
        $held = $this->issued(1);
        $returned = $this->makeInventory($this->admin, ['item' => 'Laptop', 'returned_at' => now(), 'returned_by' => $this->admin->id]);
        $pending = $this->makeInventory($this->admin, ['item' => 'Laptop', 'status' => 'pending', 'approval_type' => 'new']);

        $this->actingAs($this->admin)->get(route('inventory.show', $held))->assertOk()
            ->assertSee('Return to stock')
            ->assertSee('data-processing="Returning…"', false)
            ->assertDontSee('Assign to new person')
            ->assertSee('Taken by');

        $this->actingAs($this->admin)->get(route('inventory.show', $returned))->assertOk()
            ->assertSee('Assign to new person')
            ->assertSee('data-processing="Assigning…"', false)
            ->assertSee('Returned to stock')
            ->assertSee('Last held by')
            ->assertSee('Returned on')
            ->assertSee('by Demo Admin')
            ->assertDontSee(route('inventory.return', $returned));

        $this->actingAs($this->admin)->get(route('inventory.show', $pending))->assertOk()
            ->assertDontSee(route('inventory.return', $pending))
            ->assertDontSee(route('inventory.assign', $pending))
            ->assertSee('available once this record is approved');
    }

    public function test_show_page_forms_carry_the_processing_spinner()
    {
        $user = $this->makeUser('user');

        foreach ([$this->issued(1), $this->makeInventory($this->admin, ['item' => 'Laptop', 'returned_at' => now()])] as $inventory) {
            $html = $this->actingAs($user)->get(route('inventory.show', $inventory))->assertOk()->getContent();

            preg_match_all('/<form\b[^>]*method="POST"[^>]*>/is', $html, $forms);
            $this->assertNotEmpty($forms[0]);
            foreach ($forms[0] as $tag) {
                if (! str_contains($tag, route('logout'))) {
                    $this->assertStringContainsString('data-processing=', $tag);
                }
            }
        }
    }

    public function test_inventory_list_marks_and_filters_returned_rows()
    {
        $this->issued(1, ['taken_by' => 'Still Holding']);
        $this->makeInventory($this->admin, ['item' => 'Laptop', 'taken_by' => 'Gave It Back', 'returned_at' => now()]);

        $this->actingAs($this->admin)->get(route('inventory.index'))->assertOk()->assertSee('Returned to stock');

        $this->actingAs($this->admin)->get(route('inventory.index', ['status' => 'returned']))->assertOk()
            ->assertSee('Gave It Back')
            ->assertDontSee('Still Holding');
    }
}
