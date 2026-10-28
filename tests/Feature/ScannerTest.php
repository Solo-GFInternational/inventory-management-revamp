<?php

namespace Tests\Feature;

use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * The barcode scanner page and its lookup (ScannerController). Decoding
 * happens in the browser, so these cover the page and where a code leads.
 */
class ScannerTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    /**
     * An inventory row with this barcode, force-filled with the rest of the
     * row (so it doesn't depend on how the model generates barcodes).
     */
    private function inventoryWithBarcode(string $barcode, array $overrides = []): Inventory
    {
        return $this->makeInventory($this->makeUser('admin'), ['barcode' => $barcode] + $overrides);
    }

    public function test_scanner_page_requires_sign_in()
    {
        $this->get(route('scanner.index'))->assertRedirect(route('login'));
        $this->get(route('scanner.lookup', ['code' => 'INV000001']))->assertRedirect(route('login'));
    }

    public function test_user_and_admin_see_the_scanner_page()
    {
        foreach (['user', 'admin'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get(route('scanner.index'))
                ->assertOk()
                ->assertSee('Barcode scanner')
                ->assertSee('Scan barcode')
                ->assertSee('data-modal-open="scanner-modal"', false)
                // The manual-entry fallback: a GET form to the lookup.
                ->assertSee('action="'.route('scanner.lookup').'"', false)
                ->assertSee('name="code"', false);
        }
    }

    public function test_nav_shows_scanner_for_every_signed_in_user()
    {
        foreach (['user', 'admin'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('href="'.route('scanner.index').'"', false)
                ->assertSee('Scanner');
        }
    }

    public function test_known_barcode_redirects_to_the_record()
    {
        $inventory = $this->inventoryWithBarcode('INV000123');

        $this->actingAs($this->makeUser('user'))
            ->get(route('scanner.lookup', ['code' => 'INV000123']))
            ->assertRedirect(route('inventory.show', $inventory));
    }

    public function test_lowercase_and_padded_input_still_matches()
    {
        $inventory = $this->inventoryWithBarcode('INV000123');

        $this->actingAs($this->makeUser('user'))
            ->get(route('scanner.lookup', ['code' => '  inv000123 ']))
            ->assertRedirect(route('inventory.show', $inventory));
    }

    public function test_unknown_barcode_goes_back_to_the_scanner_with_an_error()
    {
        $this->inventoryWithBarcode('INV000123');

        $this->actingAs($this->makeUser('user'))
            ->get(route('scanner.lookup', ['code' => 'inv999999']))
            ->assertRedirect(route('scanner.index'))
            ->assertSessionHas('error', 'No inventory record has barcode INV999999.');
    }

    public function test_deactivated_record_is_still_found()
    {
        $inventory = $this->inventoryWithBarcode('INV000123');
        $inventory->forceFill(['active_yn' => false])->save();

        $this->actingAs($this->makeUser('user'))
            ->get(route('scanner.lookup', ['code' => 'INV000123']))
            ->assertRedirect(route('inventory.show', $inventory));
    }

    public function test_code_is_required_and_limited_in_length()
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)
            ->from(route('scanner.index'))
            ->get(route('scanner.lookup'))
            ->assertRedirect(route('scanner.index'))
            ->assertSessionHasErrors('code');

        $this->actingAs($user)
            ->from(route('scanner.index'))
            ->get(route('scanner.lookup', ['code' => str_repeat('A', 33)]))
            ->assertRedirect(route('scanner.index'))
            ->assertSessionHasErrors('code');
    }

    public function test_scanner_page_forms_have_no_processing_spinner()
    {
        $html = $this->actingAs($this->makeUser('user'))->get(route('scanner.index'))->getContent();

        preg_match('/<form\b[^>]*action="'.preg_quote(route('scanner.lookup'), '/').'"[^>]*>/is', $html, $match);

        $this->assertNotEmpty($match);
        $this->assertStringContainsString('method="GET"', $match[0]);
        $this->assertStringNotContainsString('data-processing', $match[0]);
    }
}
