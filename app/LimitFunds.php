<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class LimitFunds extends Model
{
    //
    use RestTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id', 'zone_id', 'daily', 'monthly', 'yearly'
    ];
    protected $dates = ['created_at', 'updated_at'];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public static function boot()
    {
        parent::boot();
    }
}
