<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os Markdown dos posts não prometem texto futuro e não chamam de pública a busca que exige conta.
 */
class BlogSeriesWordingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function files(): array
    {
        return [
            ...glob(database_path('data/blog_series/*.md')) ?: [],
            ...glob(database_path('data/aeo_posts/*.md')) ?: [],
        ];
    }

    public function test_no_post_promises_a_future_or_next_post(): void
    {
        $this->assertCount(26, $this->files());

        foreach ($this->files() as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/post futuro|artigo futuro|nesta série|próximo (post|artigo|texto)|em um próximo|nos próximos (posts|artigos)/iu',
                (string) file_get_contents($file),
                basename($file).' promete conteúdo que pode não existir.',
            );
        }
    }

    public function test_the_vehicle_search_is_never_called_public_and_says_it_needs_an_account(): void
    {
        $linked = 0;

        foreach ($this->files() as $file) {
            foreach (file($file) ?: [] as $line) {
                if (! str_contains($line, '/buscar-veiculo')) {
                    continue;
                }

                $linked++;
                $this->assertStringContainsString('conta gratuita', $line, basename($file));
                $this->assertDoesNotMatchRegularExpression('/consulta pública/iu', $line, basename($file));
            }
        }

        $this->assertSame(5, $linked);
    }

    public function test_the_buscar_veiculo_route_still_requires_login(): void
    {
        $this->get('/buscar-veiculo')->assertRedirect(route('login'));
    }
}
