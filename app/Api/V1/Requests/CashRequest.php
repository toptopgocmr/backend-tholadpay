<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class CashRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // FIX (2026-08-08) : ces règles bloquaient tout le nouvel usage (Cash
        // Pickup DigitWace, lié à outbound_id — voir App\Cash) puisqu'elles
        // rendaient obligatoires des champs (remitance_purpose/code/
        // transaction_id) propres à l'ancien usage 2020, jamais réellement
        // utilisé (aucun appelant). Assouplies sur le modèle de BankRequest/
        // MobileRequest ; receiver_city/security_question/security_answer
        // (requis par DigitWace) sont désormais les champs obligatoires.
        $rules = [
            'remitance_purpose'=>'',
            'mobile_phone'=>'',
            'code'=>'',
            'description'=>'',
            'transaction_id'=>'integer|exists:transactions,id',
            'receiver_city'=>'required|string|max:255',
            'security_question'=>'required|string|max:255',
            'security_answer'=>'required|string|max:255',
            'outbound_id'=>'integer|exists:outbounds,id',
            'inbound_id'=>'integer|exists:inbounds,id'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
