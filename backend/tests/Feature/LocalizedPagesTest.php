<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizedPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_english_is_the_default_language_at_the_root(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Codenegar')
            ->assertSee('Plus Jakarta Sans', false)
            ->assertSee('<link rel="alternate" hreflang="fa" href="'.url('/fa').'">', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false);
    }

    public function test_persian_is_served_under_its_prefix(): void
    {
        $this->get('/fa')->assertOk()
            ->assertSee('<html lang="fa" dir="rtl">', false)
            ->assertSee('کدنگار')
            ->assertSee('Vazirmatn FD NL', false)
            ->assertSee('<link rel="canonical" href="'.url('/fa').'">', false);
    }

    public function test_default_language_prefix_redirects_and_unknown_languages_404(): void
    {
        $this->get('/en')->assertRedirect('/')->assertStatus(301);
        $this->get('/de')->assertNotFound();
    }

    public function test_missing_translation_falls_back_to_default_language(): void
    {
        $site = app(\App\Services\SiteContent::class)->get();
        $site['services'][0]['title'] = ['en' => 'Only English'];
        app(\App\Services\SiteContent::class)->sync($site);

        $this->get('/fa')->assertSee('Only English');
    }

    public function test_sitemap_lists_every_language(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(url('/fa'), false);
    }

    public function test_admin_panel_is_served(): void
    {
        $this->get('/admin')->assertOk()->assertSee('noindex', false);
    }
}
