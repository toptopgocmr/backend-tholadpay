<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    //
    use RestTrait;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nom_commercial', 'solde', 'solde_utilisable', 'logo', 'agent_id', 'user_id', 'is_partner', 'retail_outlet_id'
    ];
    protected $dates = ['created_at', 'updated_at'];

    public $timestamps = false;

    public function getLabel()
    {
        return $this->nom_commercial;
    }

    public function prefundings()
    {
        return $this->hasMany((Prefunding::exists()) ? Prefunding::class : null);
    }

    public function agent()
    {
        return $this->belongsTo((Agent::exists()) ? Agent::class : null);
    }

    public function user()
    {
        return $this->belongsTo((User::exists()) ? User::class : null);
    }

    public function retail_outlet()
    {
        return $this->belongsTo((RetailOutlet::exists()) ? RetailOutlet::class : null);
    }

    public static function boot()
    {
        parent::boot();
    }
}
