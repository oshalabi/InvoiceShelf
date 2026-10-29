<?php

namespace App\Platform\Modules\Http\Controllers\Assets;

use App\Platform\Http\Controller;
use App\Platform\Modules\Runtime\ModuleAssetVersion;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvoiceShelf\Modules\Registry as ModuleRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ScriptController extends Controller
{
    /**
     * Stream one script that a module published from its ServiceProvider::boot()
     * through \InvoiceShelf\Modules\Registry::registerScript($name, $path).
     *
     * A "v" query value equal to the hash of the bytes being served earns a
     * year-long immutable cache; every other request is answered no-store so a
     * rebuilt asset is never hidden behind a stale copy.
     *
     * @throws NotFoundHttpException
     */
    public function __invoke(Request $request, string $script): Response
    {
        $path = ModuleRegistry::scriptFor($script);

        abort_if($path === null || ! is_file($path), 404);

        $contents = file_get_contents($path);

        abort_if(! is_string($contents), 404);

        $version = ModuleAssetVersion::forContents($contents);
        $requested = $request->query('v');
        $matchesServedBytes = is_string($requested) && hash_equals($version, $requested);

        $response = response($contents, 200, ['Content-Type' => 'application/javascript'])
            ->setLastModified(DateTime::createFromFormat('U', (string) filemtime($path)));

        $response->headers->set(
            'Cache-Control',
            $matchesServedBytes ? 'public, max-age=31536000, immutable' : 'no-store'
        );

        return $response;
    }
}
