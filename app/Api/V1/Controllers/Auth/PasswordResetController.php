<?php

namespace App\Api\V1\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\PasswordReset;
use App\Tarification;
use App\Transaction;
use App\User;
use App\Verification;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|exists:users,email',
        ]);

        if(sizeof(User::where('email','=', $request->email)->get()) > 0) {
            $user = User::where('email','=', $request->email)->first();
            $email = $request->email;
            $reset = Verification::create([
                'email' => $email,
                'type' => 'Email',
                'verifiable_id' => $user->id,
                'verifiable_type' => 'App\\User',
                'status' => 'Waiting',
                'code' => Str::random(10),
                // 'code' => str_random(10),
            ]);
            $token = $reset->code;

            Mail::send('emails.reset_link', compact('email', 'token'), function ($mail) use ($email) {
                $mail->to($email)
                    ->from('noreply@tholadpay.com')
                    ->subject('THOLADPAY : Password reset link');
            });
        }

        return response()->success(true);
    }

    public function sendSmsCode(Request $request)
    { // Envoyer un sms a un numero precis
        $rule = [
            'phone_number' => 'required|min:9|max:255'
        ];
        $validator = Validator::make($request->all(), $rule);
        if ($validator->fails()) {
            return response()->error($validator->errors(), 422);
        } else {
            $user = User::where('phone_number', '=', $request->phone_number)->firstOrFail();
            if ($user !== null) {
                $verify = Verification::generate_secure_code("phone_number");

                $reset = Verification::create([
                    'phone_number' => $request->phone_number,
                    'type' => 'sms',
                    'verifiable_id' => $user->id,
                    'verifiable_type' => 'App\\User',
                    'status' => 'Waiting',
                    'code' => $verify->code,
                ]);

                $token = $reset->code;

                // Envoi effectif du SMS via le canal configuré (Infobip)
                $reset->send_code($user->phone_number);

                return response()->success(compact('user', 'token'));

            } else {
                return response()->error(compact('user'));
            }
        }
    }

    public function sendSmsToPhoneNumber(Request $request)
    { // Envoyer un sms a un numero precis
        $txtFrom = $request->from;
        $txtTo = $request->to;
        $txtMessage = $request->text;
        $response = null;
        $data_json = [
            "messages" => [
                [
                    "from" => $txtFrom,
                    "destinations" => [
                        [
                            "to" => $txtTo
                        ]
                    ],
                    "text" => $txtMessage
                ]
            ]
        ];

        $data_json = json_encode($data_json, true); 
        $username = 'TholadPay';
        $password = 'Basile1308@';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Accept: application/json'));
        // curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        curl_setopt($ch, CURLOPT_URL, 'https://jklnk.api.infobip.com/sms/2/text/advanced'); // https://api.infobip.com/sms/1/text/single

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($ch);
        // curl_getinfo($ch);
        // $response;
        curl_close($ch);
        return response()->success(compact('response'));
    }

    public function getUserByEmail(Request $request){ // recuperer un user par son email
        $user = null;
        if(sizeof(User::where('email','=', $request->email)->get()) > 0) {
            $user = User::where('email','=', $request->email)->first();
        }
        return response()->success($user);
    }

    public function getUserbyPhoneNumber(Request $request){ // recuperer un user par son telephone
        $user = null;
        if(sizeof(User::where('phone_number','=', $request->phone_number)->get()) > 0) {
            $user = User::where('phone_number','=', $request->phone_number)->first();
        }
        return response()->success($user);
    }

    public function changeValuePasswordUser(Request $request){
        $user = User::where('email','=', $request->email)->first();
        $password = bcrypt($request->password);

        $request->password = $password;

        $data = [
            'password' => $password
        ];

        $user->update($data);
        return response()->success(compact('user'));
    }

    public function searchTarificationByZoneAndAmout(Request $request){ // rechercher un tarif en fonction de la zone et du montant
        $zoneID = $request->zone_id;
        $amount = $request->amount;
        
        $tarification = Tarification::orderBy('tarif_2','desc')
        ->where('zone_id','=', $zoneID)
        ->where('tarif_2','>=', $amount)
        ->where('tarif_1','<=', $amount)
        ->get();
        // dd($tarification);
        return response()->success(compact('tarification'));
    }

    public function searchAllTarificationByZone(Request $request){ // rechercher les tarifs en fonction de la zone
        $zoneID = $request->zone_id;
        
        $tarifications = Tarification::orderBy('frais','asc')->orderBy('tarif_1','asc')
        ->where('zone_id','=', $zoneID)
        ->where('status','=', 1)
        ->get();
        // dd($tarifications);
        return response()->success(compact('tarifications'));
    }

    public function getRanking(Request $request){ // rechercher le numero dordre
        // $dateJour = $request->day;

        $dateJour = @date('Y-m-d');
        // dd($dateJour);
        $transactions = Transaction::where('created_at', '>=', date('Y-m-d').' 00:00:00')->get();
        // dd($transactions);
        return response()->success(compact('transactions'));
    }

    public function verify(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'token' => 'required',
        ]);
        $check = PasswordReset::whereEmail($request->email)
        ->whereToken($request->token)
        ->first();

        if (!$check) {
            return response()->error('Email does not exist', 422);
        }
        return response()->success(true);
    }

    public function reset(Request $request)
    {
        $this->validate($request, [
            'email'    => 'required|email',
            'token'    => "required|exists:password_resets,token,email,{$request->email}",
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::whereEmail($request->email)->firstOrFail();
        $user->password = bcrypt($request->password);
        $user->save();

        //delete pending resets
        PasswordReset::whereEmail($request->email)->delete();

        return response()->success(true);
    }
}
