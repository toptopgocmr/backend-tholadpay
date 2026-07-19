<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Settingapp extends Model
{
    use RestTrait;

    protected $fillable = ['name', 'code1', 'code2'];

    protected $dates = ['created_at','updated_at'];
}
