<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * Every page uses SweetAlert. The Inventory and Stock pages trialled
 * TallStackUI dialogs and went back; a page can still opt in with
 *
 * @section('alerts', 'tallstackui').
 */
class AlertLibraryTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    public function test_pages_use_sweetalert()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);

        foreach ([
            route('dashboard'), route('inventory.index'), route('stock.index'), route('inventory.show', $inventory),
            route('inventory.pending'), route('scanner.index'), route('users.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('sweetalert2', false)
                ->assertDontSee('/tallstackui/script/', false)
                ->assertDontSee('tallstackui-alerts', false);
        }
    }
}
