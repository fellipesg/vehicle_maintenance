<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use App\Support\Seo\BlogArticle;
use Database\Seeders\AeoBlogPostsSeeder;
use Database\Seeders\BlogSeeder;
use Database\Seeders\BlogSeriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AeoReinforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_posts_gain_the_new_copy_without_a_new_publish_date(): void
    {
        $this->seed(BlogSeeder::class);

        $sale = BlogPost::query()->where('slug', 'vender-o-carro-com-historico-de-manutencao')->firstOrFail();
        $publishedAt = $sale->published_at?->clone();

        $this->seed(AeoBlogPostsSeeder::class);
        $sale->refresh();

        $this->assertTrue($publishedAt?->equalTo($sale->published_at));
        $this->assertStringContainsString('Exportar PDF', $sale->content);
        $this->assertStringContainsString('Baixar PDF', $sale->content);
        $this->assertStringNotContainsString('%', $sale->content);
        $words = BlogArticle::wordCount((string) BlogArticle::split($sale->content)['lead']);
        $this->assertGreaterThanOrEqual(40, $words);
        $this->assertLessThanOrEqual(60, $words);

        $response = $this->get(route('blog.show', $sale))->assertOk();
        $response->assertSee('Exportar PDF', false);
        $response->assertSee('Ter histórico valoriza o carro?', false);
        $response->assertSee('"@type":"FAQPage"', false);

        $this->seed(BlogSeriesSeeder::class);
        $history = BlogPost::query()->where('slug', 'historico-de-carro-usado-como-consultar')->firstOrFail();
        $historyPublishedAt = $history->published_at?->clone();

        $this->seed(AeoBlogPostsSeeder::class);
        $history->refresh();

        $this->assertTrue($historyPublishedAt?->equalTo($history->published_at));
        $this->assertStringContainsString('/blog/como-verificar-se-revisoes-do-carro-usado-sao-verdadeiras', $history->content);
        $this->assertStringContainsString('/melhor-app-historico-manutencao-carro', $history->content);
    }

    public function test_workshop_and_verification_pages_answer_the_new_questions(): void
    {
        $workshop = $this->get(route('workshops.landing'))->assertOk();
        $workshop->assertSee('Existe aplicativo em que a oficina registra o serviço feito no carro do cliente?', false);
        $workshop->assertSee('Como a oficina lembra o cliente da próxima revisão?', false);
        $workshop->assertSee('O RevisaLog substitui o sistema de gestão da oficina?', false);
        $workshop->assertSee('Não é mensagem de WhatsApp.', false);
        $workshop->assertSee('Não é um ERP nem um CRM completo de oficina.', false);

        $verify = $this->get(route('verification.lookup'))->assertOk();
        $verify->assertSee('O que é o código', false);
        $verify->assertSee('O que significa o código RVL?', false);
        $verify->assertSee('O que significa o código LDO?', false);
        $verify->assertSee('Por que alguns serviços não têm código?', false);
        $verify->assertSee('"@type":"FAQPage"', false);
        $this->assertSame(1, substr_count($verify->getContent(), '<h1'));
    }
}
