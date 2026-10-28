<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\UserAccountCredentials;
use App\Models\User;
use App\Support\EmailBranding;
use App\Support\TemporaryPassword;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
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

    /**
     * Creates a user through the form and returns the temporary password
     * that was emailed to them (Mail must be faked).
     */
    private function createThroughForm(User $admin, array $fields): string
    {
        $this->actingAs($admin)->post(route('users.store'), $fields)
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $mails = Mail::queued(UserAccountCredentials::class, fn (UserAccountCredentials $mail) => $mail->hasTo($fields['email']));
        $this->assertCount(1, $mails);

        return $mails->first()->password;
    }

    public function test_create_modal_has_no_password_fields_and_says_one_is_emailed()
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route('users.index'))->assertOk()->getContent();

        $start = strpos($html, 'id="create-user-form"');
        $create = substr($html, $start, strpos($html, '</form>', $start) - $start);

        $this->assertStringNotContainsString('name="password"', $create);
        $this->assertStringNotContainsString('name="password_confirmation"', $create);
        $this->assertStringContainsString('temporary password', $create);
    }

    public function test_admin_creates_a_user_and_their_temporary_password_is_emailed()
    {
        Mail::fake();
        $admin = $this->makeUser('admin');

        $password = $this->createThroughForm($admin, [
            'name' => 'New Admin',
            'email' => 'new.admin@example.com',
            'role' => 'admin',
        ]);

        $created = User::where('email', 'new.admin@example.com')->firstOrFail();
        $this->assertSame('admin', $created->role);
        $this->assertTrue($created->active_yn);
        $this->assertGreaterThanOrEqual(TemporaryPassword::MIN_LENGTH, strlen($password));
        $this->assertTrue(Hash::check($password, $created->password));
        $this->assertEquals($admin->id, $created->created_by);

        $this->assertSame('Account created — sign-in details emailed to new.admin@example.com.', session('status'));
        $this->assertStringNotContainsString($password, serialize(session()->all()));

        // The new account can sign in with the emailed password.
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => 'new.admin@example.com', 'password' => $password])
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_credentials_email_has_the_sign_in_details()
    {
        $user = $this->makeUser('user', ['name' => 'Jane Staff', 'email' => 'jane@example.com']);

        $mail = new UserAccountCredentials($user, 'Ab3-xyzPQ7');

        $mail->assertHasSubject('Your Inventory Management System account');
        $mail->assertSeeInHtml('Inventory Management System');
        $mail->assertSeeInHtml('jane@example.com');
        $mail->assertSeeInHtml('Ab3-xyzPQ7');
        $mail->assertSeeInHtml(route('login'));
        $mail->assertSeeInHtml('Your profile');
        $mail->assertSeeInHtml('cid:'.EmailBranding::LOGO_CID, false);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $mail);

        $reactivated = new UserAccountCredentials($user, 'Ab3-xyzPQ7', reactivated: true);
        $reactivated->assertHasSubject('Your Inventory Management System account has been reactivated');
    }

    public function test_sent_credentials_email_embeds_the_logo()
    {
        $user = $this->makeUser('user');

        Mail::to($user)->send(new UserAccountCredentials($user, 'Ab3-xyzPQ7'));

        $message = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $logo = collect($message->getAttachments())->first(fn ($part) => $part->hasContentId() && $part->getContentId() === EmailBranding::LOGO_CID);

        $this->assertNotNull($logo);
    }

    public function test_create_validates_role_and_duplicate_active_email()
    {
        Mail::fake();
        $admin = $this->makeUser('admin');
        $existing = $this->makeUser('user');

        $this->actingAs($admin)->post(route('users.store'), [
            '_modal' => 'create-user',
            'name' => 'Dup',
            'email' => $existing->email,
            'role' => 'superuser',
        ])->assertSessionHasErrors(['email', 'role'])->assertSessionDoesntHaveErrors('password');

        $this->assertSame(1, User::where('email', $existing->email)->count());
        Mail::assertNothingQueued();
    }

    public function test_creating_with_a_deactivated_email_reactivates_that_account_with_a_new_password()
    {
        Mail::fake();
        $admin = $this->makeUser('admin');
        $gone = $this->deactivate($this->makeUser('user', ['name' => 'Old Name']));

        $password = $this->createThroughForm($admin, [
            'name' => 'New Name',
            'email' => $gone->email,
            'role' => 'admin',
        ]);

        Mail::assertQueued(UserAccountCredentials::class, fn (UserAccountCredentials $mail) => $mail->reactivated);

        $this->assertSame(1, User::where('email', $gone->email)->count());
        $gone->refresh();
        $this->assertTrue($gone->active_yn);
        $this->assertSame('New Name', $gone->name);
        $this->assertSame('admin', $gone->role);
        $this->assertTrue(Hash::check($password, $gone->password));
        $this->assertFalse(Hash::check('password', $gone->password));
        $this->assertSame('User reactivated', session('status_title'));
    }

    public function test_a_failed_email_keeps_the_account_and_flashes_an_error()
    {
        Exceptions::fake();

        // An SMTP server that refuses every message.
        Mail::extend('broken', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection refused');
            }

            public function __toString(): string
            {
                return 'broken';
            }
        });
        config(['mail.mailers.broken' => ['transport' => 'broken'], 'mail.default' => 'broken']);

        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'No Mail',
            'email' => 'no.mail@example.com',
            'role' => 'user',
        ])->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('status')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'could not be sent to no.mail@example.com'));

        $created = User::where('email', 'no.mail@example.com')->firstOrFail();
        $this->assertTrue($created->active_yn);
        Exceptions::assertReported(TransportException::class);

        $this->assertTrue(Activity::where('subject_id', $created->id)->where('event', 'credentials email failed')->exists());
        $this->assertFalse(Activity::where('event', 'credentials emailed')->exists());
    }

    public function test_the_temporary_password_never_reaches_the_activity_log()
    {
        Mail::fake();
        $admin = $this->makeUser('admin');
        $gone = $this->deactivate($this->makeUser('user'));

        $created = $this->createThroughForm($admin, ['name' => 'Logged', 'email' => 'logged@example.com', 'role' => 'user']);
        $reactivated = $this->createThroughForm($admin, ['name' => 'Back', 'email' => $gone->email, 'role' => 'user']);

        $this->assertTrue(Activity::where('event', 'credentials emailed')->count() === 2);

        foreach (Activity::all() as $entry) {
            $logged = json_encode($entry->properties).$entry->description;

            foreach ([$created, $reactivated] as $password) {
                $this->assertStringNotContainsString($password, $logged);
            }
            $this->assertArrayNotHasKey('password', (array) data_get($entry->properties, 'attributes', []));
            $this->assertArrayNotHasKey('password', (array) data_get($entry->properties, 'old', []));
        }
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
