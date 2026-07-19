<?php

namespace App\Libraries;

/**
 * Liste statique des corridors (pays) mobile money supportés par Peex,
 * telle que documentée sur https://peex-api-docs.peexit.com/phone-verification
 *
 * Peex n'expose aucun endpoint "liste des corridors" dans son API (seuls
 * /clients/me, /clients/verify_phoneNumber, /clients/verify_wallet,
 * /clients/request_payment, /clients/request_bank_payment et
 * /clients/all_requests existent) : cette table est donc la seule source
 * fiable pour valider un corridor mobile money avant d'appeler Peex.
 *
 * NB : cette restriction ne s'applique qu'aux transferts MOBILE MONEY.
 * Peex ne documente pas de liste de pays limitée pour les virements
 * bancaires (request_bank_payment) — n'importe quel IBAN/SWIFT valide
 * peut être tenté, Peex validant lui-même à l'appel.
 *
 * Pense à mettre cette liste à jour si Peex étend sa couverture (voir
 * leur Change Log : https://peex-api-docs.peexit.com/changelog).
 */
class PeexCorridors
{
    /**
     * code ISO Alpha-2 => [name, dial, operators[]]
     */
    public const MOMO_CORRIDORS = [
        // Afrique de l'Ouest
        'CI' => ['name' => "Côte d'Ivoire", 'dial' => '+225', 'operators' => ['MTN', 'MOOV', 'ORANGE']],
        'TG' => ['name' => 'Togo', 'dial' => '+228', 'operators' => ['MOOV', 'TMONEY']],
        'SN' => ['name' => 'Sénégal', 'dial' => '+221', 'operators' => ['ORANGE', 'FREE']],
        'ML' => ['name' => 'Mali', 'dial' => '+223', 'operators' => ['MOOV']],
        'BF' => ['name' => 'Burkina Faso', 'dial' => '+226', 'operators' => ['MOOV', 'ORANGE']],
        'BJ' => ['name' => 'Bénin', 'dial' => '+229', 'operators' => ['MOOV']],
        // Afrique Centrale
        'CM' => ['name' => 'Cameroun', 'dial' => '+237', 'operators' => ['MTN', 'ORANGE']],
        'GA' => ['name' => 'Gabon', 'dial' => '+241', 'operators' => ['AIRTEL', 'MOOV']],
        'TD' => ['name' => 'Tchad', 'dial' => '+235', 'operators' => ['AIRTEL', 'MOOV']],
        'CF' => ['name' => 'Centrafrique', 'dial' => '+236', 'operators' => ['ORANGE']],
        'CG' => ['name' => 'Congo', 'dial' => '+242', 'operators' => ['AIRTEL', 'MTN']],
        'GQ' => ['name' => 'Guinée Équatoriale', 'dial' => '+240', 'operators' => ['MUNI DINERO']],
    ];

    public static function isMomoSupported(?string $countryCode): bool
    {
        if (!$countryCode) {
            return false;
        }
        return array_key_exists(strtoupper($countryCode), self::MOMO_CORRIDORS);
    }

    public static function list(): array
    {
        return self::MOMO_CORRIDORS;
    }

    public static function forApp(): array
    {
        // Format compact pour le front (mobile/admin)
        $out = [];
        foreach (self::MOMO_CORRIDORS as $code => $info) {
            $out[] = [
                'country_code' => $code,
                'name' => $info['name'],
                'dial' => $info['dial'],
                'operators' => $info['operators'],
            ];
        }
        return $out;
    }
}
