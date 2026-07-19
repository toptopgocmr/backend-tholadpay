<?php

namespace App;

use App\Traits\RestTrait;
use Ghanem\Rating\Traits\Ratingable;
use Hash;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laratrust\Traits\LaratrustUserTrait;
use Setting;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use RestTrait;
    use LaratrustUserTrait;
    use Notifiable;
    use Ratingable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email', 'phone_number','failed_password_attemps','is_active', 'status', 'first_name', 'last_name', 'country',
        'password', 'last_login', 'remember_token', 'picture', 'admin_id'
    ];

    public $appends=['full_name','all_status'];
//    public $appends=['ratingPercent'];

    public function  __construct(array $attributes = [])
    {
        $this->files = ['picture'];
        parent::__construct($attributes);
    }

    protected $dates = ['created_at','updated_at'];


    public function getPictureAttribute($val)
    {
        if($val==null){
            $val='default/img/person_picture.png';
        }
        return $val;
    }

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token'
    ];

    public static $Status = ['inactive', 'active', 'insolvable', 'banned'];



    public function getLabel()
    {
        return $this->email ;
    }

    public function getAllStatusAttribute(){
        return self::$Status;
    }

    /**
     * Automatically creates hash for the user password.
     *
     * @param  string  $value
     * @return void
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function user_roles(){
        return $this->hasMany(RoleUser::class);
    }

    public function getFullNameAttribute(){
        return $this->first_name.' '.$this->last_name;
    }

    public function agent(){
        return $this->hasOne(Agent::class);
    }

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }

    public function addresses(){
        return $this->hasMany(Address::class);
    }

    public function images(){
        return $this->hasMany(Image::class);
    }
}
