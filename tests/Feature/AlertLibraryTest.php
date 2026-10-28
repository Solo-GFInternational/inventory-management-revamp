<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * The Inventory and Stock pages trial TallStackUI dialogs (and so load Livewire, which
 * brings Alpine; its script tag isn't rendered under test); every other
 * page keeps SweetAlert.
 */
class AlertLibraryTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    public function test_inventory_pages_use_tallstackui_dialogs()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);

        foreach ([route('inventory.index'), route('stock.index'), route('inventory.show', $inventory), route('inventory.pending')] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('/tallstackui/script/', false)
                ->assertSee('tallstackui_dialog', false)
                ->assertSee('tallstackui-alerts', false)
                ->assertDontSee('sweetalert2', false);
        }
    }

    public function test_other_pages_keep_sweetalert()
    {
        $admin = $this->makeUser('admin');

        foreach ([route('dashboard'), route('scanner.index'), route('users.index')] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('sweetalert2', false)
                ->assertDontSee('/tallstackui/script/', false)
                ->assertDontSee('tallstackui-alerts', false);
        }
    }
}
