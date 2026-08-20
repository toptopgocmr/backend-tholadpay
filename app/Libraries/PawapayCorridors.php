<?php

namespace App\Libraries;

/**
 * Corridors mobile money supportés par PawaPay pour l'intégration Tholadpay
 * (source : https://docs.pawapay.io/v2/docs/providers — page "Providers",
 * consultée le 2026-08-20).
 *
 * Contrairement à Peex (PeexCorridors, plusieurs dizaines de pays), PawaPay
 * n'est intégré ici QUE pour le Congo-Brazzaville (République du Congo, CG /
 * COG) — 3e partenaire payeur, demandé explicitement en plus de Peex et
 * DigitWace (voir email Max Hickey du 2026-08-18 : "simulate payout flows
 * for Congo-Brazzaville mobile money"). PawaPay propose bien d'autres pays
 * (voir doc Providers), mais on ne déclare ici que ce qui a été demandé —
 * étendre cette table (et l'appel resolvePawapayProvider ci-dessous) le jour
 * où un autre corridor PawaPay sera réellement utilisé.
 *
 * NB devise : XAF ne supporte pas les décimales chez PawaPay (voir doc
 * Providers, colonne "decimalsInAmount" = false pour AIRTEL_COG/MTN_MOMO_COG)
 * — les montants envoyés à /v2/remittances doivent donc être des entiers.
 */
class PawapayCorridors
{
    /**
     * code ISO Alpha-2 => [name, dial, iso3 (Alpha-3, requis par
     * sender.senderDetails.address.country — doc Remittance API), currency,
     * operators (nom court affiché app => code "provider" PawaPay exact)]
     */
    public const CORRIDORS = [
        'CG' => [
            'name' => 'Congo',
            'dial' => '+242',
            'iso3' => 'COG',
            'currency' => 'XAF',
            'operators' => [
                'AIRTEL' => 'AIRTEL_COG',
                'MTN' => 'MTN_MOMO_COG',
            ],
        ],
    ];

    public static function isSupported(?string $countryCode): bool
    {
        if (!$countryCode) {
            return false;
        }
        return array_key_exists(strtoupper($countryCode), self::CORRIDORS);
    }

    public static function list(): array
    {
        return self::CORRIDORS;
    }

    /**
     * Format compact pour le front (mobile/admin), même forme que
     * PeexCorridors::forApp() pour que le sélecteur de pays puisse afficher
     * les deux listes de façon uniforme selon le partenaire choisi.
     */
    public static function forApp(): array
    {
        $out = [];
        foreach (self::CORRIDORS as $code => $info) {
            $out[] = [
                'country_code' => $code,
                'name' => $info['name'],
                'dial' => $info['dial'],
                'operators' => array_keys($info['operators']),
            ];
        }
        return $out;
    }

    /**
     * Résout le code "provider" PawaPay exact (ex: 'AIRTEL_COG') à partir du
     * pays et d'un nom court d'opérateur ('AIRTEL', 'MTN', insensible à la
     * casse). Renvoie null si le pays ou l'opérateur n'est pas supporté.
     */
    public static function resolveProvider(?string $countryCode, ?string $operator): ?string
    {
        if (!$countryCode || !$operator) {
            return null;
        }
        $corridor = self::CORRIDORS[strtoupper($countryCode)] ?? null;
        if (!$corridor) {
            return null;
        }
        // Accepte aussi directement le code provider complet ('AIRTEL_COG') si
        // l'appelant le connaît déjà, plutôt que de forcer systématiquement le
        // nom court.
        $operatorUpper = strtoupper($operator);
        if (in_array($operatorUpper, $corridor['operators'], true)) {
            return $operatorUpper;
        }
        return $corridor['operators'][$operatorUpper] ?? null;
    }

    public static function iso3(?string $countryCode): ?string
    {
        if (!$countryCode) {
            return null;
        }
        return self::CORRIDORS[strtoupper($countryCode)]['iso3'] ?? null;
    }

    public static function currency(?string $countryCode): ?string
    {
        if (!$countryCode) {
            return null;
        }
        return self::CORRIDORS[strtoupper($countryCode)]['currency'] ?? null;
    }
}
