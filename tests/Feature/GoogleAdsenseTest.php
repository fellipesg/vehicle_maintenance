<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdSense só no artigo do blog, personalização só com aceite (.ai/rules/config.md).
 */
class GoogleAdsenseTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT = 'ca-pub-1234567890123456';

    private const ARTICLE_SLOT = '1111111111';

    private function enableAdsense(bool $withSlots = true): void
    {
        config([
            'services.google_adsense.client' => self::CLIENT,
            'services.google_adsense.slots.blog_article' => $withSlots ? self::ARTICLE_SLOT : null,
        ]);
    }

    public function test_nothing_is_rendered_without_the_client(): void
    {
        config(['services.google_adsense.client' => null]);
        $post = BlogPost::factory()->create();

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertDontSee('adsbygoogle', false)
            ->assertDontSee('data-ad-consent', false);

        $this->get(route('ads.txt'))->assertNotFound();
    }

    public function test_invalid_client_is_ignored(): void
    {
        config(['services.google_adsense.client' => 'pub-123']);

        $post = BlogPost::factory()->create();

        $this->get(route('blog.show', $post))->assertOk()->assertDontSee('adsbygoogle', false);
        $this->get(route('ads.txt'))->assertNotFound();
    }

    public function test_blog_pages_load_the_script_units_and_hidden_consent_notice(): void
    {
        $this->enableAdsense();
        $post = BlogPost::factory()->create();

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.self::CLIENT, false)
            ->assertSee('requestNonPersonalizedAds = 1', false)
            ->assertSee('navigator.globalPrivacyControl', false)
            ->assertSee('data-ad-slot="'.self::ARTICLE_SLOT.'"', false)
            ->assertSee('<section hidden aria-labelledby="aviso-cookies-titulo"', false)
            ->assertSee('data-ad-consent-choice="granted"', false)
            ->assertSee('data-ad-consent-choice="denied"', false);
    }

    public function test_blog_listing_and_category_pages_have_no_ads_or_consent_notice(): void
    {
        $this->enableAdsense();
        $post = BlogPost::factory()->create();

        $this->assertSame(['blog.show'], \App\Support\GoogleAdsense::ROUTES);

        foreach ([route('blog.index'), route('blog.category', $post->category)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('adsbygoogle', false)
                ->assertDontSee('data-ad-consent', false)
                ->assertDontSee('data-slot="ad-unit"', false);
        }
    }

    public function test_consent_defaults_to_denied_when_analytics_is_off(): void
    {
        $this->enableAdsense();
        $post = BlogPost::factory()->create();
        config(['services.google_analytics.measurement_id' => '']);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee("window.gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied'", false);

        config(['services.google_analytics.measurement_id' => 'G-TEST123456']);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertDontSee("window.gtag('consent', 'default'", false)
            ->assertSee("ad_storage: 'denied'", false);
    }

    public function test_units_need_a_configured_slot(): void
    {
        $this->enableAdsense(withSlots: false);
        $post = BlogPost::factory()->create();

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('adsbygoogle.js', false)
            ->assertDontSee('data-slot="ad-unit"', false);
    }

    public function test_draft_preview_has_no_ad_unit(): void
    {
        $this->enableAdsense();
        $admin = User::factory()->create(['is_admin' => true]);
        $draft = BlogPost::factory()->draft()->create();

        $this->actingAs($admin)
            ->get(route('blog.show', $draft))
            ->assertOk()
            ->assertDontSee('data-ad-slot="'.self::ARTICLE_SLOT.'"', false);
    }

    public function test_pages_outside_the_blog_never_load_ads(): void
    {
        $this->enableAdsense();

        foreach ([route('home'), route('legal.privacy'), route('legal.terms'), route('workshops.landing'), route('about'), route('blog.index'), route('login'), route('verification.lookup')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('adsbygoogle', false)->assertDontSee('data-ad-consent', false);
        }

        $user = User::factory()->create(['user_type' => 'user']);
        $this->actingAs($user)->get(route('vehicle.search'))->assertDontSee('adsbygoogle', false);
    }

    public function test_ads_txt_declares_the_publisher(): void
    {
        $this->enableAdsense();

        $this->get(route('ads.txt'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText('google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0');
    }

    public function test_privacy_policy_discloses_adsense_and_the_consent_cookie(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Google AdSense (anúncios no blog)', false)
            ->assertSee('rl_ads_consent', false)
            ->assertSee('Global Privacy Control', false)
            ->assertDontSee('Não usamos cookies de publicidade', false);
    }
}
