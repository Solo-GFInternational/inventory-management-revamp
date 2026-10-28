<?php

namespace Tests\Feature;

use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

/**
 * Every form that changes data carries data-processing (the "Processing…"
 * SweetAlert in resources/js/app.js); GET filters, the PDF download and
 * sign out don't.
 */
class ProcessingAlertTest extends TestCase
{
    use CreatesInventory, RefreshDatabase;

    /**
     * The opening <form> tags on the page.
     *
     * @return list<string>
     */
    private function forms(string $html): array
    {
        preg_match_all('/<form\b[^>]*>/is', $html, $matches);

        return $matches[0];
    }

    /**
     * POST forms (except sign out) must have data-processing; GET forms and
     * sign out must not. Returns the POST forms' tags.
     *
     * @return list<string>
     */
    private function assertProcessingOnChangingFormsOnly(string $html): array
    {
        $posts = [];

        foreach ($this->forms($html) as $tag) {
            $isGet = (bool) preg_match('/method="GET"/i', $tag);
            $isLogout = str_contains($tag, 'action="'.route('logout').'"');

            if ($isGet || $isLogout) {
                $this->assertStringNotContainsString('data-processing', $tag, "Unexpected spinner on: {$tag}");
            } else {
                $this->assertStringContainsString('data-processing=', $tag, "Missing spinner on: {$tag}");
                $posts[] = $tag;
            }
        }

        return $posts;
    }

    public function test_inventory_page_forms()
    {
        $admin = $this->makeUser('admin');
        $this->makeInventory($admin);
        $this->makeInventory($admin, ['status' => 'pending', 'approval_type' => 'new']);

        $html = $this->actingAs($admin)->get(route('inventory.index'))->assertOk()->getContent();
        $posts = $this->assertProcessingOnChangingFormsOnly($html);

        $this->assertNotEmpty($posts);
        $this->assertStringContainsString('data-processing="Saving inventory…"', $html);
        $this->assertStringContainsString('data-processing="Saving changes…"', $html);
        $this->assertStringContainsString('data-processing="Approving…"', $html);
        $this->assertStringContainsString('data-processing="Deactivating…"', $html);

        // The PDF download and the filters are GET forms without it.
        $pdf = collect($this->forms($html))->first(fn ($tag) => str_contains($tag, route('inventory.download.pdf')));
        $this->assertNotNull($pdf);
        $this->assertStringNotContainsString('data-processing', $pdf);
    }

    public function test_inventory_forms_for_a_user_say_submitting_for_approval()
    {
        $user = $this->makeUser('user');
        $this->makeInventory($user);

        $html = $this->actingAs($user)->get(route('inventory.index'))->assertOk()->getContent();

        $this->assertProcessingOnChangingFormsOnly($html);
        $this->assertStringContainsString('data-processing="Submitting for approval…"', $html);
    }

    public function test_pending_page_forms()
    {
        $admin = $this->makeUser('admin');
        $this->makeInventory($admin, ['status' => 'pending', 'approval_type' => 'new']);

        $html = $this->actingAs($admin)->get(route('inventory.pending'))->assertOk()->getContent();

        $this->assertNotEmpty($this->assertProcessingOnChangingFormsOnly($html));
        $this->assertStringContainsString('data-processing="Approving…"', $html);
    }

    public function test_stock_page_forms()
    {
        $admin = $this->makeUser('admin');
        Stock::create(['item' => 'Laptop Bag', 'qty' => 5]);

        $html = $this->actingAs($admin)->get(route('stock.index'))->assertOk()->getContent();

        $this->assertProcessingOnChangingFormsOnly($html);
        $this->assertStringContainsString('data-processing="Adding stock…"', $html);
        $this->assertStringContainsString('data-processing="Saving the stock count…"', $html);
    }

    public function test_users_page_forms()
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('user');
        $gone = $this->makeUser('user');
        $gone->forceFill(['active_yn' => false])->save();

        $html = $this->actingAs($admin)->get(route('users.index', ['status' => 'all']))->assertOk()->getContent();

        $this->assertProcessingOnChangingFormsOnly($html);
        $this->assertStringContainsString('data-processing="Creating the account…" data-processing-text="Emailing their sign-in details — this can take a moment."', $html);
        $this->assertStringContainsString('data-processing="Saving changes…"', $html);
        $this->assertStringContainsString('data-processing="Deactivating…"', $html);
        $this->assertStringContainsString('data-processing="Reactivating…"', $html);
    }

    public function test_profile_page_forms()
    {
        $html = $this->actingAs($this->makeUser('user'))->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertProcessingOnChangingFormsOnly($html);
        $this->assertStringContainsString('data-processing="Saving your profile…"', $html);
        $this->assertStringContainsString('data-processing="Updating your password…"', $html);
    }

    public function test_dashboard_filter_and_sign_in_have_no_spinner()
    {
        $html = $this->actingAs($this->makeUser('user'))->get(route('dashboard'))->assertOk()->getContent();
        $this->assertProcessingOnChangingFormsOnly($html);

        $this->app['auth']->forgetGuards();
        $login = $this->get('/login')->assertOk()->getContent();

        foreach ($this->forms($login) as $tag) {
            $this->assertStringNotContainsString('data-processing', $tag);
        }
    }
}
