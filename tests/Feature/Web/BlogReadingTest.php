<?php

namespace Tests\Feature\Web;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Leitura do blog (PUB-19, PUB-20, PUB-22, PUB-24, PUB-X07): cabeçalho público pelo x-ui.page-header,
 * coluna de leitura de ~72 caracteres por linha, card com um link só, chips de categoria com aria-current e
 * uma imagem de compartilhamento por página.
 */
class BlogReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_draws_its_only_h1_with_the_public_page_header(): void
    {
        BlogPost::factory()->count(2)->create();

        $page = $this->page($this->get(route('blog.index'))->assertOk());

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Blog', $this->text($this->find($page, 'header[data-slot="page-header"] h1')));
        $this->assertNull($page->querySelector('header[data-slot="page-header"] nav[aria-label="Trilha"]'), 'O índice é o 1º nível: sem trilha.');
    }

    public function test_category_page_has_a_breadcrumb_back_to_the_blog(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Documentação', 'slug' => 'documentacao']);
        BlogPost::factory()->for($category, 'category')->create();

        $page = $this->page($this->get(route('blog.category', $category))->assertOk());

        $this->assertSame('Documentação', $this->text($this->find($page, 'header[data-slot="page-header"] h1')));
        $trail = $this->find($page, 'header[data-slot="page-header"] nav[aria-label="Trilha"]');
        $this->assertSame(route('blog.index'), $this->find($trail, 'a')->getAttribute('href'));
        $this->assertSame('Documentação', $this->text($this->find($trail, '[aria-current="page"]')));
    }

    public function test_category_chips_mark_the_current_page_and_scroll_sideways_on_small_screens(): void
    {
        $maintenance = BlogCategory::factory()->create(['name' => 'Manutenção', 'slug' => 'manutencao']);
        $documents = BlogCategory::factory()->create(['name' => 'Documentação', 'slug' => 'documentacao']);
        BlogPost::factory()->for($maintenance, 'category')->count(2)->create();
        BlogPost::factory()->for($documents, 'category')->create();

        $index = $this->page($this->get(route('blog.index'))->assertOk());
        $chips = $this->find($index, 'nav[aria-label="Categorias do blog"]');
        $current = $chips->querySelectorAll('a[aria-current="page"]');

        $this->assertCount(1, $current);
        $this->assertSame('Todos', $this->text($current->item(0)));
        $this->assertStringContainsString('(2 artigos)', $chips->textContent);
        $this->assertStringContainsString('(1 artigo)', $chips->textContent);

        $list = $this->find($chips, 'ul');
        foreach (['overflow-x-auto', 'snap-x', 'sm:flex-wrap', 'sm:overflow-visible'] as $class) {
            $this->assertContains($class, $this->classesOf($list));
        }
        foreach ($chips->querySelectorAll('a') as $chip) {
            $this->assertContains('min-h-10', $this->classesOf($chip), 'Chip com alvo de 40px.');
            $this->assertContains('shrink-0', $this->classesOf($chip));
        }

        $category = $this->page($this->get(route('blog.category', $maintenance))->assertOk());
        $currentOnCategory = $this->find($category, 'nav[aria-label="Categorias do blog"]')->querySelectorAll('a[aria-current="page"]');

        $this->assertCount(1, $currentOnCategory);
        $this->assertSame(route('blog.category', $maintenance), $currentOnCategory->item(0)->getAttribute('href'));
    }

    public function test_empty_category_offers_a_way_back_to_all_posts(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Venda', 'slug' => 'venda']);
        BlogPost::factory()->for($category, 'category')->scheduled()->create();

        $page = $this->page($this->get(route('blog.category', $category))->assertOk());
        $empty = $this->find($page, '[data-slot="empty-state"]');

        $this->assertSame('Ainda não há artigos nesta categoria', $this->text($this->find($empty, 'h2')));
        $actions = array_map(fn (Element $link): string => $link->getAttribute('href'), iterator_to_array($empty->querySelectorAll('a')));
        $this->assertSame([route('blog.index'), route('home')], $actions);
    }

    public function test_empty_blog_shows_the_empty_state_without_the_all_posts_action(): void
    {
        $page = $this->page($this->get(route('blog.index'))->assertOk());
        $empty = $this->find($page, '[data-slot="empty-state"]');

        $this->assertSame('Ainda não há artigos aqui', $this->text($this->find($empty, 'h2')));
        $this->assertCount(1, $empty->querySelectorAll('a'));
        $this->assertSame(route('home'), $this->find($empty, 'a')->getAttribute('href'));
    }

    public function test_card_has_a_single_stretched_link_to_the_post_and_a_decorative_cover(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Manutenção', 'slug' => 'manutencao']);
        $post = BlogPost::factory()->for($category, 'category')->create(['title' => 'Quando trocar o óleo']);

        $page = $this->page($this->get(route('blog.index'))->assertOk());
        $card = $this->find($page, 'article[data-slot="blog-card"]');

        $postLinks = array_values(array_filter(
            iterator_to_array($card->querySelectorAll('a')),
            fn (Element $link): bool => $link->getAttribute('href') === route('blog.show', $post),
        ));
        $this->assertCount(1, $postLinks, 'Uma parada de Tab por card: só o título leva ao post.');
        $this->assertSame('Quando trocar o óleo', $this->text($postLinks[0]));
        $this->assertSame('H2', $postLinks[0]->parentElement->tagName);
        $this->assertContains('after:absolute', $this->classesOf($postLinks[0]));
        $this->assertContains('after:inset-0', $this->classesOf($postLinks[0]));
        $this->assertContains('relative', $this->classesOf($card));

        $this->assertNull($this->find($card, '[data-slot="blog-card-media"]')->querySelector('a'), 'A capa não é outro link.');
        $cover = $this->find($card, '[data-slot="blog-card-media"] svg');
        $this->assertSame('true', $cover->getAttribute('aria-hidden'));
        $this->assertFalse($cover->hasAttribute('aria-label'));

        $categoryLink = $this->find($card, '[data-slot="blog-card-category"]');
        $this->assertSame(route('blog.category', $category), $categoryLink->getAttribute('href'));
        $this->assertContains('relative', $this->classesOf($categoryLink));
        $this->assertContains('z-10', $this->classesOf($categoryLink), 'A categoria fica clicável por cima do link esticado.');
    }

    public function test_card_photo_is_decorative_and_reserves_its_space(): void
    {
        BlogPost::factory()->create(['cover_photo_path' => 'blog-covers/oleo.jpg', 'cover_photo_alt' => 'Mecânico trocando o óleo']);

        $page = $this->page($this->get(route('blog.index'))->assertOk());
        $image = $this->find($page, '[data-slot="blog-card-media"] img');

        $this->assertSame('', $image->getAttribute('alt'));
        $this->assertSame('1200', $image->getAttribute('width'));
        $this->assertSame('630', $image->getAttribute('height'));
        $this->assertSame('lazy', $image->getAttribute('loading'));
        $this->assertContains('aspect-[1200/630]', $this->classesOf($image));
        $this->assertContains('object-contain', $this->classesOf($image), 'A foto aparece inteira, sem corte.');
        $this->assertNotContains('object-cover', $this->classesOf($image));
    }

    public function test_card_motion_is_opt_in_and_never_transition_all(): void
    {
        BlogPost::factory()->create();

        $page = $this->page($this->get(route('blog.index'))->assertOk());
        $card = $this->find($page, 'article[data-slot="blog-card"]');
        $cardClasses = $this->classesOf($card);

        $this->assertContains('motion-safe:hover:-translate-y-0.5', $cardClasses);
        $this->assertContains('motion-reduce:transition-none', $cardClasses);
        $this->assertContains('ease-smooth-out', $cardClasses);
        $this->assertNotContains('transition', $cardClasses);
        $this->assertNotContains('transition-all', $cardClasses);
        $this->assertContains('motion-safe:group-hover:scale-[1.03]', $this->classesOf($this->find($card, '[data-slot="blog-card-media"] svg')));
    }

    public function test_article_title_is_the_only_h1_responsive_and_balanced(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Revisão dos 40 mil km: o que muda no preço de revenda']);

        $page = $this->page($this->get(route('blog.show', $post))->assertOk());

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $heading = $this->find($page, 'header[data-slot="page-header"] h1');
        $this->assertContains('text-balance', $this->classesOf($heading));
        $title = $this->find($heading, '[data-slot="blog-post-title"]');
        $this->assertSame($post->title, $this->text($title));
        foreach (['text-3xl', 'sm:text-4xl', 'lg:text-5xl'] as $class) {
            $this->assertContains($class, $this->classesOf($title));
        }
    }

    public function test_article_breadcrumb_leads_back_to_blog_and_category(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Manutenção', 'slug' => 'manutencao']);
        $post = BlogPost::factory()->for($category, 'category')->create();

        $page = $this->page($this->get(route('blog.show', $post))->assertOk());
        $trail = $this->find($page, 'nav[aria-label="Trilha"]');
        $links = array_map(fn (Element $link): string => $link->getAttribute('href'), iterator_to_array($trail->querySelectorAll('a')));

        $this->assertSame([route('blog.index'), route('blog.category', $category)], $links);
        $this->assertSame($post->title, $this->text($this->find($trail, '[aria-current="page"]')));
    }

    public function test_article_text_sits_in_a_comfortable_reading_column(): void
    {
        $post = BlogPost::factory()->create(['content' => "## Por que o chassi\n\nA placa muda, o chassi não."]);

        $page = $this->page($this->get(route('blog.show', $post))->assertOk());
        $content = $this->find($page, '[data-slot="blog-post-content"]');
        $classes = $this->classesOf($content);

        foreach (['blog-content', 'max-w-[56ch]', 'mx-auto', 'text-[1.0625rem]', 'sm:text-lg', 'leading-[1.8]'] as $class) {
            $this->assertContains($class, $classes);
        }
        $this->assertSame('Por que o chassi', $this->text($this->find($content, 'h2')));
    }

    public function test_article_lead_does_not_look_like_a_quote(): void
    {
        $post = BlogPost::factory()->create(['excerpt' => 'O que conferir antes de comprar um usado.']);

        $page = $this->page($this->get(route('blog.show', $post))->assertOk());
        $lead = $this->find($page, '[data-slot="blog-post-lead"]');

        $this->assertSame('O que conferir antes de comprar um usado.', $this->text($lead));
        $this->assertNotContains('border-l-4', $this->classesOf($lead));
        $this->assertContains('text-lg', $this->classesOf($lead));
    }

    public function test_article_cover_photo_reserves_its_space(): void
    {
        $post = BlogPost::factory()->create(['cover_photo_path' => 'blog-covers/capa.jpg', 'cover_photo_alt' => 'Painel do carro']);

        $page = $this->page($this->get(route('blog.show', $post))->assertOk());
        $image = $this->find($page, '[data-slot="blog-post-cover"] img');

        $this->assertSame('Painel do carro', $image->getAttribute('alt'));
        $this->assertSame('1200', $image->getAttribute('width'));
        $this->assertSame('630', $image->getAttribute('height'));
        $this->assertSame('high', $image->getAttribute('fetchpriority'));
        $this->assertContains('aspect-[1200/630]', $this->classesOf($image));
        $this->assertContains('object-contain', $this->classesOf($image), 'A foto aparece inteira, sem corte.');
        $this->assertNotContains('object-cover', $this->classesOf($image));
    }

    public function test_related_posts_are_h3_under_the_leia_tambem_h2(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->for($category, 'category')->create();
        $related = BlogPost::factory()->for($category, 'category')->create(['title' => 'Checklist da revisão']);

        $page = $this->page($this->get(route('blog.show', $post))->assertOk());
        $section = $this->find($page, 'section#leia-tambem');

        $this->assertSame('Leia também', $this->text($this->find($section, 'h2')));
        $this->assertSame('Checklist da revisão', $this->text($this->find($section, 'article[data-slot="blog-card"] h3')));
        $this->assertNull($section->querySelector('article[data-slot="blog-card"] h2'));
        $this->assertSame(route('blog.show', $related), $this->find($section, 'article h3 a')->getAttribute('href'));
    }

    public function test_article_call_to_action_offers_sign_up_only_to_guests(): void
    {
        $post = BlogPost::factory()->create();

        $guestCta = $this->find($this->page($this->get(route('blog.show', $post))->assertOk()), '[data-slot="blog-post-cta"]');
        $this->assertStringContainsString('Começar grátis', $guestCta->textContent);
        $this->assertStringContainsString('Conhecer a plataforma', $guestCta->textContent);

        $owner = User::factory()->asUser()->create();
        $ownerCta = $this->find($this->page($this->actingAs($owner)->get(route('blog.show', $post))->assertOk()), '[data-slot="blog-post-cta"]');
        $this->assertStringNotContainsString('Começar grátis', $ownerCta->textContent);
    }

    public function test_draft_preview_warns_with_the_alert_component(): void
    {
        $admin = User::factory()->asUser()->create(['is_admin' => true]);
        $post = BlogPost::factory()->draft()->create();

        $page = $this->page($this->actingAs($admin)->get(route('blog.show', $post))->assertOk());
        $alert = $this->find($page, '[data-slot="alert"][data-variant="warning"]');

        $this->assertStringContainsString('ainda não está publicado', $alert->textContent);
    }

    public function test_article_hands_its_cover_to_the_layout_as_the_share_image(): void
    {
        $post = BlogPost::factory()->create(['cover_photo_path' => 'blog-covers/capa.jpg', 'cover_photo_alt' => 'Painel do carro']);

        $sections = view('blog.show', ['post' => $post->load('category', 'author'), 'related' => collect()])->renderSections();

        $this->assertSame(e($post->cover_photo_url), $sections['og_image'] ?? null);
        $this->assertSame('Painel do carro', $sections['og_image_alt'] ?? null);
    }

    public function test_article_without_photo_keeps_the_brand_share_image(): void
    {
        $post = BlogPost::factory()->create(['cover_photo_path' => null]);

        $sections = view('blog.show', ['post' => $post->load('category', 'author'), 'related' => collect()])->renderSections();

        $this->assertArrayNotHasKey('og_image', $sections);
    }

    public function test_article_head_has_one_share_image_and_one_twitter_card(): void
    {
        $post = BlogPost::factory()->create(['cover_photo_path' => 'blog-covers/capa.jpg']);

        $page = $this->page($this->get(route('blog.show', $post))->assertOk());

        $this->assertCount(1, $page->querySelectorAll('meta[property="og:image"]'), 'Um og:image só: WhatsApp e Facebook usam o primeiro.');
        $this->assertCount(1, $page->querySelectorAll('meta[name="twitter:card"]'));
        $this->assertCount(1, $page->querySelectorAll('meta[name="twitter:image"]'));
        $this->assertCount(1, $page->querySelectorAll('script[type="application/ld+json"]'));

        // A imagem compartilhada é a capa do post, não a arte padrão da marca.
        $this->assertSame($post->cover_photo_url, $this->find($page, 'meta[property="og:image"]')->getAttribute('content'));
        $this->assertSame($post->cover_photo_url, $this->find($page, 'meta[name="twitter:image"]')->getAttribute('content'));
        $this->assertNull($page->querySelector('meta[property="og:image:width"]'), 'Largura e altura são da arte da marca, não da capa.');
        $this->assertSame(route('blog.show', $post), $this->find($page, 'link[rel="canonical"]')->getAttribute('href'));
    }

    private function page(TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
    }

    private function find(HTMLDocument|Element $scope, string $selector): Element
    {
        $element = $scope->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    /**
     * @return list<string>
     */
    private function classesOf(Element $element): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim((string) $element->getAttribute('class'))) ?: []));
    }

    private function text(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }
}
