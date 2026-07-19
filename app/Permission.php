<?php

namespace App;

use App\Traits\RestTrait;
use Laratrust\Models\LaratrustPermission;

class Permission extends LaratrustPermission
{
    //
    use RestTrait;

    protected $fillable = ['name','display_name','description'];

    protected $dates = ['created_at','updated_at'];

    public function getLabel()
    {
        return $this->name ;
    }
}
