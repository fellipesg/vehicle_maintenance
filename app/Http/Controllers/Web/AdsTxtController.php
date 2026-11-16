<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\GoogleAdsense;
use Illuminate\Http\Response;

/**
 * /ads.txt declara o RevisaLog como vendedor direto do próprio inventário no AdSense. Sem
 * ADSENSE_CLIENT_ID responde 404, para não publicar um editor que não existe.
 */
class AdsTxtController extends Controller
{
    /**
     * ID fixo do Google na Trustworthy Accountability Group (TAG), o mesmo para todo editor do AdSense.
     */
    private const GOOGLE_TAG_ID = 'f08c47fec0942fa0';

    public function __invoke(): Response
    {
        $publisherId = GoogleAdsense::publisherId();

        abort_if($publisherId === null, 404);

        return response('google.com, '.$publisherId.', DIRECT, '.self::GOOGLE_TAG_ID."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
