<?php

namespace App\Http\Middleware;

use Closure;

/**
 * AJOUT (2026-09-05, suite au scan securityheaders.com/Qualys SSL Labs
 * fourni par l'utilisateur — note F sur backend-tholadpay-production.up.railway.app) :
 * ajoute les en-têtes de sécurité HTTP manquants sur TOUTES les réponses
 * (API + web) et masque la stack technique (X-Powered-By: PHP/8.1.34).
 *
 * Le score F du scan ne signale pas une faille active mais un défaut de
 * configuration des en-têtes ; ce middleware les ajoute globalement (voir
 * enregistrement dans app/Http/Kernel.php, tableau $middleware).
 */
class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (method_exists($response, 'headers')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');
            // HSTS : le domaine Railway est déjà servi en HTTPS uniquement (voir
            // TrustProxies + certificat vu dans le rapport SSL Labs, note A).
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            // CSP volontairement permissive sur connect-src/img-src car ce backend
            // sert une API JSON consommée par l'app mobile et le panel admin, pas
            // des pages HTML publiques ; default-src 'self' suffit à bloquer les
            // tentatives d'injection de contenu si une route web venait à s'ajouter.
            $response->headers->set('Content-Security-Policy', "default-src 'self'; frame-ancestors 'self'");
            $response->headers->remove('X-Powered-By');
        }

        return $response;
    }
}
