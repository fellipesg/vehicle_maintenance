<?php

namespace Tests\Feature\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class UserPortalJsLinksTest extends TestCase
{
    /**
     * The owner portal is server-rendered now; the only link its scripts build is the PDF download
     * (/usuario/exportacoes-pdf/{id}/{file}.pdf). Match every hard-coded path against the router so
     * a typo cannot ship a link that falls through to a `{id}` route.
     */
    public function test_hard_coded_portal_links_resolve_to_registered_routes(): void
    {
        $paths = [];

        foreach (['js/user-portal.js', 'js/utils/vehicle-pdf-export.js'] as $script) {
            $source = file_get_contents(resource_path($script));

            preg_match_all('#href="(/[^"`\s]*)#', $source, $hrefs);
            preg_match_all("#'(/usuario/[^'\s]*)'#", $source, $constants);

            $paths = [...$paths, ...$hrefs[1], ...$constants[1]];
        }

        $paths = array_values(array_unique($paths));

        $this->assertNotEmpty($paths, 'No hard-coded portal links were found to verify.');

        foreach ($paths as $path) {
            $resolvable = preg_replace('#\$\{[^}]*\}#', '1', $path);

            // Prefixo de caminho (termina em /): completa com um id e um nome de arquivo PDF.
            if (str_ends_with($resolvable, '/')) {
                $resolvable .= '1/historico_manutencoes.pdf';
            }

            try {
                $route = $this->app['router']->getRoutes()->match(Request::create($resolvable, 'GET'));
            } catch (NotFoundHttpException|UrlGenerationException) {
                $this->fail("A owner portal script links to {$path}, which matches no registered GET route.");
            }

            $this->assertNotSame('user.vehicles.show', $route->getName(), "{$path} caiu numa rota {id}.");
        }
    }
}
