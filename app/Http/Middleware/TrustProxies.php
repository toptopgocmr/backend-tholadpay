<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Railway (comme Heroku) fait transiter les requêtes derrière un proxy
     * dont l'IP n'est pas fixe. Sans ça, Laravel ignore X-Forwarded-Proto
     * et génère des URLs en http:// même quand le visiteur est en https://,
     * ce qui casse le chargement des assets (CSS/JS) en contenu mixte.
     *
     * @var array|string
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var string
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
