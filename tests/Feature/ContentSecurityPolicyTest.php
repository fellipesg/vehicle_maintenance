<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Security\ContentSecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * O Content-Security-Policy das páginas HTML (App\Http\Middleware\SetSecurityHeaders e
 * App\Support\Security\ContentSecurityPolicy).
 */
class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_page_carries_the_policy_in_report_only_by_default(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeaderMissing('Content-Security-Policy');

        $policy = $this->policyOf($response);

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
    }

    public function test_enforcing_the_policy_is_one_config_line(): void
    {
        config(['security.csp.enforce' => true]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_policy_can_be_switched_off_entirely(): void
    {
        config(['security.csp.enabled' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_script_src_takes_the_nonce_and_never_unsafe_inline(): void
    {
        $response = $this->get(route('home'));

        $policy = $this->policyOf($response);
        $scriptSrc = $this->directiveOf($policy, 'script-src');

        $this->assertStringContainsString("'nonce-", $scriptSrc);
        $this->assertStringContainsString("'strict-dynamic'", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-eval'", $scriptSrc);
    }

    public function test_nonce_of_the_header_is_the_nonce_of_the_inline_scripts(): void
    {
        $response = $this->get(route('home'));

        $nonce = $this->nonceOf($response);

        $this->assertNotSame('', $nonce);

        // O shell (layouts/partials/shell-script.blade.php) é inline em toda página.
        $response->assertSee('<script nonce="'.$nonce.'">', false);
    }

    public function test_nonce_changes_on_every_request(): void
    {
        $first = $this->nonceOf($this->get(route('home')));
        $second = $this->nonceOf($this->get(route('home')));

        $this->assertNotSame($first, $second);
        $this->assertSame(32, strlen($first));
    }

    public function test_inline_script_without_the_nonce_of_the_request_is_not_covered(): void
    {
        $response = $this->get(route('home'));

        // Um `<script>` cru — o que uma injeção produziria — não aparece na página: todo bloco
        // inline sai com o atributo nonce, e sem ele o navegador recusa executar.
        $response->assertDontSee('<script>', false);
    }

    public function test_turnstile_is_allowed_as_script_and_as_frame(): void
    {
        $response = $this->get(route('home'));
        $policy = $this->policyOf($response);

        $this->assertStringContainsString(
            'https://challenges.cloudflare.com',
            $this->directiveOf($policy, 'script-src'),
        );
        $this->assertStringContainsString(
            'https://challenges.cloudflare.com',
            $this->directiveOf($policy, 'frame-src'),
        );
    }

    public function test_fonts_and_analytics_hosts_are_allowed(): void
    {
        $policy = $this->policyOf($this->get(route('home')));

        $this->assertStringContainsString('https://fonts.bunny.net', $this->directiveOf($policy, 'style-src'));
        $this->assertStringContainsString('https://fonts.bunny.net', $this->directiveOf($policy, 'font-src'));
        $this->assertStringContainsString('https://www.googletagmanager.com', $this->directiveOf($policy, 'script-src'));
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $this->directiveOf($policy, 'script-src'));
    }

    public function test_page_without_ads_keeps_the_closed_list(): void
    {
        $policy = $this->policyOf($this->get(route('home')));

        $this->assertNotContains('https:', $this->sourcesOf($policy, 'img-src'));
        $this->assertNotContains('https:', $this->sourcesOf($policy, 'frame-src'));
        $this->assertNotContains('https:', $this->sourcesOf($policy, 'connect-src'));
        $this->assertContains("'self'", $this->sourcesOf($policy, 'img-src'));
    }

    public function test_page_with_ads_gets_the_wider_policy_for_creatives(): void
    {
        config(['services.google_adsense.client' => 'ca-pub-1234567890123456']);
        $article = BlogPost::factory()->create();

        $response = $this->get(route('blog.show', $article));

        // Sem isto um 500 passaria por política fechada e a falha apontaria para o lugar errado.
        $response->assertOk();

        $policy = $this->policyOf($response);

        // Criativo e iframe de anúncio vêm de domínio que não se enumera.
        $this->assertContains('https:', $this->sourcesOf($policy, 'img-src'));
        $this->assertContains('https:', $this->sourcesOf($policy, 'frame-src'));

        // Mesmo lá, script inline continua preso ao nonce.
        $scriptSrc = $this->directiveOf($policy, 'script-src');
        $this->assertStringContainsString("'nonce-", $scriptSrc);
        $this->assertStringContainsString("'strict-dynamic'", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
    }

    public function test_article_without_adsense_configured_keeps_the_closed_list(): void
    {
        config(['services.google_adsense.client' => null]);
        $article = BlogPost::factory()->create();

        $policy = $this->policyOf($this->get(route('blog.show', $article)));

        $this->assertNotContains('https:', $this->sourcesOf($policy, 'img-src'));
    }

    /**
     * O perfil largo segue o anúncio, não a seção: a listagem do blog não carrega AdSense
     * (GoogleAdsense::ROUTES, hoje só blog.show) e fica fechada mesmo com o cliente configurado.
     */
    public function test_blog_listing_has_no_ads_and_keeps_the_closed_list(): void
    {
        config(['services.google_adsense.client' => 'ca-pub-1234567890123456']);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $policy = $this->policyOf($response);

        $this->assertNotContains('https:', $this->sourcesOf($policy, 'img-src'));
        $this->assertNotContains('https:', $this->sourcesOf($policy, 'frame-src'));
    }

    public function test_authenticated_portal_page_gets_the_closed_list(): void
    {
        $owner = User::factory()->asUser()->create();

        $response = $this->actingAs($owner)->get(route('user.dashboard'));

        $response->assertOk();
        $policy = $this->policyOf($response);

        $this->assertNotContains('https:', $this->sourcesOf($policy, 'img-src'));
        $this->assertNotContains("'unsafe-inline'", $this->sourcesOf($policy, 'script-src'));
        $response->assertSee('<script nonce="'.$this->nonceOf($response).'">', false);
    }

    public function test_report_destination_is_announced_in_both_formats(): void
    {
        $response = $this->get(route('home'));
        $policy = $this->policyOf($response);

        $this->assertStringContainsString('report-uri '.route('csp-report'), $policy);
        $this->assertStringContainsString('report-to '.ContentSecurityPolicy::REPORTING_ENDPOINT, $policy);
        $this->assertSame(
            ContentSecurityPolicy::REPORTING_ENDPOINT.'="'.route('csp-report').'"',
            $response->headers->get('Reporting-Endpoints'),
        );
    }

    public function test_configured_report_uri_replaces_the_local_route(): void
    {
        config(['security.csp.report_uri' => 'https://o1.ingest.sentry.io/api/2/security/?sentry_key=abc']);

        $response = $this->get(route('home'));

        $this->assertStringContainsString(
            'report-uri https://o1.ingest.sentry.io/api/2/security/?sentry_key=abc',
            $this->policyOf($response),
        );
    }

    public function test_extra_hosts_from_config_are_merged_into_the_directive(): void
    {
        config(['security.csp.extra_hosts' => ['img-src' => ['https://exemplo.test']]]);

        $policy = $this->policyOf($this->get(route('home')));

        $this->assertStringContainsString('https://exemplo.test', $this->directiveOf($policy, 'img-src'));
    }

    public function test_json_responses_do_not_carry_the_page_policy(): void
    {
        $response = $this->getJson('/api/v1/workshops');

        $response->assertOk();
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * A resposta de arquivo do cliente manda o próprio CSP, bem mais apertado. O middleware não
     * pode trocá-lo pelo da página, que libera script com nonce.
     */
    public function test_invoice_download_keeps_its_own_sandbox_policy(): void
    {
        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $owner->vehicles()->attach($vehicle->id, ['terms_version' => config('legal.terms_version')]);

        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->fresh()->tenant_id,
        ]);

        Storage::disk('public')->put('invoices/nota-armazenada.pdf', '%PDF-1.4 teste');

        $invoice = Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => 'invoices/nota-armazenada.pdf',
            'file_name' => 'nota_fiscal.pdf',
        ]);

        $response = $this->actingAs($owner)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        );
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_report_route_logs_a_report_uri_payload(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Violação de Content-Security-Policy'
                    && $context['directive'] === 'script-src-elem'
                    && $context['blocked_uri'] === 'https://evil.test/x.js';
            });

        $this->postJson(route('csp-report'), [
            'csp-report' => [
                'document-uri' => 'https://revisalog.com.br/blog',
                'effective-directive' => 'script-src-elem',
                'blocked-uri' => 'https://evil.test/x.js',
                'disposition' => 'report',
            ],
        ])->assertNoContent();
    }

    public function test_report_route_logs_a_reporting_api_payload(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['blocked_uri'] === 'https://evil.test/y.js'
                && $context['directive'] === 'script-src-elem');

        $this->call(
            'POST',
            route('csp-report'),
            server: ['CONTENT_TYPE' => 'application/reports+json'],
            content: json_encode([[
                'type' => 'csp-violation',
                'body' => [
                    'documentURL' => 'https://revisalog.com.br/blog',
                    'effectiveDirective' => 'script-src-elem',
                    'blockedURL' => 'https://evil.test/y.js',
                ],
            ]]),
        )->assertNoContent();
    }

    public function test_report_route_ignores_browser_extension_noise(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->never();

        $this->postJson(route('csp-report'), [
            'csp-report' => [
                'effective-directive' => 'script-src-elem',
                'blocked-uri' => 'chrome-extension://abcdef/inject.js',
            ],
        ])->assertNoContent();
    }

    public function test_report_route_survives_a_body_that_is_not_a_report(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->never();

        $this->call('POST', route('csp-report'), content: 'nao e json')->assertNoContent();
    }

    private function policyOf(TestResponse $response): string
    {
        return (string) $response->headers->get('Content-Security-Policy-Report-Only');
    }

    private function nonceOf(TestResponse $response): string
    {
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $this->policyOf($response), $matches);

        return $matches[1] ?? '';
    }

    /**
     * Isola uma diretiva da política, para afirmar sobre ela sem casar com outra.
     */
    private function directiveOf(string $policy, string $directive): string
    {
        foreach (explode('; ', $policy) as $part) {
            if (str_starts_with($part, $directive.' ')) {
                return $part;
            }
        }

        return '';
    }

    /**
     * Fontes de uma diretiva, uma a uma. `https:` sozinho é o curinga de esquema; como texto ele
     * casaria com qualquer host https da lista, então a afirmação tem de ser pelo item.
     *
     * @return list<string>
     */
    private function sourcesOf(string $policy, string $directive): array
    {
        $parts = explode(' ', $this->directiveOf($policy, $directive));

        return array_values(array_slice($parts, 1));
    }
}
