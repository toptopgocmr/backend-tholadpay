<?php

namespace App\Services;

use App\Tax;

/**
 * Calcule les taxes (TTF, Commission COBAC, TVA, Timbre electronique)
 * applicables a une transaction, a partir des taux/valeurs configures
 * dans la table `taxes` (gerable par l'admin via TaxController), sans
 * avoir a toucher au code.
 *
 * Class TaxCalculationService
 * @package App\Services
 */
class TaxCalculationService
{
    /**
     * Codes geres et cle de sortie associee.
     *
     * @var array
     */
    private static $codeMap = [
        'TTF' => 'ttf',
        'COBAC' => 'commission_cobac',
        'TVA' => 'tva',
        'TIMBRE' => 'timbre_electronique',
    ];

    /**
     * Calcule les 4 taxes applicables a un montant/frais donnes.
     *
     * @param float $montant Montant de la transaction (assiette 'montant')
     * @param float $fraisService Frais de service / frais d'envoi (assiette 'frais')
     * @param int|null $zoneId Zone eventuellement associee a la tarification utilisee
     * @return array ['ttf'=>float,'commission_cobac'=>float,'tva'=>float,'timbre_electronique'=>float,'total_taxes'=>float,'frais_envoi_ttc'=>float]
     */
    public function calculate(float $montant, float $fraisService, ?int $zoneId = null): array
    {
        $result = [
            'ttf' => 0,
            'commission_cobac' => 0,
            'tva' => 0,
            'timbre_electronique' => 0,
        ];

        $taxes = $this->getApplicableTaxes($zoneId);

        foreach ($taxes as $tax) {
            $key = self::$codeMap[$tax->code] ?? null;
            if (!$key) {
                continue;
            }

            if ($tax->type === 'pourcentage') {
                $assiette = $tax->assiette === 'frais' ? $fraisService : $montant;
                $montantTaxe = $assiette * ($tax->valeur / 100);
            } elseif ($tax->type === 'fixe') {
                $montantTaxe = $tax->valeur;
            } else {
                $montantTaxe = 0;
            }

            $result[$key] = $montantTaxe;
        }

        $totalTaxes = $result['ttf'] + $result['commission_cobac'] + $result['tva'] + $result['timbre_electronique'];

        $result['total_taxes'] = $totalTaxes;
        // FIX (2026-08-13, demande explicite) : les taxes ne s'ajoutent plus au frais
        // deja configure dans la grille tarifaire — elles sont desormais prelevees SUR
        // ce montant (le frais affiche/configure est deja considere TTC), pas ajoutees
        // par-dessus. Le client est donc facture exactement le frais de la grille, ni
        // plus ni moins ; ttf/commission_cobac/tva/timbre_electronique et total_taxes
        // restent calcules et stockes pour la comptabilite/conformite (ce que
        // l'entreprise reverse aux impots sur ce frais), mais ne gonflent plus le
        // montant debite au client. Avant ce fix : frais_envoi_ttc = frais + taxes.
        $result['frais_envoi_ttc'] = $fraisService;

        return $result;
    }

    /**
     * Recupere les taxes actives, en priorisant celles specifiques a la zone
     * fournie et en retombant sur les taxes globales (zone_id null) sinon.
     *
     * @param int|null $zoneId
     * @return \Illuminate\Support\Collection
     */
    private function getApplicableTaxes(?int $zoneId)
    {
        $query = Tax::where('statut', true);

        if ($zoneId) {
            $zoneTaxes = (clone $query)->where('zone_id', $zoneId)->get();
            if ($zoneTaxes->count() > 0) {
                return $zoneTaxes;
            }
        }

        return $query->whereNull('zone_id')->get();
    }
}
