<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class InventoryAccessTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    public function test_guests_cannot_download_the_pdf()
    {
        $this->get('/inventory/download-pdf')->assertRedirect('/login');
    }

    public function test_non_admins_cannot_delete_or_approve()
    {
        $user = $this->makeUser('user');
        $inventory = $this->makeInventory($user, ['status' => 'pending', 'approval_type' => 'new']);

        $this->actingAs($user)->delete("/inventory/{$inventory->id}")->assertForbidden();
        $this->actingAs($user)->patch("/inventory/{$inventory->id}/approve")->assertForbidden();
        $this->actingAs($user)->get('/inventory/pending')->assertForbidden();
    }

    public function test_non_admins_can_still_add_and_edit_inventory()
    {
        $user = $this->makeUser('user');
        $inventory = $this->makeInventory($user);

        $this->actingAs($user)->get('/inventory')->assertOk()->assertSee('edit-inventory-'.$inventory->id);

        $this->actingAs($user)->put("/inventory/{$inventory->id}", $this->withIds([
            'date' => now()->toDateString(),
            'qty' => 1,
            'item' => 'Laptop Bag',
            'condition' => 'used',
            'taken_by' => 'Someone Else',
            'department' => 'Finance',
            'site' => 'Lautoka',
        ]))->assertRedirect('/inventory');

        $inventory->refresh();
        $this->assertSame('pending', $inventory->status);
        $this->assertSame('edit', $inventory->approval_type);
        $this->assertSame(['old' => 'Someone', 'new' => 'Someone Else'], $inventory->change_log['taken_by']);
        // Foreign keys are logged by name, under the field's display key.
        $this->assertSame(['old' => 'Dry Goods', 'new' => 'Lautoka'], $inventory->change_log['site']);
        $this->assertArrayNotHasKey('site_id', $inventory->change_log);
        $this->assertArrayNotHasKey('department', $inventory->change_log);
    }

    public function test_admin_can_approve_with_post_or_patch()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Laptop Bag', 'qty' => 5]);
        $first = $this->makeInventory($admin, ['status' => 'pending', 'approval_type' => 'new']);
        $second = $this->makeInventory($admin, ['status' => 'pending', 'approval_type' => 'new']);

        $this->actingAs($admin)->post("/inventory/{$first->id}/approve")->assertRedirect();
        $this->actingAs($admin)->patch("/inventory/{$second->id}/approve")->assertRedirect();

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('approved', $second->fresh()->status);
        $this->assertEquals(3, Stock::first()->qty);
    }

    public function test_delete_deactivates_the_inventory_instead_of_removing_it()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin, ['condition' => 'used']);

        $this->actingAs($admin)->delete("/inventory/{$inventory->id}")->assertRedirect('/inventory');

        $this->assertDatabaseHas(Inventory::class, ['id' => $inventory->id]);
        $this->assertFalse($inventory->fresh()->active_yn);

        $this->actingAs($admin)->get('/inventory')->assertOk()->assertDontSee('edit-inventory-'.$inventory->id);
    }

    public function test_approval_is_logged_to_the_activity_log()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin, ['status' => 'pending', 'approval_type' => 'new', 'condition' => 'used']);

        $this->actingAs($admin)->post("/inventory/{$inventory->id}/approve")->assertRedirect();

        $this->assertDatabaseHas('logs.activity_log', [
            'log_name' => 'inventory',
            'event' => 'approved',
            'subject_id' => $inventory->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_pending_page_shows_the_edit_diff()
    {
        $admin = $this->makeUser('admin');
        $this->makeInventory($admin, [
            'status' => 'pending',
            'approval_type' => 'edit',
            'item' => 'Docking Station',
            'department' => 'Information Technology',
            'site' => 'Feed Mill',
            'change_log' => [
                'taken_by' => ['old' => 'Alice Old', 'new' => 'Bob New'],
                'item' => ['old' => 'Old Webcam', 'new' => 'Docking Station'],
            ],
        ]);

        $this->actingAs($admin)->get('/inventory/pending')->assertOk()
            ->assertSee('Alice Old')->assertSee('Bob New')
            ->assertSee('Old Webcam')
            // The row itself shows names, not ids.
            ->assertSee('Docking Station')->assertSee('Information Technology')->assertSee('Feed Mill');
    }

    public function test_non_admin_edit_of_item_and_department_logs_names_for_the_approvals_page()
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser('user');
        $inventory = $this->makeInventory($user, ['condition' => 'used']);

        $this->actingAs($user)->put("/inventory/{$inventory->id}", $this->withIds([
            'date' => now()->toDateString(),
            'qty' => 1,
            'item' => 'Webcam',
            'condition' => 'used',
            'taken_by' => 'Someone',
            'department' => 'Logistics',
            'site' => 'Dry Goods',
        ]))->assertSessionHasNoErrors();

        $log = $inventory->fresh()->change_log;
        $this->assertSame(['old' => 'Laptop Bag', 'new' => 'Webcam'], $log['item']);
        $this->assertSame(['old' => 'Finance', 'new' => 'Logistics'], $log['department']);
        $this->assertCount(2, $log);

        $this->actingAs($admin)->get('/inventory/pending')->assertOk()
            ->assertSee('Laptop Bag')->assertSee('Webcam')->assertSee('Finance')->assertSee('Logistics');
    }

    public function test_index_filters_by_site_id()
    {
        $admin = $this->makeUser('admin');
        $here = $this->makeInventory($admin, ['site' => 'Lautoka', 'taken_by' => 'Lautoka Person']);
        $there = $this->makeInventory($admin, ['site' => 'Feed Mill', 'taken_by' => 'Feed Mill Person']);

        $this->actingAs($admin)->get('/inventory?site_id='.$here->site_id)
            ->assertOk()
            ->assertSee('edit-inventory-'.$here->id)
            ->assertDontSee('edit-inventory-'.$there->id);
    }

    public function test_pdf_downloads()
    {
        $admin = $this->makeUser('admin');
        $this->makeInventory($admin);

        $this->actingAs($admin)->get('/inventory/download-pdf?year='.now()->year)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // The PDF is rendered from names.
        $html = view('inventory.pdf', [
            'inventories' => Inventory::with(['giver', 'stock', 'department', 'site'])->get(),
            'year' => now()->year,
        ])->render();
        $this->assertStringContainsString('Laptop Bag', $html);
        $this->assertStringContainsString('Finance', $html);
        $this->assertStringContainsString('Dry Goods', $html);
    }

    public function test_created_by_and_updated_by_come_from_the_signed_in_user()
    {
        $admin = $this->makeUser('admin');
        $other = $this->makeUser('admin');

        $this->actingAs($admin);
        $inventory = $this->makeInventory($admin);
        $this->assertSame($admin->id, $inventory->created_by);
        $this->assertSame($admin->id, $inventory->updated_by);
        $this->assertTrue($inventory->fresh()->active_yn);

        $this->actingAs($other);
        $inventory->update(['qty' => 2]);
        $this->assertEquals($admin->id, $inventory->fresh()->created_by);
        $this->assertEquals($other->id, $inventory->fresh()->updated_by);
    }

    public function test_admin_created_users_keep_their_role()
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Admin',
            'email' => 'new.admin@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ]);

        $this->assertSame('admin', User::where('email', 'new.admin@example.com')->value('role'));
    }
}
