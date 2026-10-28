<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Stock;
use App\Services\BarcodeLabel;
use App\Support\AuditTrailEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * Every inventory record gets a Code 128 barcode (INV + 6-digit id) and a
 * label JPEG on the local disk; the details page is where a scan leads.
 */
class InventoryBarcodeTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    public function test_barcode_value_is_inv_and_the_padded_id()
    {
        $this->assertSame('INV000123', BarcodeLabel::valueFor(123));
        $this->assertSame('INV1234567', BarcodeLabel::valueFor(1234567));
    }

    public function test_created_rows_get_a_barcode_and_label_file()
    {
        Storage::fake('local');
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Monitor', 'qty' => 5]);

        $this->actingAs($admin)->post('/inventory', $this->withIds([
            'date' => now()->toDateString(),
            'item' => 'Monitor',
            'condition' => 'new',
            'site' => 'Dry Goods',
        ]) + ['people' => [
            $this->withIds(['taken_by' => 'Ana', 'department' => 'Finance']),
            $this->withIds(['taken_by' => 'Ben', 'department' => 'Finance']),
        ]])->assertSessionHasNoErrors();

        foreach (Inventory::all() as $inventory) {
            $this->assertSame(BarcodeLabel::valueFor($inventory->id), $inventory->barcode);
            $this->assertSame("barcodes/{$inventory->barcode}.jpg", $inventory->barcodePath());
            Storage::disk('local')->assertExists($inventory->barcodePath());

            $size = getimagesizefromstring(Storage::disk('local')->get($inventory->barcodePath()));
            $this->assertSame('image/jpeg', $size['mime']);
            $this->assertGreaterThanOrEqual(400, $size[0]);
            $this->assertGreaterThanOrEqual(200, $size[1]);
        }
    }

    public function test_rows_created_directly_get_a_barcode_too()
    {
        $inventory = $this->makeInventory($this->makeUser('admin'));

        $this->assertSame(BarcodeLabel::valueFor($inventory->id), $inventory->fresh()->barcode);
        Storage::disk('local')->assertExists($inventory->barcodePath());
        // Assigning it isn't an audit-trail change of its own.
        $this->assertSame(0, AuditTrailEntry::query()->where('subject_type', $inventory->getMorphClass())->where('event', 'updated')->count());
    }

    public function test_barcode_downloads_as_a_jpeg_for_any_signed_in_user()
    {
        $inventory = $this->makeInventory($this->makeUser('admin'));

        $this->get(route('inventory.barcode', $inventory))->assertRedirect('/login');

        $this->actingAs($this->makeUser('user'))->get(route('inventory.barcode', $inventory))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertDownload($inventory->barcode.'.jpg');

        $inline = $this->actingAs($this->makeUser('user'))->get(route('inventory.barcode', ['inventory' => $inventory, 'inline' => 1]));
        $inline->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringStartsWith('inline', $inline->headers->get('Content-Disposition'));
    }

    public function test_a_missing_label_file_is_regenerated()
    {
        $inventory = $this->makeInventory($this->makeUser('admin'))->fresh();
        Storage::disk('local')->delete($inventory->barcodePath());

        $this->actingAs($this->makeUser('user'))->get(route('inventory.barcode', $inventory))->assertOk();

        Storage::disk('local')->assertExists($inventory->barcodePath());
    }

    public function test_backfill_command_assigns_missing_barcodes()
    {
        $inventory = $this->makeInventory($this->makeUser('admin'));
        // As if created before barcodes existed.
        Inventory::whereKey($inventory->id)->update(['barcode' => null]);
        Storage::disk('local')->deleteDirectory('barcodes');

        Artisan::call('inventory:barcodes');

        $inventory->refresh();
        $this->assertSame(BarcodeLabel::valueFor($inventory->id), $inventory->barcode);
        Storage::disk('local')->assertExists($inventory->barcodePath());
    }

    public function test_show_page_renders_for_users_and_admins()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin, [
            'item' => 'Monitor', 'taken_by' => 'Ana Example', 'serial_number' => 'SN-777', 'site' => 'Lautoka',
        ])->fresh();

        foreach ([$this->makeUser('user'), $admin] as $viewer) {
            $this->actingAs($viewer)->get(route('inventory.show', $inventory))
                ->assertOk()
                ->assertSee($inventory->barcode)
                ->assertSee('Monitor')
                ->assertSee('Ana Example')
                ->assertSee('SN-777')
                ->assertSee('Finance')
                ->assertSee('Lautoka')
                ->assertSee($admin->name)
                ->assertSee('Approved')
                ->assertSee(route('inventory.barcode', ['inventory' => $inventory, 'inline' => 1]), false)
                ->assertSee('Download barcode')
                ->assertSee('Back to inventory')
                ->assertDontSee('Deactivated');
        }
    }

    public function test_show_page_still_renders_a_deactivated_record()
    {
        $admin = $this->makeUser('admin');
        $inventory = $this->makeInventory($admin);
        $inventory->forceFill(['active_yn' => false])->save();

        $this->actingAs($this->makeUser('user'))->get(route('inventory.show', $inventory))
            ->assertOk()
            ->assertSee('Deactivated');
    }

    public function test_inventory_list_links_to_details_and_edit_modal_offers_the_barcode()
    {
        $user = $this->makeUser('user');
        $inventory = $this->makeInventory($user)->fresh();

        $this->actingAs($user)->get('/inventory')->assertOk()
            ->assertSee(route('inventory.show', $inventory), false)
            ->assertSee(route('inventory.barcode', $inventory), false)
            ->assertSee($inventory->barcode);
    }
}
