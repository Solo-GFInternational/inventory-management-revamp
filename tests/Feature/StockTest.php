<?php

namespace Tests\Feature;

use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class StockTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    public function test_index_shows_in_stock_given_out_and_total()
    {
        $user = $this->makeUser('user');
        $bag = Stock::create(['item' => 'Laptop Bag', 'qty' => 5]);
        $mouse = Stock::create(['item' => 'Mouse', 'qty' => 0]);
        $retired = Stock::create(['item' => 'Retired Thing', 'qty' => 9]);
        $retired->forceFill(['active_yn' => false])->save();

        // Counted: approved, new, active.
        $this->makeInventory($user, ['qty' => 2]);
        $this->makeInventory($user, ['qty' => 1]);
        $this->makeInventory($user, ['item' => 'Mouse', 'qty' => 4]);
        // Not counted: pending, used, inactive.
        $this->makeInventory($user, ['qty' => 10, 'status' => 'pending', 'approval_type' => 'new']);
        $this->makeInventory($user, ['qty' => 20, 'condition' => 'used']);
        $this->makeInventory($user, ['qty' => 40, 'active_yn' => false]);

        $stocks = Stock::active()->withGivenOut()->orderBy('inventory.stocks.item')->get()->keyBy('item');
        $this->assertCount(2, $stocks);
        $this->assertEquals([5, 3, 8], [$stocks['Laptop Bag']->inStock(), $stocks['Laptop Bag']->givenOut(), $stocks['Laptop Bag']->total()]);
        $this->assertEquals([0, 4, 4], [$stocks['Mouse']->inStock(), $stocks['Mouse']->givenOut(), $stocks['Mouse']->total()]);

        $response = $this->actingAs($user)->get('/stock');

        $response->assertOk()
            ->assertViewHas('totals', ['inStock' => 5, 'givenOut' => 7, 'total' => 12])
            ->assertSee('Laptop Bag')
            ->assertSee('Mouse')
            ->assertDontSee('Retired Thing')
            // Non-admins get no adjust action.
            ->assertDontSee('adjust-stock-'.$bag->id);

        $this->actingAs($user)->get('/stock?q=mou')
            ->assertOk()
            ->assertSee('Mouse')
            ->assertViewHas('stocks', fn ($stocks) => $stocks->pluck('item')->all() === ['Mouse'])
            ->assertViewHas('totals', ['inStock' => 5, 'givenOut' => 7, 'total' => 12]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->get('/stock')->assertOk()->assertSee('adjust-stock-'.$mouse->id);
    }

    public function test_given_out_follows_the_stock_row_not_the_name()
    {
        $user = $this->makeUser('user');
        $old = Stock::create(['item' => 'Headset', 'qty' => 0]);
        $old->forceFill(['active_yn' => false])->save();
        $this->makeInventory($user, ['stock_id' => $old->id, 'qty' => 6]);

        // A new active row with the same name starts with nothing given out.
        $new = Stock::create(['item' => 'Headset', 'qty' => 2]);
        $this->makeInventory($user, ['stock_id' => $new->id, 'qty' => 1]);

        $givenOut = Stock::withGivenOut()->get()->keyBy('id');
        $this->assertEquals(6, $givenOut[$old->id]->givenOut());
        $this->assertEquals(1, $givenOut[$new->id]->givenOut());
        $this->assertEquals(6, $old->inventories()->sum('qty'));
    }

    public function test_add_stock_creates_a_new_item()
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)
            ->post('/stock', ['new_item' => 'USB-C Dock', 'qty' => 3, '_modal' => 'add-stock'])
            ->assertRedirect('/stock')
            ->assertSessionHas('status');

        $stock = Stock::where('item', 'USB-C Dock')->first();
        $this->assertNotNull($stock);
        $this->assertEquals(3, $stock->qty);
        $this->assertEquals($user->id, $stock->created_by);
    }

    public function test_add_stock_increments_an_existing_item()
    {
        $user = $this->makeUser('user');
        Stock::create(['item' => 'Mouse', 'qty' => 2]);

        $this->actingAs($user)->post('/stock', ['item' => 'Mouse', 'qty' => 5])->assertRedirect('/stock');
        // A typed name matching an existing item adds to it rather than duplicating.
        $this->actingAs($user)->post('/stock', ['new_item' => 'Mouse', 'qty' => 1])->assertRedirect('/stock');

        $this->assertEquals(1, Stock::where('item', 'Mouse')->count());
        $this->assertEquals(8, Stock::where('item', 'Mouse')->value('qty'));
    }

    public function test_add_stock_reactivates_a_deactivated_item()
    {
        $user = $this->makeUser('user');
        $stock = Stock::create(['item' => 'Old Phone', 'qty' => 1]);
        $stock->forceFill(['active_yn' => false])->save();

        $this->actingAs($user)->post('/stock', ['new_item' => 'Old Phone', 'qty' => 2])->assertRedirect('/stock');

        $stock->refresh();
        $this->assertTrue($stock->active_yn);
        $this->assertEquals(3, $stock->qty);
    }

    public function test_add_stock_validates_input()
    {
        $user = $this->makeUser('user');
        Stock::create(['item' => 'Mouse', 'qty' => 2]);

        $this->actingAs($user)->from('/stock')
            ->post('/stock', ['qty' => 0, '_modal' => 'add-stock'])
            ->assertRedirect('/stock')
            ->assertSessionHasErrors(['item', 'new_item', 'qty']);

        $this->actingAs($user)->from('/stock')
            ->post('/stock', ['item' => 'Mouse', 'new_item' => 'Keyboard', 'qty' => 1])
            ->assertSessionHasErrors('item');

        $this->actingAs($user)->from('/stock')
            ->post('/stock', ['item' => 'Not In The List', 'qty' => 1])
            ->assertSessionHasErrors('item');

        $this->actingAs($user)->from('/stock')
            ->post('/stock', ['new_item' => 'Keyboard', 'qty' => 1.5])
            ->assertSessionHasErrors('qty');

        $this->assertEquals(2, Stock::where('item', 'Mouse')->value('qty'));
        $this->assertFalse(Stock::where('item', 'Keyboard')->exists());
    }

    public function test_admin_can_adjust_the_in_stock_count()
    {
        $admin = $this->makeUser('admin');
        $stock = Stock::create(['item' => 'Mouse', 'qty' => 7]);

        $this->actingAs($admin)
            ->put("/stock/{$stock->id}", ['qty' => 0, 'note' => 'Recount'])
            ->assertRedirect('/stock')
            ->assertSessionHas('status');

        $stock->refresh();
        $this->assertEquals(0, $stock->qty);
        $this->assertEquals($admin->id, $stock->updated_by);
    }

    public function test_adjust_rejects_negative_or_non_integer_counts()
    {
        $admin = $this->makeUser('admin');
        $stock = Stock::create(['item' => 'Mouse', 'qty' => 7]);

        foreach ([-1, 'abc', 2.5, null] as $qty) {
            $this->actingAs($admin)->from('/stock')
                ->put("/stock/{$stock->id}", ['qty' => $qty, '_modal' => 'adjust-stock-'.$stock->id])
                ->assertRedirect('/stock')
                ->assertSessionHasErrors('qty');
        }

        $this->assertEquals(7, $stock->fresh()->qty);
    }

    public function test_non_admins_cannot_adjust_stock()
    {
        $user = $this->makeUser('user');
        $stock = Stock::create(['item' => 'Mouse', 'qty' => 7]);

        $this->actingAs($user)->put("/stock/{$stock->id}", ['qty' => 1])->assertForbidden();

        $this->assertEquals(7, $stock->fresh()->qty);
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/stock')->assertRedirect('/login');
        $this->post('/stock', ['new_item' => 'X', 'qty' => 1])->assertRedirect('/login');
    }
}
