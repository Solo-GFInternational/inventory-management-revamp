<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * Inventory and Stock share one page: a single "Inventory and Stock" menu
 * item, and the two lists as tabs.
 */
class InventoryStockPageTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    public function test_both_tabs_share_the_page_and_mark_the_open_one()
    {
        $user = $this->makeUser('user');

        foreach (['inventory.index' => 'stock.index', 'stock.index' => 'inventory.index'] as $open => $other) {
            $html = $this->actingAs($user)->get(route($open))
                ->assertOk()
                ->assertSee('<title>Inventory and Stock', false)
                ->getContent();

            // Blade's spacing between the attributes varies, so match it loosely.
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route($open), '#').'"\s+aria-current="page"#', $html);
            $this->assertStringContainsString('<a href="'.route($other).'"', $html);
        }
    }

    public function test_menu_has_one_inventory_and_stock_item()
    {
        $user = $this->makeUser('user');

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Inventory and Stock', $html);
        // The old separate Stock menu item is gone; the only link to the stock list is from the tabs.
        $this->assertStringNotContainsString(route('stock.index'), $html);
    }
}
