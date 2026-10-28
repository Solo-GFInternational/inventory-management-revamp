<?php

namespace Tests\Feature;

use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTypes;
use Tests\TestCase;

class TypesTest extends TestCase
{
    use CreatesTypes, RefreshDatabase;

    private function makeUser(string $role = 'user'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    public function test_only_admins_can_manage_types()
    {
        $user = $this->makeUser('user');
        $type = $this->makeType(Type::DEPARTMENT, 'Finance');

        $this->actingAs($user)->get(route('types.index'))->assertForbidden();
        $this->actingAs($user)->post(route('types.store'), [])->assertForbidden();
        $this->actingAs($user)->put(route('types.update', $type), [])->assertForbidden();
        $this->actingAs($user)->delete(route('types.destroy', $type))->assertForbidden();
        $this->actingAs($user)->patch(route('types.reactivate', $type))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get(route('types.index'))->assertRedirect('/login');

        $this->actingAs($this->makeUser('admin'))->get(route('types.index'))
            ->assertOk()
            ->assertSee('New type')
            ->assertSee('Finance');
    }

    public function test_admin_creates_a_type()
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('types.store'), [
            '_modal' => 'create-type',
            'type_group' => Type::SITE,
            'type_name' => 'Bakery',
            'sort_order' => 20,
        ])->assertRedirect(route('types.index', ['group' => Type::SITE]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status_title', 'Type created');

        $type = Type::query()->where('type_name', 'Bakery')->firstOrFail();
        $this->assertSame(Type::SITE, $type->type_group);
        $this->assertEquals(20, $type->sort_order);
        $this->assertTrue($type->fresh()->active_yn);
        $this->assertEquals($admin->id, $type->created_by);
    }

    public function test_create_validates_group_name_and_order()
    {
        $this->actingAs($this->makeUser('admin'))->post(route('types.store'), [
            'type_group' => 'colour',
            'type_name' => '',
            'sort_order' => -1,
        ])->assertSessionHasErrors(['type_group', 'type_name', 'sort_order']);

        $this->assertSame(0, Type::count());
    }

    public function test_name_is_unique_within_a_group_among_active_types()
    {
        $admin = $this->makeUser('admin');
        $this->makeType(Type::DEPARTMENT, 'Finance');

        // Same group, same name (any case): refused.
        $this->actingAs($admin)->post(route('types.store'), [
            'type_group' => Type::DEPARTMENT, 'type_name' => 'finance', 'sort_order' => 0,
        ])->assertSessionHasErrors(['type_name' => 'This group already has an active type with this name.']);

        // Another group may use the name.
        $this->actingAs($admin)->post(route('types.store'), [
            'type_group' => Type::SITE, 'type_name' => 'Finance', 'sort_order' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Type::where('type_name', 'Finance')->count());
    }

    public function test_creating_a_deactivated_name_reactivates_it()
    {
        $admin = $this->makeUser('admin');
        $old = $this->makeType(Type::DEPARTMENT, 'Finance', 5, active: false);

        $this->actingAs($admin)->post(route('types.store'), [
            'type_group' => Type::DEPARTMENT, 'type_name' => 'Finance', 'sort_order' => 30,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('status_title', 'Type reactivated');

        $this->assertSame(1, Type::where('type_name', 'Finance')->count());
        $old->refresh();
        $this->assertTrue($old->active_yn);
        $this->assertEquals(30, $old->sort_order);
    }

    public function test_admin_edits_a_type_but_not_its_group()
    {
        $admin = $this->makeUser('admin');
        $type = $this->makeType(Type::DEPARTMENT, 'Finanse', 1);
        $this->makeType(Type::DEPARTMENT, 'HR');

        $this->actingAs($admin)->put(route('types.update', $type), [
            '_modal' => 'edit-type-'.$type->id,
            'type_group' => Type::SITE,
            'type_name' => 'Finance',
            'sort_order' => 3,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('status_title', 'Type updated');

        $type->refresh();
        $this->assertSame('Finance', $type->type_name);
        $this->assertEquals(3, $type->sort_order);
        $this->assertSame(Type::DEPARTMENT, $type->type_group);
        $this->assertEquals($admin->id, $type->updated_by);

        // Keeping its own name is fine; taking another active type's isn't.
        $this->actingAs($admin)->put(route('types.update', $type), ['type_name' => 'Finance', 'sort_order' => 4])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('types.update', $type), ['type_name' => 'HR', 'sort_order' => 4])
            ->assertSessionHasErrors('type_name');

        // The edit modal shows the group read-only.
        $this->actingAs($admin)->get(route('types.index'))
            ->assertSee('<div id="edit-type-'.$type->id.'-type_group" class="gf-readonly-field">Department</div>', false);
    }

    public function test_deactivate_and_reactivate()
    {
        $admin = $this->makeUser('admin');
        $type = $this->makeType(Type::SITE, 'Dry Goods');
        $this->makeInventoryRow($admin, ['site' => 'Dry Goods']);

        $this->actingAs($admin)->get(route('types.index'))
            ->assertSee('1 active inventory record already uses it and will keep it.');

        // Allowed while in use: the inventory row keeps it, new rows can't pick it.
        $this->actingAs($admin)->delete(route('types.destroy', $type))
            ->assertRedirect()
            ->assertSessionHas('status_title', 'Type deactivated');

        $this->assertFalse($type->fresh()->active_yn);
        $this->assertNotContains('Dry Goods', Type::options(Type::SITE)->all());
        $this->assertContains('Dry Goods', Type::options(Type::SITE, $type->id)->all());

        $this->actingAs($admin)->patch(route('types.reactivate', $type))
            ->assertRedirect()
            ->assertSessionHas('status_title', 'Type reactivated');

        $this->assertTrue($type->fresh()->active_yn);
    }

    public function test_reactivate_refuses_when_an_active_type_has_the_name()
    {
        $admin = $this->makeUser('admin');
        $old = $this->makeType(Type::DEPARTMENT, 'Finance', active: false);
        $this->makeType(Type::DEPARTMENT, 'Finance');

        $this->actingAs($admin)->patch(route('types.reactivate', $old))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($old->fresh()->active_yn);
    }

    public function test_filters_by_group_status_and_search()
    {
        $admin = $this->makeUser('admin');
        $this->makeType(Type::DEPARTMENT, 'Finance');
        $this->makeType(Type::DEPARTMENT, 'Warehouse Ops');
        $this->makeType(Type::SITE, 'Bakery');
        $this->makeType(Type::SITE, 'Closed Depot', active: false);

        $this->actingAs($admin);

        $this->get(route('types.index'))
            ->assertSee('Finance')->assertSee('Bakery')->assertDontSee('Closed Depot');

        $this->get(route('types.index', ['group' => Type::SITE]))
            ->assertSee('Bakery')->assertDontSee('Finance');

        $this->get(route('types.index', ['status' => 'inactive']))
            ->assertSee('Closed Depot')->assertDontSee('Bakery');

        $this->get(route('types.index', ['status' => 'all', 'group' => Type::SITE]))
            ->assertSee('Closed Depot')->assertSee('Bakery')->assertDontSee('Finance');

        $this->get(route('types.index', ['q' => 'house']))
            ->assertSee('Warehouse Ops')->assertDontSee('Finance')->assertDontSee('Bakery');

        // An unknown group or status falls back to all groups / active.
        $this->get(route('types.index', ['group' => 'nope', 'status' => 'nope']))
            ->assertOk()->assertSee('Finance')->assertSee('Bakery')->assertDontSee('Closed Depot');
    }

    public function test_list_is_ordered_by_group_then_order_then_name()
    {
        $admin = $this->makeUser('admin');
        $this->makeType(Type::SITE, 'Zeta Site', 1);
        $this->makeType(Type::DEPARTMENT, 'Beta Dept', 2);
        $this->makeType(Type::DEPARTMENT, 'Alpha Dept', 2);
        $this->makeType(Type::DEPARTMENT, 'Zulu Dept', 1);

        $this->actingAs($admin)->get(route('types.index'))
            ->assertSeeInOrder(['Zulu Dept', 'Alpha Dept', 'Beta Dept', 'Zeta Site']);
    }

    public function test_every_changing_form_has_a_processing_alert()
    {
        $admin = $this->makeUser('admin');
        $this->makeType(Type::DEPARTMENT, 'Finance');
        $this->makeType(Type::SITE, 'Closed Depot', active: false);

        $html = $this->actingAs($admin)->get(route('types.index', ['status' => 'all']))->assertOk()->getContent();

        preg_match_all('/<form\b[^>]*>/is', $html, $matches);
        $posts = 0;

        foreach ($matches[0] as $tag) {
            $isGet = (bool) preg_match('/method="GET"/i', $tag);
            $isLogout = str_contains($tag, 'action="'.route('logout').'"');

            if ($isGet || $isLogout) {
                $this->assertStringNotContainsString('data-processing', $tag, "Unexpected spinner on: {$tag}");
            } else {
                $this->assertStringContainsString('data-processing=', $tag, "Missing spinner on: {$tag}");
                $posts++;
            }
        }

        $this->assertGreaterThanOrEqual(5, $posts);
        $this->assertStringContainsString('data-processing="Saving the type…"', $html);
        $this->assertStringContainsString('data-processing="Saving changes…"', $html);
        $this->assertStringContainsString('data-processing="Deactivating…"', $html);
        $this->assertStringContainsString('data-processing="Reactivating…"', $html);
    }

    public function test_failed_validation_reopens_the_modal_that_was_submitted()
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->from(route('types.index'))->post(route('types.store'), [
            '_modal' => 'create-type',
            'type_group' => Type::DEPARTMENT,
            'type_name' => '',
            'sort_order' => 0,
        ])->assertRedirect(route('types.index'));

        $html = $this->actingAs($admin)->get(route('types.index'))->getContent();

        $this->assertMatchesRegularExpression('/<dialog id="create-type"\s+data-open-on-load/', $html);
        $this->assertSame(1, substr_count($html, 'data-open-on-load'));
    }
}
