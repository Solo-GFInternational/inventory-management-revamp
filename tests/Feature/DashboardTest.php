<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user']);
    }

    private function issue(array $attributes = []): Inventory
    {
        return $this->makeInventory($this->user, $attributes + [
            'item' => 'Laptop',
            'taken_by' => 'Jane Staff',
            'department' => 'IT',
            'site' => 'Auckland',
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_renders_for_a_signed_in_user(): void
    {
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Recent issues');
    }

    public function test_stat_cards_count_only_active_rows_as_defined(): void
    {
        Stock::create(['item' => 'Laptop', 'qty' => 7]);
        Stock::create(['item' => 'Mouse', 'qty' => 3]);
        Stock::create(['item' => 'Old', 'qty' => 100])->forceFill(['active_yn' => false])->save();

        // Counted as given out and issued this month.
        $this->issue(['qty' => 2]);
        $this->issue(['qty' => 3, 'item' => 'Mouse']);
        // Used: issued this month, but not "given out".
        $this->issue(['qty' => 5, 'condition' => 'used']);
        // Approved last month: given out, not issued this month.
        $this->issue(['qty' => 11, 'date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString()]);
        // Pending: only counts towards pending approvals.
        $this->issue(['qty' => 40, 'status' => 'pending', 'approval_type' => 'new']);
        $this->issue(['qty' => 50, 'status' => 'pending', 'approval_type' => 'edit']);
        // Deactivated rows never count.
        $this->issue(['qty' => 1000, 'active_yn' => false]);
        $this->issue(['qty' => 1000, 'status' => 'pending', 'active_yn' => false]);

        $response = $this->actingAs($this->user)->get('/dashboard')->assertOk();

        $response->assertViewHas('inStock', fn ($value) => $value == 10);
        $response->assertViewHas('givenOut', fn ($value) => $value == 16);
        $response->assertViewHas('pendingCount', fn ($value) => $value == 2);
        $response->assertViewHas('issuedThisMonth', fn ($value) => $value == 10);
    }

    public function test_item_totals_exclude_pending_and_inactive_rows(): void
    {
        $this->issue(['qty' => 2]);
        $this->issue(['qty' => 4, 'condition' => 'used']);
        $this->issue(['qty' => 30, 'status' => 'pending']);
        $this->issue(['qty' => 500, 'active_yn' => false]);

        $response = $this->actingAs($this->user)->get('/dashboard')->assertOk();

        $response->assertViewHas('items', function ($items) {
            return $items->count() === 1
                && $items->first()->item === 'Laptop'
                && $items->first()->stock_id == Stock::where('item', 'Laptop')->value('id')
                && $items->first()->total_qty == 6;
        });
    }

    public function test_item_totals_can_be_filtered_by_site_and_department(): void
    {
        $this->issue(['qty' => 1, 'site' => 'Auckland', 'department' => 'IT']);
        $this->issue(['qty' => 2, 'site' => 'Auckland', 'department' => 'Finance']);
        $this->issue(['qty' => 4, 'site' => 'Sydney', 'department' => 'IT']);
        $this->issue(['qty' => 8, 'site' => 'Inactive Site', 'department' => 'Gone', 'active_yn' => false]);

        $auckland = $this->typeFor(Type::SITE, 'Auckland')->id;
        $it = $this->typeFor(Type::DEPARTMENT, 'IT')->id;
        Type::whereIn('type_name', ['Inactive Site', 'Gone'])->update(['active_yn' => false]);

        $total = fn ($query) => $this->actingAs($this->user)
            ->get('/dashboard?'.http_build_query($query))
            ->assertOk()
            ->viewData('items')
            ->sum('total_qty');

        $this->assertEquals(7, $total([]));
        $this->assertEquals(3, $total(['site_id' => $auckland]));
        $this->assertEquals(5, $total(['department_id' => $it]));
        $this->assertEquals(1, $total(['site_id' => $auckland, 'department_id' => $it]));

        // Dropdowns list the active managed types, id => name.
        $response = $this->actingAs($this->user)->get('/dashboard');
        $this->assertEquals(['Auckland', 'Sydney'], $response->viewData('sites')->values()->all());
        $this->assertEquals(['Finance', 'IT'], $response->viewData('departments')->values()->all());
        $this->assertEquals($auckland, $response->viewData('sites')->search('Auckland'));
        $response->assertSee('<option value="'.$auckland.'"', false);

        // A deactivated site stays selectable while it is the active filter.
        $inactive = Type::where('type_name', 'Inactive Site')->value('id');
        $this->assertContains('Inactive Site', $this->actingAs($this->user)
            ->get('/dashboard?site_id='.$inactive)->viewData('sites')->all());
    }

    public function test_recent_issues_lists_the_latest_five_active_rows(): void
    {
        foreach (range(1, 6) as $day) {
            $this->issue(['item' => "Item {$day}", 'date' => now()->startOfMonth()->addDays($day - 1)->toDateString()]);
        }
        $this->issue(['item' => 'Hidden', 'date' => now()->addYear()->toDateString(), 'active_yn' => false]);

        $response = $this->actingAs($this->user)->get('/dashboard')->assertOk();

        $this->assertEquals(
            ['Item 6', 'Item 5', 'Item 4', 'Item 3', 'Item 2'],
            $response->viewData('recent')->pluck('stock.item')->all(),
        );
        $response->assertSee('IT · Auckland');
    }

    public function test_only_admins_get_the_link_to_the_approvals_page(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertDontSee('Review approvals');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertSee('Review approvals')
            ->assertSee(route('inventory.pending'), false);
    }
}
