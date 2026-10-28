<?php

namespace Tests\Feature;

use App\Models\InventoryUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * The Timeline card on an inventory record's page: anyone signed in adds
 * notes; the author or an admin removes them (deactivated, never deleted);
 * returned / assigned entries stay; the record's creation always ends it.
 */
class InventoryTimelineTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    public function test_user_and_admin_can_add_a_note()
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser('user');
        $inventory = $this->makeInventory($admin);

        foreach ([$user, $admin] as $who) {
            $this->actingAs($who)
                ->post(route('inventory.updates.store', $inventory), ['body' => "  New battery fitted by {$who->name}  "])
                ->assertRedirect(route('inventory.show', $inventory).'#timeline')
                ->assertSessionHas('status', 'Update added to the timeline.');
        }

        $notes = InventoryUpdate::orderBy('id')->get();
        $this->assertCount(2, $notes);
        $this->assertSame(InventoryUpdate::NOTE, $notes[0]->kind);
        $this->assertSame("New battery fitted by {$user->name}", $notes[0]->body);
        $this->assertEquals($inventory->id, $notes[0]->inventory_id);
        $this->assertEquals($user->id, $notes[0]->created_by);
        $this->assertEquals($admin->id, $notes[1]->created_by);
    }

    public function test_note_must_have_text_and_not_be_too_long()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);

        foreach (['', "   \n  ", str_repeat('a', 2001)] as $body) {
            $this->actingAs($admin)
                ->from(route('inventory.show', $inventory))
                ->post(route('inventory.updates.store', $inventory), ['body' => $body])
                ->assertSessionHasErrors('body', null, 'timeline');
        }

        $this->assertSame(0, InventoryUpdate::count());

        // 2000 characters is fine.
        $this->actingAs($admin)
            ->post(route('inventory.updates.store', $inventory), ['body' => str_repeat('a', 2000)])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, InventoryUpdate::count());
    }

    public function test_validation_error_is_shown_under_the_field_with_the_text_kept()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);

        $this->actingAs($admin)
            ->from(route('inventory.show', $inventory))
            ->followingRedirects()
            ->post(route('inventory.updates.store', $inventory), ['body' => str_repeat('b', 2001)])
            ->assertOk()
            ->assertSee('id="timeline-body-error"', false)
            ->assertSee('must not be greater than 2000 characters', false)
            ->assertSee(str_repeat('b', 2001));
    }

    public function test_note_on_a_deactivated_record_is_refused_and_the_form_hidden()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);
        $this->actingAs($admin);
        $note = InventoryUpdate::record($inventory, InventoryUpdate::NOTE, 'Screen replaced');
        $inventory->forceFill(['active_yn' => false])->save();

        $this->post(route('inventory.updates.store', $inventory), ['body' => 'Too late'])
            ->assertRedirect(route('inventory.show', $inventory).'#timeline')
            ->assertSessionHas('error', 'This inventory record is deactivated, so updates can no longer be added.');
        $this->assertSame(1, InventoryUpdate::count());

        // Nor can its notes be removed.
        $this->delete(route('inventory.updates.destroy', [$inventory, $note]))->assertSessionHas('error');
        $this->assertTrue($note->fresh()->active_yn);

        // The timeline is still shown, read-only: no form, no remove buttons.
        $this->get(route('inventory.show', $inventory))
            ->assertOk()
            ->assertSee('Screen replaced')
            ->assertDontSee(route('inventory.updates.store', $inventory))
            ->assertDontSee('data-processing="Removing update…"', false);
    }

    public function test_timeline_lists_entries_newest_first_ending_with_the_record_created()
    {
        $admin = $this->makeUser('admin', ['name' => 'Ada Admin']);
        $user = $this->makeUser('user', ['name' => 'Uma User']);

        $this->travelTo(now()->subDays(3));
        $inventory = $this->makeInventory($admin, ['taken_by' => 'Ben Borrower']);

        $this->travelBack();
        $this->travelTo(now()->subDays(2));
        $this->actingAs($user);
        InventoryUpdate::record($inventory, InventoryUpdate::NOTE, "Keyboard replaced\nunder warranty");

        $this->travelBack();
        $this->travelTo(now()->subDay());
        $this->actingAs($admin);
        InventoryUpdate::record($inventory, InventoryUpdate::RETURNED, 'Back on the shelf');
        $this->travelBack();

        $this->actingAs($user)->get(route('inventory.show', $inventory))
            ->assertOk()
            ->assertSee('id="timeline"', false)
            ->assertSeeInOrder([
                'Returned to stock', 'Ada Admin', 'Back on the shelf',
                'Note', 'Uma User', 'Keyboard replaced',
                // Handed over: taken_by may now be a later holder, so the created entry doesn't name it.
                'Record created', 'Issued by Ada Admin.',
            ])
            ->assertDontSee('Issued to Ben Borrower by')
            ->assertSee($inventory->updates()->first()->created_at->format('d/m/Y H:i:s'))
            ->assertDontSee('No updates yet.');
    }

    public function test_empty_timeline_invites_the_first_update()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin, ['taken_by' => 'Ben Borrower']);

        $this->actingAs($admin)->get(route('inventory.show', $inventory))
            ->assertOk()
            ->assertSee('No updates yet.')
            ->assertSee('Issued to Ben Borrower');
    }

    public function test_author_can_remove_their_own_note()
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser('user');
        $inventory = $this->makeInventory($admin);
        $this->actingAs($user);
        $note = InventoryUpdate::record($inventory, InventoryUpdate::NOTE, 'Charger swapped');

        $this->get(route('inventory.show', $inventory))
            ->assertSee(route('inventory.updates.destroy', [$inventory, $note]));

        $this->delete(route('inventory.updates.destroy', [$inventory, $note]))
            ->assertRedirect(route('inventory.show', $inventory).'#timeline')
            ->assertSessionHas('status', 'Update removed.');

        // Deactivated, not deleted, and gone from the page.
        $this->assertFalse($note->fresh()->active_yn);
        $this->get(route('inventory.show', $inventory))->assertDontSee('Charger swapped');
    }

    public function test_another_user_cannot_remove_a_note()
    {
        $admin = $this->makeUser('admin');
        $author = $this->makeUser('user');
        $other = $this->makeUser('user');
        $inventory = $this->makeInventory($admin);
        $this->actingAs($author);
        $note = InventoryUpdate::record($inventory, InventoryUpdate::NOTE, 'Charger swapped');

        $this->actingAs($other)->get(route('inventory.show', $inventory))
            ->assertSee('Charger swapped')
            ->assertDontSee(route('inventory.updates.destroy', [$inventory, $note]));

        $this->actingAs($other)->delete(route('inventory.updates.destroy', [$inventory, $note]))->assertForbidden();
        $this->assertTrue($note->fresh()->active_yn);
    }

    public function test_admin_can_remove_anyones_note()
    {
        $admin = $this->makeUser('admin');
        $author = $this->makeUser('user');
        $inventory = $this->makeInventory($admin);
        $this->actingAs($author);
        $note = InventoryUpdate::record($inventory, InventoryUpdate::NOTE, 'Charger swapped');

        $this->actingAs($admin)->delete(route('inventory.updates.destroy', [$inventory, $note]))
            ->assertSessionHas('status', 'Update removed.');
        $this->assertFalse($note->fresh()->active_yn);
    }

    public function test_returned_and_assigned_entries_cannot_be_removed()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);
        $this->actingAs($admin);
        $returned = InventoryUpdate::record($inventory, InventoryUpdate::RETURNED, 'Returned by Ben');
        $assigned = InventoryUpdate::record($inventory, InventoryUpdate::ASSIGNED, 'Assigned to Cat');

        $this->get(route('inventory.show', $inventory))
            ->assertSee('Returned by Ben')
            ->assertDontSee(route('inventory.updates.destroy', [$inventory, $returned]))
            ->assertDontSee(route('inventory.updates.destroy', [$inventory, $assigned]));

        foreach ([$returned, $assigned] as $entry) {
            $this->delete(route('inventory.updates.destroy', [$inventory, $entry]))->assertForbidden();
            $this->assertTrue($entry->fresh()->active_yn);
        }
    }

    public function test_an_update_from_another_record_is_not_found()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);
        $other = $this->makeInventory($admin);
        $this->actingAs($admin);
        $note = InventoryUpdate::record($other, InventoryUpdate::NOTE, 'Elsewhere');

        $this->delete(route('inventory.updates.destroy', [$inventory, $note]))->assertNotFound();
        $this->assertTrue($note->fresh()->active_yn);
    }

    public function test_timeline_forms_have_processing_spinners()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);
        $this->actingAs($admin);
        InventoryUpdate::record($inventory, InventoryUpdate::NOTE, 'Fan cleaned');

        $this->get(route('inventory.show', $inventory))
            ->assertSee('data-processing="Adding update…"', false)
            ->assertSee('data-processing="Removing update…"', false);
    }
}
