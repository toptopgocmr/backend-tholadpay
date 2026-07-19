<?php

namespace App\Api\V1\Controllers\Auth;

use App\Device;
use App\Http\Controllers\Controller;
use App\Role;
use App\RoleUser;
use App\User;
use App\Verification;
use Auth;
use Browser;
use Carbon\Carbon;
use function Functional\true;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use App\Services\AppAccessChecker;
use JWTAuth;
use Mail;
use Setting;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AuthController extends Controller
{

    /**
     * Get authenticated user details and auth credentials.
     *
     * @return JSON
     */
    public function getAuthenticatedUser(){
        if (Auth::check()) {
            $user = Auth::user();
            $token = JWTAuth::fromUser($user);

            return response()->success(compact('user', 'token'));
        } else {
            return response()->error('unauthorized', 401);
        }
    }


    /**
     * Authenticate user.
     *
     * @param Instance Request instance
     *
     * @return JSON user details and auth credentials
     */
    public function login(Request $request){

        $credentials = $request->only('email', 'password');
        $email=$credentials["email"];
        if($email==null)
            return response()->json(['error' => 'missing email'], 403);
        $validator = Validator::make(['email'=>$email], ['email'=>'email']);
        if($validator->fails()){
            $user= User::where("phone_number","=",$email)->first();
        }else{
            $user = User::where('email', '=', $email)->first();
        }
        // dd($user);

        if(!isset($user))
            return response()->error('Identifiant ou mot de passe incorrect', 401);

        $credentials['email'] = $user->email;

        if (!AppAccessChecker::isAppActive()) {
            return response()->error('Erreur lors de la configuration système', 403);
        }
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->error(trans('auth.failed'), 401);
            }
        } catch (\JWTException $e) {
            return response()->error('Could not create token', 500);
        }

        $user = Auth::user();
        $token = JWTAuth::fromUser($user);

        $abilities = $this->getRolesAbilities();
        $userRole = [];

        foreach ($user->roles as $role) {
            $userRole [] = $role->name;
        }

        return response()->success(compact('user', 'token','abilities', 'userRole'));
    }

    public function setPinCode(Request $request){
        $user = Auth::user();
        $rule = [
            'pin_code' => 'required|min:4|max:20'
        ];

        $validator = Validator::make($request->all(), $rule);

        if ($validator->fails()) {
            return response()->error($validator->errors(), 422);
        }
        $user->pin_code = trim($request->pin_code);

        $user->save();
        return response()->success(compact('user'));
    }

    public function updateMe(Request $request){
        $rule = [
        ];

        if( $request->email !=null ){
            $rule['email'] = 'required|email|unique:users,email';
        }
        $validator = Validator::make($request->all(), $rule);

        if($validator->fails()){
            return Response::json($validator->errors(), 422);
        }else{
            $user = Auth::user();

            if(isset($request->settings)){
                foreach ($request->settings as  $key=>$val){
                    Setting::set($key,$val, $user->id);
                }
                Setting::save($user->id);
            }

            $user->update($request->all());
            return response()->success(compact('user'));
        }
    }

    public function updatePassword(Request $request){

        $user = User::where('email','=', $request->email)->first();
        if(isset($request->settings)){
            foreach ($request->settings as  $key=>$val){
                Setting::set($key,$val, $user->id);
            }
            Setting::save($user->id);
        }

        $user->update($request->all());
        return response()->success($user);
    }

    public function refresToken(){
        $token = JWTAuth::getToken();

        if (! $token) {
            throw new BadRequestHttpException('Token not provided');
        }

        try {
            $token = JWTAuth::refresh($token);
        } catch (TokenInvalidException $e) {
            throw new AccessDeniedHttpException('The token is invalid');
        }

        return response()->success(compact('token'));
    }


    public function oauth_login(Request $request){
        $rule = [
            'first_name'       => 'min:3|max:255',
            'last_name'       => 'min:3|max:255',
            'email'      => 'required|email',
            'provider_name'   => 'required',
            'oauth_id'=>'required'
        ];
        $isRegister= false;
        if($request->phone_number_number){
            $rule['phone_number'] = 'min:9|max:255';
        }


        $validator = Validator::make($request->all(), $rule);

        if($validator->fails()){
            return Response::json(['errors'=>$validator->errors()], 422);

        }else{
            // $verificationCode = str_random(40);
            $verificationCode = Str::random(40);
            $user = User::where("email","=",$request->email)->first();
            if(!$user){
                $isRegister=true;
                $validator = Validator::make($request->all(), ['phone_number'=>'required']);
                if($validator->fails()){
                    return Response::json(['is_register'=>true,'errors'=>$validator->errors()], 422);
                }

                $user = new User();
                $user->first_name = trim($request->first_name);
                $user->last_name = trim($request->last_name);
                $user->phone_number = $request->phone_number;
                $user->email = trim(strtolower($request->email));
                $user->settings = '';
                $user->password = $request->password;
                $user->save();

            }

            if($request->provider_name=='google'){
                if($user->google_oauth_id){
                    if($user->google_oauth_id!=$request->oauth_id)
                        return response()->error(trans('auth.failed'), 401);
                }else{
                    $user->google_oauth_id=$request->oauth_id;
                    $user->save();
                }
            }else if($request->provider_name=='facebook'){
                if($user->facebook_oauth_id){
                    if($user->facebook_oauth_id!=$request->oauth_id)
                        return response()->error(trans('auth.failed'), 401);
                }else{
                    $user->facebook_oauth_id=$request->oauth_id;
                    $user->save();
                }

            }else{
                return response()->error(trans('auth.failed'), 401);
            }
            $user = User::find($user->id);



            $token = JWTAuth::fromUser($user);

            $abilities = $this->getRolesAbilities();
            $userRole = [];

            foreach ($user->roles as $role) {
                $userRole [] = $role->name;
            }

            return response()->success(compact('user', 'token','abilities', 'userRole','isRegister'));
        }


    }

    /**
     * Login from phone number & pin code.
     *
     * @param Instance Request instance
     *
     * @return JsonResponse user details and auth credentials
     */
    public function loginWithPhoneNumberAndPinCode(Request $request)
    {

        $rule = [
            'phone_number'      => 'required|min:9|max:255',
            'pin_code' => 'required|min:4|max:20'
        ];

        $validator = Validator::make($request->all(), $rule);

        if($validator->fails()){
            return response()->error($validator->errors(), 422);

        }else{

            $user = User::where('phone_number', '=', $request->phone_number)->first();
            if ($user && Hash::check($request->pin_code, $user->pin_code)) {
                $device = $this->detect_device($user);
                if ($device->is_confirmed) {
                    $token = JWTAuth::fromUser($user);
                    $user->last_device_id = $device->id;
                    $user->last_login = Carbon::now();
                    $user->save();
                    return response()->success(compact('user', 'token', 'device'));
                } else {
                    return response()->error(trans('auth.device_banned'), 422);
                }

            }
            else {
                return response()->error(trans('auth.failed'), 422);
            }
        }
    }

    /**
     * Create new user from phone number.
     *
     * @param Instance Request instance
     *
     * @return JsonResponse user details and auth credentials
     */
    public function postRegisterWithPhoneNumber(Request $request)
    {

        $rule = [
            'phone_number'      => 'required|min:9|max:255|unique:users,phone_number',
            'settings' => 'array'
        ];

        $validator = Validator::make($request->all(), $rule);

        if($validator->fails()){
            return response()->error($validator->errors(), 422);

        }else{
            $user = new User();
            $user->phone_number = $request->phone_number;
            $user->settings = '';
            $user->save();
            if(isset($request->settings)){
                foreach ($request->settings as  $key=>$val){
                    Setting::set($key,$val, $user->id);
                }
                Setting::save($user->id);
            }

            $verify = Verification::generate_secure_code("phone_number");
            $verify->author_id = $user->id;
            $verify->save();
            $verify->send_code($user->phone_number);


            $token = JWTAuth::fromUser($user);
            if ( (env("APP_ENV") == "testing") ){
                $code = $verify->code;
                return response()->success(compact('user', 'token', 'code'));
            }else{
                return response()->success(compact('user', 'token'));
            }
        }
    }

    /**
     * Send verification code to user phone number.
     *
     * @param Instance Request instance
     *
     * @return JsonResponse user details and auth credentials
     */
    public function sendPhoneVerificationCode(Request $request){

        $rule = [
            'phone_number'      => 'required|min:9|max:255'
        ];

        $validator = Validator::make($request->all(), $rule);

        if($validator->fails()){
            return response()->error($validator->errors(), 422);

        }else{

            $user = User::where('phone_number', '=' ,$request->phone_number)
                ->firstOrFail();
            if (!$user->email) {
                $verify = Verification::generate_secure_code("phone_number");
                $verify->author_id = $user->id;
                $verify->save();
                $verify->send_code($user->phone_number);

                return response()->success(trans('auth.code_sent'));
            }else{
                return response()->error('The user has already been registered', 422);
            }
        }
    }

    /**
     * Send verification code to user in order to reset pincode.
     * @param Instance Request instance
     * @return JsonResponse user details and auth credentials
     */
    public function sendPhoneVerificationCode_toresetpincode(Request $request){

        $rule = [
            'phone_number' => 'required|min:9|max:255'
        ];

        $validator = Validator::make($request->all(), $rule);
        if($validator->fails()){
            return response()->error($validator->errors(), 422);

        }else{
            $user = User::where('phone_number', '=' ,$request->phone_number)
                ->firstOrFail();
            if ($user->email) {
                $verify = Verification::generate_secure_code("phone_number");
                $verify->author_id = $user->id;
                $verify->save();
                $verify->send_code($user->phone_number);
                return response()->success(trans('auth.code_sent'));
            }else{
                return response()->error('The user has not completed registration process', 422);
            }
        }
    }

    /**
     * Send verification code to user phone number.
     *
     * @param Instance Request instance
     *
     * @return JsonResponse user details and auth credentials
     */
    public function verifyCode(Request $request){

        $rule = [
            'code'      => 'required|min:6|max:12'
        ];

        $validator = Validator::make($request->all(), $rule);

        if($validator->fails()){
            return response()->error($validator->errors(), 422);

        }else{
            $verify = Verification::where('code', '=' ,$request->code)->where('status', '!=', 'done')->first();
            if($verify){
                // Log de diagnostic : on veut voir EXACTEMENT quelle ligne "verifications"
                // a matché ce code, et quel verifiable_id/type elle porte, avant le
                // firstOrFail() qui échoue actuellement en 404 "No query results for
                // model [App\User]." — ceci pointe vers un verifiable_id qui ne
                // correspond à aucun user existant (ligne erronée / user supprimé / bug
                // de création plus haut dans le flux).
                \Log::info('verifyCode: verification trouvée', [
                    'verification_id' => $verify->id,
                    'code' => $verify->code,
                    'status' => $verify->status,
                    'type' => $verify->type,
                    'verifiable_id' => $verify->verifiable_id,
                    'verifiable_type' => $verify->verifiable_type,
                ]);
                $verify->status = "done";
                $verify->save();
                // NOTE : la table verifications n'a pas de colonne 'author_id' (voir
                // migration create_verifications_table -> $table->morphs('verifiable')),
                // uniquement 'verifiable_id'/'verifiable_type'. 'author_id' vaut donc
                // toujours null, ce qui faisait échouer firstOrFail() en 404 pour
                // TOUT code OTP, même valide.
                $user = User::where('id', '=' ,$verify->verifiable_id)->firstOrFail();
                // save_device() référence App\Device, un modèle/table qui n'existe pas
                // dans ce projet (fonctionnalité jamais terminée) -> "Class App\Device
                // not found" et 500 systématique ici. On isole l'appel : un échec de ce
                // side-effect (tracking d'appareil) ne doit jamais bloquer la connexion
                // après OTP, qui a déjà réussi à ce stade.
                try {
                    $this->save_device($user);
                } catch (\Throwable $e) {
                    \Log::warning('save_device ignoré : ' . $e->getMessage());
                }
                $token = JWTAuth::fromUser($user);
                return response()->json(compact('user', 'token'));
            }
            else {
                return response()->error(trans('auth.code_invalid'), 422);
            }
        }
    }

    /**
     * Create new user.
     *
     * @param Instance Request instance
     *
     * @return JsonResponse user details and auth credentials
     */
    public function postRegister(Request $request)
    {

        $rule = [
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|min:9|max:255|unique:users,phone_number',
            'first_name'=>'required|min:3|max:255',
            'last_name'=>'required|min:3|max:255',
            'role_id'=>'',
            'country'=>'',
            'country_code'=>'',
            'password'   => 'required|min:5'
        ];
        $validator = Validator::make($request->all(), $rule);
        if($validator->fails()){
            return Response::json($validator->errors(), 422);
        } else {

            if (!AppAccessChecker::isAppActive()) {
                return response()->error('Erreur système !!', 403);
            }
            // $verificationCode = str_random(40);
            $verificationCode = Str::random(40);
            $user = new User();
            $user->phone_number = $request->country_code . $request->phone_number;
            $user->email = trim(strtolower($request->email));
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->password = $request->password;
            $user->country = $request->country;
            $user->is_active = (isset($request->is_active) ? $request->is_active : 1);
            $user->status = $request->status ?? 'active';
            $user->save();
            if(isset($request->settings)){
                foreach ($request->settings as  $key=>$val){
                    Setting::set($key,$val, $user->id);
                }
                Setting::save($user->id);
            }
            // Enregistrement du role
            if(isset($request->role_id)){
                $ur = new RoleUser();
                $ur->user_id = $user->id;
                $ur->role_id = $request->role_id;
                $ur->user_type = 'App\User';
                $ur->save();
            }


            $token = JWTAuth::fromUser($user);

            return response()->success(compact('user', 'token'));
        }

    }


    public function putMe(Request $request)
    {
        $user = Auth::user();
        $rule = [
            'first_name'       => 'min:3|max:255',
            'last_name'       => 'min:3|max:255',
            'device_tokens'       => 'array',
            'email'      => 'required|email|unique:users,email,'.$user->id,
            'password'   => 'min:5|confirmed'
        ];
        if($request->phone_number!=null){
            $rule['phone_number'] = 'min:9|max:255|unique:users,phone_number,'.$user->id;
        }
        if($request->password){
            $rule['current_password'] = 'required|min:5';
        }

        $validator = Validator::make($request->all(), $rule);

        if($validator->fails()){
            return response()->error($validator->errors(), 422);
        }



        $user->first_name = trim($request->first_name);
        $user->last_name = trim($request->last_name);
        $user->phone_number = $request->phone_number;
        $user->device_tokens = $request->device_tokens;
        $user->email = trim(strtolower($request->email));



        if ($request->has('current_password')or $request->has('password')) {
            Validator::extend('hashmatch', function ($attribute, $value, $parameters) {
                return Hash::check($value, Auth::user()->password);
            });

            $rules = [
                'current_password' => 'required|hashmatch:data.current_password',
                'password' => 'required|min:5|confirmed',
                'password_confirmation' => 'required|min:5',
            ];

            $payload = $request->only('current_password', 'password', 'password_confirmation');

            $messages = [
                'hashmatch' => 'Invalid Password',
            ];

            $validator = app('validator')->make($payload, $rules, $messages);

            if ($validator->fails()) {
                return response()->error($validator->errors(), 422);
            } else {
                $user->password = Hash::make($request['password']);
            }
        }
        if(isset($request->settings)){
            foreach ($request->settings as  $key=>$val){
                Setting::set($key,$val, $user->id);
            }
            Setting::save($user->id);
        }

        $user->save();
        return response()->success(compact('user'));
    }

    /**
     * Get all roles and their corresponding permissions.
     *
     * @return array
     */
    private function getRolesAbilities()
    {
        $abilities = [];
        $roles = Role::all();

        foreach ($roles as $role) {
            if (!empty($role->name)) {
                $abilities[$role->name] = [];
                $rolePermission = $role->permissions()->get();

                foreach ($rolePermission as $permission) {
                    if (!empty($permission->name)) {
                        array_push($abilities[$role->name], $permission->name);
                    }
                }
            }
        }

        return $abilities;
    }

    private function detect_device(User $user)
    {
        $f = Browser::detect();
        $n = $f->deviceFamily() . " " . $f->deviceModel();
        $des = $n . " " . $f->platformName() . " " . $f->platformFamily() . " " . $f->platformVersion();
        // str_slug() est un helper global supprimé depuis Laravel 7 (projet en Laravel 8,
        // paquet laravel/helpers absent) : l'appeler levait une erreur fatale
        // "Call to undefined function str_slug()", provoquant le 500 après vérification OTP.
        $tag = \Illuminate\Support\Str::slug($des);
        $d = $user->devices()->where("tag", $tag)->first();
        if ($d == null) {
            if ($f->isMobile()) {
                $typ = "mobile";
            } elseif ($f->isDesktop()) {
                $typ = "desktop";
            } elseif ($f->isTablet()) {
                $typ = "tablet";
            } elseif ($f->isBot()) {
                $typ = "bot";
            } else {
                $typ = 'bot';
            }
            $d1 = Device::create([
                'tag' => $tag,
                'name' => $n,
                'description' => $des,
                'type' => $typ,
                'user_id' => $user->id
            ]);
            return $d1;
        } else {
            return $d;
        }
    }

    private function save_device($user)
    {
        $f = Browser::detect();
        $n = $f->deviceFamily() . " " . $f->deviceModel();
        $des = $n . " " . $f->platformName() . " " . $f->platformFamily() . " " . $f->platformVersion();
        // str_slug() est un helper global supprimé depuis Laravel 7 (projet en Laravel 8,
        // paquet laravel/helpers absent) : l'appeler levait une erreur fatale
        // "Call to undefined function str_slug()", provoquant le 500 après vérification OTP.
        $tag = \Illuminate\Support\Str::slug($des);
        $is_notifiable = false;


        if ($f->isMobile()) {
            $typ = "mobile";
            $is_notifiable = true;
        } elseif ($f->isDesktop()) {
            $typ = "desktop";
        } elseif ($f->isTablet()) {
            $typ = "tablet";
        } elseif ($f->isBot()) {
            $typ = "bot";
        } else {
            $typ = 'bot';
        }
        if(!Device::whereTag($tag)->whereUserId($user->id)->exists()){
            $d = Device::create([
                'is_confirmed' => true,
                'is_notifiable' => $is_notifiable,
                'tag' => $tag,
                'name' => $n,
                'description' => $des,
                'type' => $typ,
                'user_id' => $user->id
            ]);
            return $d;
        }else{
            return Device::whereTag($tag)->whereUserId($user->id)->first();
        }
    }

}