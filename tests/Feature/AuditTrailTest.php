<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesTypes;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use CreatesTypes, RefreshDatabase;

    private function makeUser(string $role = 'user', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function makeInventory(User $givenBy, array $overrides = []): Inventory
    {
        return $this->makeInventoryRow($givenBy, $overrides);
    }

    private function entries(?string $logName = null)
    {
        return Activity::query()->when($logName, fn ($query) => $query->where('log_name', $logName))->orderBy('id')->get();
    }

    public function test_only_admins_can_open_the_audit_trail()
    {
        $this->get('/settings/audit-trail')->assertRedirect('/login');
        $this->actingAs($this->makeUser('user'))->get('/settings/audit-trail')->assertForbidden();
        $this->actingAs($this->makeUser('admin'))->get('/settings/audit-trail')
            ->assertOk()
            ->assertSee('Audit trail');
    }

    public function test_creating_and_updating_a_record_logs_old_and_new_values()
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $inventory = $this->makeInventory($admin);

        $created = $this->entries('inventory')->last();
        $this->assertSame('created', $created->event);
        $this->assertEquals($admin->id, $created->causer_id);
        $this->assertEquals($inventory->stock_id, $created->properties['attributes']['stock_id']);

        $dryGoods = $inventory->site_id;
        $bakery = $this->makeType(Type::SITE, 'Bakery');
        $inventory->update(['qty' => 3, 'site_id' => $bakery->id]);

        $updated = $this->entries('inventory')->last();
        $this->assertSame('updated', $updated->event);
        $this->assertEquals(['qty' => 1, 'site_id' => $dryGoods], collect($updated->properties['old'])->only(['qty', 'site_id'])->all());
        $this->assertEquals(3, $updated->properties['attributes']['qty']);
        $this->assertEquals($bakery->id, $updated->properties['attributes']['site_id']);
        // Only what changed; bookkeeping is left out.
        $this->assertArrayNotHasKey('stock_id', $updated->properties['attributes']);
        $this->assertArrayNotHasKey('updated_by', $updated->properties['attributes']);
        $this->assertArrayNotHasKey('updated_at', $updated->properties['attributes']);

        // Saving with nothing changed logs nothing.
        $count = Activity::count();
        $inventory->touch();
        $this->assertSame($count, Activity::count());

        $this->get('/settings/audit-trail')
            ->assertOk()
            ->assertSee('Updated inventory')
            ->assertSee('Inventory #'.$inventory->id.' – Laptop Bag for Jane')
            ->assertSee('Quantity')
            ->assertSee('Dry Goods')
            ->assertSee('Bakery');
    }

    public function test_deactivation_and_reactivation_are_shown_as_such()
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $jane = $this->makeUser('user', ['name' => 'Jane Example']);
        $jane->forceFill(['active_yn' => false])->save();

        $entry = $this->entries('user')->last();
        $this->assertSame('deactivated', $entry->event);
        $this->assertSame('Deactivated user', AuditTrail::action($entry));
        $this->assertSame('Jane Example', AuditTrail::about($entry));

        $jane->forceFill(['active_yn' => true])->save();
        $this->assertSame('Reactivated user', AuditTrail::action($this->entries('user')->last()));

        $this->get('/settings/audit-trail')
            ->assertSee('Deactivated user')
            ->assertSee('Reactivated user')
            ->assertSee('Jane Example');
    }

    public function test_approvals_logged_with_activity_read_well()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);

        activity('inventory')->causedBy($admin)->performedOn($inventory)->event('approved')->log('Approved');

        $entry = $this->entries('inventory')->last();
        $this->assertSame('Approved inventory', AuditTrail::action($entry));
        $this->assertSame('Inventory #'.$inventory->id.' – Laptop Bag for Jane', AuditTrail::about($entry));
    }

    public function test_password_is_never_logged()
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $user = $this->makeUser('user');
        $user->update(['password' => bcrypt('new-secret-password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'a-wrong-password']);

        foreach (Activity::all() as $entry) {
            $json = json_encode($entry->properties);
            $this->assertStringNotContainsString('password', $json);
            $this->assertStringNotContainsString('remember_token', $json);
            $this->assertStringNotContainsString('a-wrong-password', $json);
        }
    }

    public function test_signing_in_and_out_is_logged_with_the_ip_address()
    {
        $user = $this->makeUser('user');

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticated();

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->post('/logout');
        $this->assertGuest();

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $entries = $this->entries(AuditTrail::LOG_SIGN_IN);
        $this->assertSame(['signed in', 'signed out', 'failed'], $entries->pluck('event')->all());
        $this->assertEquals([$user->id, $user->id, $user->id], $entries->pluck('causer_id')->all());
        $this->assertSame(['10.1.2.3', '10.1.2.3', '10.9.9.9'], $entries->map(fn ($entry) => AuditTrail::ip($entry))->all());
        $this->assertSame('Failed sign-in as '.$user->email, $entries->last()->description);
    }

    public function test_pages_opened_by_signed_in_users_are_logged()
    {
        $user = $this->makeUser('user');

        // Guests opening the sign-in page aren't logged.
        $this->get('/login')->assertOk();
        $this->assertCount(0, $this->entries(AuditTrail::LOG_NAVIGATION));

        $this->actingAs($user)->get('/profile')->assertOk();

        $views = $this->entries(AuditTrail::LOG_NAVIGATION);
        $this->assertCount(1, $views);
        $this->assertSame('Opened Profile', $views->first()->description);
        $this->assertSame('profile.edit', $views->first()->properties['route']);
        $this->assertSame('/profile', $views->first()->properties['url']);
        $this->assertSame('127.0.0.1', AuditTrail::ip($views->first()));

        // A reload straight away, a redirect, a form post and a JSON call aren't pages.
        $this->actingAs($user)->get('/profile');
        $this->actingAs($user)->get('/');
        $this->actingAs($user)->post('/stock', ['item' => 'Mouse', 'qty' => 2]);
        $this->actingAs($user)->getJson('/inventory');

        $this->assertCount(1, $this->entries(AuditTrail::LOG_NAVIGATION));
    }

    public function test_filters_by_user_type_and_date()
    {
        $admin = $this->makeUser('admin', ['name' => 'Ada Admin']);
        $bob = $this->makeUser('user', ['name' => 'Bob Builder']);

        activity(AuditTrail::LOG_SIGN_IN)->causedBy($bob)->event('signed in')->log('Bob signed in marker');
        activity(AuditTrail::LOG_NAVIGATION)->causedBy($admin)->event('viewed')->log('Ada opened marker');
        $old = activity('stock')->causedBy($admin)->log('Old change marker');
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        activity('stock')->log('System change marker');

        $this->actingAs($admin);

        $this->get('/settings/audit-trail?user='.$bob->id)
            ->assertSee('Bob signed in marker')
            ->assertDontSee('Ada opened marker')
            ->assertDontSee('System change marker');

        $this->get('/settings/audit-trail?user=system')
            ->assertSee('System change marker')
            ->assertDontSee('Bob signed in marker');

        $this->get('/settings/audit-trail?type=sign_in')
            ->assertSee('Bob signed in marker')
            ->assertDontSee('Ada opened marker')
            ->assertDontSee('System change marker');

        $this->get('/settings/audit-trail?type=navigation')
            ->assertSee('Ada opened marker')
            ->assertDontSee('Bob signed in marker');

        $this->get('/settings/audit-trail?type=changes')
            ->assertSee('System change marker')
            ->assertSee('Old change marker')
            ->assertDontSee('Bob signed in marker')
            ->assertDontSee('Ada opened marker');

        $this->get('/settings/audit-trail?from='.now()->subDays(2)->format('Y-m-d'))
            ->assertSee('System change marker')
            ->assertDontSee('Old change marker');

        $this->get('/settings/audit-trail?until='.now()->subDays(5)->format('Y-m-d'))
            ->assertSee('Old change marker')
            ->assertDontSee('System change marker');
    }

    public function test_stock_changes_are_logged_by_the_trait()
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $stock = new Stock(['item' => 'Mouse', 'qty' => 5]);
        $stock->save();
        $stock->update(['qty' => 4]);

        $entry = $this->entries('stock')->last();
        $this->assertSame('Updated stock', AuditTrail::action($entry));
        $this->assertSame('Stock #'.$stock->id.' – Mouse', AuditTrail::about($entry));
        $this->assertSame([['field' => 'Quantity', 'old' => '5', 'new' => '4']], AuditTrail::changes($entry));
    }

    public function test_inventory_changes_show_item_department_and_site_names()
    {
        $admin = $this->makeUser('admin', ['name' => 'Ada Admin']);
        $this->actingAs($admin);

        $inventory = $this->makeInventory($admin, ['item' => 'Docking Station', 'department' => 'Finance', 'site' => 'Dry Goods']);
        $created = $this->entries('inventory')->last();

        $changes = collect(AuditTrail::changes($created))->pluck('new', 'field');
        $this->assertSame('Docking Station', $changes['Item']);
        $this->assertSame('Finance', $changes['Department']);
        $this->assertSame('Dry Goods', $changes['Site']);
        $this->assertSame('Ada Admin', $changes['Given by']);

        $hr = $this->makeType(Type::DEPARTMENT, 'HR');
        $monitor = $this->stockNamed('Monitor');
        $inventory->update(['department_id' => $hr->id, 'stock_id' => $monitor->id]);

        $updated = $this->entries('inventory')->last();
        $this->assertEqualsCanonicalizing([
            ['field' => 'Item', 'old' => 'Docking Station', 'new' => 'Monitor'],
            ['field' => 'Department', 'old' => 'Finance', 'new' => 'HR'],
        ], AuditTrail::changes($updated));
        // About uses the record's current stock item.
        $this->assertSame('Inventory #'.$inventory->id.' – Monitor for Jane', AuditTrail::about($updated));

        // A type renamed or deactivated since still shows (by its current name).
        $hr->forceFill(['type_name' => 'People', 'active_yn' => false])->save();

        $this->get('/settings/audit-trail?type=changes')
            ->assertOk()
            ->assertSee('Docking Station')
            ->assertSee('Monitor')
            ->assertSee('People')
            ->assertSee('Inventory #'.$inventory->id.' – Monitor for Jane');
    }

    public function test_names_are_looked_up_in_bulk()
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        foreach (['Mouse', 'Keyboard', 'Headset', 'Monitor'] as $i => $item) {
            $this->makeInventory($admin, ['item' => $item, 'department' => 'Dept '.$i, 'site' => 'Site '.$i]);
        }

        $entries = $this->entries('inventory')->load('subject');
        $this->assertCount(4, $entries);

        DB::enableQueryLog();
        $names = AuditTrail::names($entries);
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEqualsCanonicalizing(['Mouse', 'Keyboard', 'Headset', 'Monitor'], array_values($names['stocks']));
        $this->assertCount(8, $names['types']);

        // With the names given, formatting needs no further queries.
        DB::flushQueryLog();
        DB::enableQueryLog();
        foreach ($entries as $entry) {
            AuditTrail::changes($entry, $names);
        }
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_about_falls_back_to_logged_values_when_the_record_is_gone()
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $inventory = $this->makeInventory($admin, ['item' => 'Webcam', 'taken_by' => 'Sam']);
        $id = $inventory->id;

        // Rows are never deleted by the app; remove it behind its back.
        Inventory::query()->whereKey($id)->toBase()->delete();

        $entry = $this->entries('inventory')->last()->fresh();
        $this->assertNull($entry->subject);
        $this->assertSame('Inventory #'.$id.' – Webcam for Sam', AuditTrail::about($entry));
    }

    public function test_type_changes_are_logged_and_read_well()
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $this->get(route('types.index'))->assertOk();
        $this->post(route('types.store'), ['type_group' => Type::DEPARTMENT, 'type_name' => 'Finance', 'sort_order' => 5])
            ->assertSessionHasNoErrors();
        $type = Type::query()->where('type_name', 'Finance')->firstOrFail();

        $created = $this->entries('type')->last();
        $this->assertSame('Created type', AuditTrail::action($created));
        $this->assertSame('Department – Finance', AuditTrail::about($created));
        $changes = collect(AuditTrail::changes($created))->pluck('new', 'field');
        $this->assertSame('Department', $changes['Group']);
        $this->assertSame('Finance', $changes['Name']);
        $this->assertSame('5', $changes['Order']);

        $this->put(route('types.update', $type), ['type_name' => 'Finance & Admin', 'sort_order' => 5]);
        $updated = $this->entries('type')->last();
        $this->assertSame('Updated type', AuditTrail::action($updated));
        $this->assertSame([['field' => 'Name', 'old' => 'Finance', 'new' => 'Finance & Admin']], AuditTrail::changes($updated));

        $this->delete(route('types.destroy', $type));
        $this->assertSame('Deactivated type', AuditTrail::action($this->entries('type')->last()));

        $this->patch(route('types.reactivate', $type));
        $this->assertSame('Reactivated type', AuditTrail::action($this->entries('type')->last()));

        $this->get('/settings/audit-trail')
            ->assertOk()
            ->assertSee('Created type')
            ->assertSee('Department – Finance &amp; Admin', false)
            ->assertSee('Opened Settings – Types', false);
    }
}
