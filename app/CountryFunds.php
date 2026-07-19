<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class CountryFunds extends Model
{
    //
    use RestTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id', 'daily', 'monthly', 'yearly'
    ];
    protected $dates = ['created_at', 'updated_at'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public static function boot()
    {
        parent::boot();
    }
}
