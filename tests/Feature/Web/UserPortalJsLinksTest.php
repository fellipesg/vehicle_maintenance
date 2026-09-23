<?php

namespace Tests\Feature\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class UserPortalJsLinksTest extends TestCase
{
    /**
     * The portal is rendered client-side, so its hard-coded hrefs are never
     * exercised by a controller test. Match each one against the router so a
     * typo cannot ship a link that falls through to a `{id}` route.
     */
    public function test_hard_coded_portal_links_resolve_to_registered_routes(): void
    {
        $source = file_get_contents(resource_path('js/user-portal.js'));

        preg_match_all('#href="(/[^"`\s]*)#', $source, $matches);

        $paths = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($paths, 'No hard-coded portal links were found to verify.');

        foreach ($paths as $path) {
            $resolvable = preg_replace('#\$\{[^}]*\}#', '1', $path);

            try {
                $this->app['router']->getRoutes()->match(Request::create($resolvable, 'GET'));
            } catch (NotFoundHttpException|UrlGenerationException) {
                $this->fail("user-portal.js links to {$path}, which matches no registered GET route.");
            }
        }
    }
}
