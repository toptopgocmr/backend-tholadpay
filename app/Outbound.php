<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Outbound extends Model
{
    //
    use RestTrait;

    protected $fillable = ['remitance_purpose', 'description', 'country', 'transaction_id'];

    protected $dates = ['created_at','updated_at'];

    public function transaction(){
        return $this->belongsTo((Transaction::exists()) ? Transaction::class : null);
    }

    // FIX (2026-07-04) : la base contient d'anciennes lignes `mobiles`/`banks`
    // (2020) dont l'`outbound_id` entre en collision avec les nouveaux
    // `outbounds.id` générés depuis la remise à zéro de cette table pour les
    // tests — la table `mobiles`, elle, n'a pas été purgée et garde son propre
    // compteur auto-incrémenté (lignes avec id ~4900+). Résultat : PLUSIEURS
    // lignes `mobiles` partagent le même `outbound_id` (une très ancienne, une
    // récente), et hasOne() sans tri renvoyait souvent l'ancienne ligne (id le
    // plus bas) au lieu de la plus récente — d'où un numéro/indicatif de
    // bénéficiaire complètement étranger au test en cours, alors que la
    // transaction elle-même était correcte (voir §4.15/§4.17/§4.20 du rapport
    // d'intégration Peex). On force explicitement la ligne la PLUS RÉCENTE.
    public function mobile(){
        return $this->hasOne(Mobile::class)->latest('id');
    }

    public function bank(){
        return $this->hasOne(Bank::class)->latest('id');
    }

    public static function boot()
    {
        parent::boot();
    }
}
