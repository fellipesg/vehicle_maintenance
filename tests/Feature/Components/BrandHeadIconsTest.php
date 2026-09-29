<?php

namespace Tests\Feature\Components;

use App\Support\AppStorage;
use Dom\HTMLDocument;
use Tests\TestCase;

/**
 * <x-brand-head-icons>: a imagem de compartilhamento sai uma vez só, a da página (capa do post) ou
 * a arte da marca, sem escapar duas vezes a URL que já vem escapada do @section.
 */
class BrandHeadIconsTest extends TestCase
{
    public function test_without_a_page_image_it_prints_the_brand_art_with_its_size(): void
    {
        $head = $this->renderHead('<x-brand-head-icons include-og-image />');
        $brandImage = AppStorage::brandUrl('og-preview.png');

        $this->assertSame([$brandImage], $this->contents($head, 'meta[property="og:image"]'));
        $this->assertSame([$brandImage], $this->contents($head, 'meta[name="twitter:image"]'));
        $this->assertSame(['1200'], $this->contents($head, 'meta[property="og:image:width"]'));
        $this->assertSame(['RevisaLog'], $this->contents($head, 'meta[property="og:image:alt"]'));
        $this->assertSame(['summary_large_image'], $this->contents($head, 'meta[name="twitter:card"]'));
    }

    public function test_a_page_image_replaces_the_brand_art_once_without_double_escaping(): void
    {
        // Como chega do layout: $__env->yieldContent() devolve o texto já escapado pelo @section.
        $head = $this->renderHead(
            '<x-brand-head-icons include-og-image :og-image="$image" :og-image-alt="$alt" />',
            ['image' => e('https://cdn.example.test/blog-covers/capa.jpg?v=2&sig=abc'), 'alt' => e('Óleo & filtro')],
        );

        $this->assertSame(['https://cdn.example.test/blog-covers/capa.jpg?v=2&sig=abc'], $this->contents($head, 'meta[property="og:image"]'));
        $this->assertSame(['https://cdn.example.test/blog-covers/capa.jpg?v=2&sig=abc'], $this->contents($head, 'meta[name="twitter:image"]'));
        $this->assertSame(['Óleo & filtro'], $this->contents($head, 'meta[property="og:image:alt"]'));
        $this->assertSame([], $this->contents($head, 'meta[property="og:image:width"]'), 'Tamanho e tipo são da arte da marca.');
        $this->assertSame([], $this->contents($head, 'meta[property="og:image:type"]'));
        $this->assertSame(['summary_large_image'], $this->contents($head, 'meta[name="twitter:card"]'));
    }

    public function test_page_image_without_alt_falls_back_to_the_brand_name(): void
    {
        $head = $this->renderHead('<x-brand-head-icons include-og-image og-image="https://cdn.example.test/capa.jpg" og-image-alt="" />');

        $this->assertSame(['RevisaLog'], $this->contents($head, 'meta[property="og:image:alt"]'));
    }

    public function test_without_include_og_image_only_the_icons_are_printed(): void
    {
        $head = $this->renderHead('<x-brand-head-icons og-image="https://cdn.example.test/capa.jpg" />');

        $this->assertSame([], $this->contents($head, 'meta[property="og:image"]'));
        $this->assertSame([], $this->contents($head, 'meta[name="twitter:image"]'));
        $this->assertCount(1, $head->querySelectorAll('link[rel="icon"]'));
        $this->assertCount(1, $head->querySelectorAll('link[rel="apple-touch-icon"]'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderHead(string $template, array $data = []): HTMLDocument
    {
        $html = (string) $this->blade($template, $data);

        return HTMLDocument::createFromString('<!DOCTYPE html><html><head>'.$html.'</head><body></body></html>', LIBXML_NOERROR);
    }

    /**
     * @return list<string>
     */
    private function contents(HTMLDocument $document, string $selector): array
    {
        $contents = [];

        foreach ($document->querySelectorAll($selector) as $meta) {
            $contents[] = (string) $meta->getAttribute('content');
        }

        return $contents;
    }
}
