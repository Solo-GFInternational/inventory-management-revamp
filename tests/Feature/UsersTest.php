<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function deactivate(User $user): User
    {
        $user->forceFill(['active_yn' => false])->save();

        return $user->fresh();
    }

    public function test_settings_redirects_to_the_users_tab()
    {
        $this->actingAs($this->makeUser('admin'))->get('/settings')->assertRedirect('/settings/users');
    }

    public function test_only_admins_can_manage_users()
    {
        $user = $this->makeUser('user');
        $other = $this->makeUser('user');

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('users.store'), [])->assertForbidden();
        $this->actingAs($user)->put(route('users.update', $other), [])->assertForbidden();
        $this->actingAs($user)->delete(route('users.destroy', $other))->assertForbidden();
        $this->actingAs($user)->patch(route('users.reactivate', $other))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get(route('users.index'))->assertRedirect('/login');
    }

    public function test_list_shows_active_users_by_default_and_filters_by_status()
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('user', ['name' => 'Still Here']);
        $this->deactivate($this->makeUser('user', ['name' => 'Long Gone']));

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Still Here')
            ->assertDontSee('Long Gone');

        $this->actingAs($admin)->get(route('users.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Long Gone')
            ->assertDontSee('Still Here');

        $this->actingAs($admin)->get(route('users.index', ['status' => 'all']))
            ->assertSee('Long Gone')
            ->assertSee('Still Here');

        $this->actingAs($admin)->get(route('users.index', ['q' => 'still']))
            ->assertSee('Still Here')
            ->assertDontSee($admin->email);
    }

    public function test_admin_creates_a_user_with_role_and_password()
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Admin',
            'email' => 'new.admin@example.com',
            'role' => 'admin',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $created = User::where('email', 'new.admin@example.com')->firstOrFail();
        $this->assertSame('admin', $created->role);
        $this->assertTrue($created->active_yn);
        $this->assertTrue(Hash::check('secret-pass', $created->password));
        $this->assertEquals($admin->id, $created->created_by);
    }

    public function test_create_validates_password_and_duplicate_active_email()
    {
        $admin = $this->makeUser('admin');
        $existing = $this->makeUser('user');

        $this->actingAs($admin)->post(route('users.store'), [
            '_modal' => 'create-user',
            'name' => 'Dup',
            'email' => $existing->email,
            'role' => 'user',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertSame(1, User::where('email', $existing->email)->count());
    }

    public function test_creating_with_a_deactivated_email_reactivates_that_account()
    {
        $admin = $this->makeUser('admin');
        $gone = $this->deactivate($this->makeUser('user', ['name' => 'Old Name']));

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Name',
            'email' => $gone->email,
            'role' => 'admin',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', $gone->email)->count());
        $gone->refresh();
        $this->assertTrue($gone->active_yn);
        $this->assertSame('New Name', $gone->name);
        $this->assertSame('admin', $gone->role);
        $this->assertTrue(Hash::check('brand-new-pass', $gone->password));
    }

    public function test_admin_edits_a_user()
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser('user');
        $oldHash = $user->password;

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Renamed',
            'email' => 'renamed@example.com',
            'role' => 'admin',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame('renamed@example.com', $user->email);
        $this->assertSame('admin', $user->role);
        $this->assertSame($oldHash, $user->password);
        $this->assertEquals($admin->id, $user->updated_by);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Renamed',
            'email' => 'renamed@example.com',
            'role' => 'admin',
            'password' => 'another-pass',
            'password_confirmation' => 'another-pass',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('another-pass', $user->fresh()->password));
    }

    public function test_edit_rejects_an_email_another_account_uses()
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser('user');

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $admin->email,
            'role' => 'user',
        ])->assertSessionHasErrors('email');

        // Keeping your own email is fine.
        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Same Email',
            'email' => $user->email,
            'role' => 'user',
        ])->assertSessionHasNoErrors();
    }

    public function test_admin_cannot_demote_themselves()
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'user',
        ])->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_deactivate_keeps_the_row_and_reactivate_restores_it()
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser('user');

        $this->actingAs($admin)->delete(route('users.destroy', $user))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertFalse($user->fresh()->active_yn);

        $this->actingAs($admin)->patch(route('users.reactivate', $user))->assertRedirect();

        $this->assertTrue($user->fresh()->active_yn);
    }

    public function test_admin_cannot_deactivate_themselves()
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->delete(route('users.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->active_yn);
    }

    public function test_inactive_user_cannot_sign_in()
    {
        $user = $this->deactivate($this->makeUser('user'));

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => LoginRequest::DEACTIVATED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_inactive_user_with_wrong_password_gets_the_generic_message()
    {
        $user = $this->deactivate($this->makeUser('user'));

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_active_user_can_still_sign_in()
    {
        $user = $this->makeUser('user');

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivated_user_is_signed_out_on_their_next_request()
    {
        // bootstrap/app.php appends this to the web group; appending it again is a no-op.
        $this->app->make(Kernel::class)->appendMiddlewareToGroup('web', EnsureUserIsActive::class);

        $user = $this->makeUser('user');
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user = $this->deactivate($user);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => LoginRequest::DEACTIVATED_MESSAGE]);

        $this->assertGuest();
    }
}
