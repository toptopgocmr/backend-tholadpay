<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class PermissionUser extends Model
{
    //
    use RestTrait;

    protected $table = 'permission_user';
    public $timestamps = false;
    protected $fillable = ['user_id','permission_id','user_type'];

    public function getLabel()
    {
        return $this->permission_id ;
    }

    public function permission(){
        return $this->belongsTo(Permission::class);
		
    } public function users(){
        return $this->belongsTo(User::class);
    }

    public function role(){
        return $this->belongsTo(Role::class);
    }
}
