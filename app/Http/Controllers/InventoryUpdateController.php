<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryUpdate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Notes on an inventory item's timeline (the Timeline card on its view
 * page), e.g. "New battery fitted". Any signed-in user can add one to an
 * active record; the note's author or an admin can remove it. Returned /
 * assigned entries are written by the handover actions and can't be
 * removed. Nothing is deleted: a removed note is deactivated.
 */
class InventoryUpdateController extends Controller
{
    /** Longest note accepted, in characters (the form's hint says so too). */
    public const MAX_LENGTH = 2000;

    public function store(Request $request, Inventory $inventory): RedirectResponse
    {
        if (! $inventory->active_yn) {
            return $this->backToTimeline($inventory)->with('error', 'This inventory record is deactivated, so updates can no longer be added.');
        }

        // Own error bag, so the card shows the message under the field instead
        // of the layout's SweetAlert. TrimStrings + ConvertEmptyStringsToNull
        // turn a whitespace-only note into null, which "required" refuses.
        $validated = $request->validateWithBag('timeline', [
            'body' => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ], [
            'body.required' => 'Type the update to add.',
        ], [
            'body' => 'update',
        ]);

        InventoryUpdate::record($inventory, InventoryUpdate::NOTE, $validated['body']);

        return $this->backToTimeline($inventory)->with('status', 'Update added to the timeline.');
    }

    public function destroy(Request $request, Inventory $inventory, InventoryUpdate $update): RedirectResponse
    {
        abort_unless($update->kind === InventoryUpdate::NOTE, 403, 'Only notes can be removed from the timeline.');
        abort_unless(static::canRemove($request->user(), $update), 403);

        // A deactivated record's timeline is read-only.
        if (! $inventory->active_yn) {
            return $this->backToTimeline($inventory)->with('error', 'This inventory record is deactivated, so its timeline can no longer be changed.');
        }

        if (! $update->active_yn) {
            return $this->backToTimeline($inventory)->with('error', 'This update has already been removed.');
        }

        $update->active_yn = false;
        $update->save();

        return $this->backToTimeline($inventory)->with('status', 'Update removed.');
    }

    /** A note can be removed by its author or an admin; system entries by no one. */
    public static function canRemove(?User $user, InventoryUpdate $update): bool
    {
        return $update->kind === InventoryUpdate::NOTE
            && $user !== null
            && ($user->isAdmin() || $update->created_by == $user->getKey());
    }

    private function backToTimeline(Inventory $inventory): RedirectResponse
    {
        return redirect()->to(route('inventory.show', $inventory).'#timeline');
    }
}
