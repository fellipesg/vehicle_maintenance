<?php

namespace App\Support\Seo;

use App\Models\BlogPost;
use App\Support\AppStorage;

final class StructuredData
{
    /**
     * Organization + WebSite, presentes em toda página pública.
     *
     * @return array<string, mixed>
     */
    public static function siteGraph(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::organization(),
                self::website(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => CanonicalUrl::organizationId(),
            'name' => 'RevisaLog',
            'url' => CanonicalUrl::home(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => AppStorage::brandUrl('app-icon.png'),
            ],
            'email' => (string) config('legal.support_email'),
            'founder' => [
                '@type' => 'Person',
                'name' => 'Felipe Gonçalves',
            ],
            'sameAs' => array_values(array_filter([
                (string) config('app.ios_app_store_url'),
                (string) config('seo.instagram_url'),
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => CanonicalUrl::websiteId(),
            'name' => 'RevisaLog',
            'url' => CanonicalUrl::home(),
            'inLanguage' => 'pt-BR',
            'publisher' => [
                '@id' => CanonicalUrl::organizationId(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mobileApplication(string $description): array
    {
        $storeUrl = (string) config('app.ios_app_store_url');

        return [
            '@context' => 'https://schema.org',
            '@type' => ['SoftwareApplication', 'MobileApplication'],
            'name' => 'RevisaLog',
            'operatingSystem' => 'iOS',
            'applicationCategory' => 'UtilitiesApplication',
            'url' => $storeUrl,
            'installUrl' => $storeUrl,
            'description' => $description,
            'inLanguage' => 'pt-BR',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'BRL',
            ],
            'publisher' => [
                '@id' => CanonicalUrl::organizationId(),
            ],
        ];
    }

    /**
     * @param  list<array{question: string, parts: list<array{text: string, href?: string, external?: bool}>}>  $items
     * @return array<string, mixed>
     */
    public static function faqPage(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'inLanguage' => 'pt-BR',
            'mainEntity' => array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => Faq::plain($item['parts']),
                ],
            ], $items),
        ];
    }

    /**
     * @param  list<array{name: string, url?: string}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumb(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $element = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
            ];

            if (isset($item['url'])) {
                $element['item'] = $item['url'];
            }

            $elements[] = $element;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function webPage(string $name, string $description, string $url, ?string $dateModified = null): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'inLanguage' => 'pt-BR',
            'isPartOf' => [
                '@id' => CanonicalUrl::websiteId(),
            ],
            'publisher' => [
                '@id' => CanonicalUrl::organizationId(),
            ],
        ];

        if ($dateModified !== null) {
            $data['dateModified'] = $dateModified;
        }

        return $data;
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public static function itemList(string $name, array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'url' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blogPosting(BlogPost $post, int $commentCount): array
    {
        $url = CanonicalUrl::named('blog.show', $post);
        $authorName = $post->author?->name;
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->meta_description ?: $post->summary,
            'inLanguage' => 'pt-BR',
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => ($post->updated_at ?? $post->published_at)?->toIso8601String(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url,
            ],
            'author' => [
                '@type' => 'Person',
                'name' => is_string($authorName) && $authorName !== '' ? $authorName : 'Felipe Gonçalves',
            ],
            'publisher' => [
                '@type' => 'Organization',
                '@id' => CanonicalUrl::organizationId(),
                'name' => 'RevisaLog',
            ],
            'commentCount' => $commentCount,
        ];

        if (filled($post->cover_photo_url)) {
            $data['image'] = $post->cover_photo_url;
        }

        return $data;
    }
}
