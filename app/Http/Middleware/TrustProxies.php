<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
// Fideloper\Proxy\TrustProxies n'est pas installe (absent de composer.json/
// lock) : cette classe n'existe pas -> 500 fatal des que ce middleware est
// active. Laravel 8 fournit l'equivalent en natif, on l'utilise a la place.
use Illuminate\Http\Middleware\TrustProxies as Middleware;

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
