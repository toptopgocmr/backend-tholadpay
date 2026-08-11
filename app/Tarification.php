<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Tarification extends Model
{
    //
    use RestTrait;


    protected $fillable = ['tarif_1', 'tarif_2', 'frais', 'status', 'zone_id'];

    protected $dates = ['created_at','updated_at'];

    // Champs calcules exposes automatiquement dans toute reponse JSON de ce
    // modele (liste /tarifications, recherche par zone utilisee par le
    // mobile, etc.) : frais hors taxes (identique a `frais`) et frais avec
    // toutes les taxes actives (TTF, Commission COBAC, TVA, Timbre
    // electronique). TTF et Commission COBAC etant assises sur le montant
    // transfere (qui varie entre tarif_1 et tarif_2), le frais TTC est
    // fourni en fourchette min/max plutot qu'en valeur unique.
    protected $appends = ['frais_ht', 'taxes_min', 'taxes_max', 'frais_ttc_min', 'frais_ttc_max'];

    /**
     * Cache statique des taxes actives, pour eviter une requete par ligne de
     * tarification lorsqu'une liste entiere est serialisee.
     *
     * @var \Illuminate\Support\Collection|null
     */
    private static $activeTaxesCache = null;

    public function getLabel()
    {
        return $this->frais ;
    }

    public function zone(){
        return $this->belongsTo(Zone::class);
    }

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }

    public function getFraisHtAttribute()
    {
        return (float) $this->frais;
    }

    public function getTaxesMinAttribute()
    {
        return $this->computeTaxesRange()['min'];
    }

    public function getTaxesMaxAttribute()
    {
        return $this->computeTaxesRange()['max'];
    }

    public function getFraisTtcMinAttribute()
    {
        return (float) $this->frais + $this->taxes_min;
    }

    public function getFraisTtcMaxAttribute()
    {
        return (float) $this->frais + $this->taxes_max;
    }

    /**
     * Calcule la fourchette (min/max) des taxes applicables a cette grille
     * tarifaire : min = taxes au bas de la tranche (tarif_1), max = taxes au
     * plafond de la tranche (tarif_2). Les taxes assises sur le frais
     * (ex. TVA) ou fixes (ex. Timbre electronique) sont identiques aux deux
     * bornes.
     *
     * @return array{min: float, max: float}
     */
    private function computeTaxesRange(): array
    {
        $tranche1 = (float) $this->tarif_1;
        $tranche2 = (float) $this->tarif_2;
        $frais = (float) $this->frais;
        $zoneId = $this->zone_id;

        $taxes = self::activeTaxes();
        $zoneTaxes = $zoneId ? $taxes->where('zone_id', $zoneId) : collect();
        $applicable = ($zoneId && $zoneTaxes->count() > 0) ? $zoneTaxes : $taxes->whereNull('zone_id');

        $min = 0.0;
        $max = 0.0;
        foreach ($applicable as $tax) {
            if ($tax->type === 'fixe') {
                $min += (float) $tax->valeur;
                $max += (float) $tax->valeur;
            } else {
                $taux = ((float) $tax->valeur) / 100;
                if ($tax->assiette === 'frais') {
                    $min += $frais * $taux;
                    $max += $frais * $taux;
                } else {
                    $min += $tranche1 * $taux;
                    $max += $tranche2 * $taux;
                }
            }
        }

        return ['min' => $min, 'max' => $max];
    }

    private static function activeTaxes()
    {
        if (self::$activeTaxesCache === null) {
            self::$activeTaxesCache = Tax::where('statut', true)->get();
        }
        return self::$activeTaxesCache;
    }
}
