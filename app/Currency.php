<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    //

    use RestTrait;


    protected $fillable = ['code', 'symbol', 'rate', 'fees'];

    protected $dates = ['created_at','updated_at'];

    public function getLabel()
    {
        return $this->code ;
    }
}
