<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class SenderRequest extends FormRequest
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
        $rules = [
            //'first_name'=>'required|min:0|max:255',
            //'last_name'=>'required|min:0|max:255',
            //'email'=>'',
            //'email'=>'email|max:255|unique:senders,email',
            //'mobile_phone'=>'required|max:25|unique:senders,mobile_phone',
            'cni_number'=>'required|max:255',
            'country'=>'min:0|max:255',
            'date_exp_id'=>'date',
            'type_id'=>'',
            // AJOUT (2026-08-27) : voir migration add_civil_status_to_senders_table.
            'civil_status'=>'nullable|in:Single,Married',
            'user_id'=>'',
            'sex'=>'',
            'valid',
            'cni_picture'=>'nullable|min:0|max:255',
            'justif_picture'=>'nullable|min:0|max:255',
            // Compte Personnel/Business DigitWace (doc §V) — voir migration
            // add_business_fields_to_senders_table. 'sender_type' facultatif ('P' par
            // défaut côté modèle) ; les champs business ne sont vérifiés à l'envoi que
            // dans OutboundController::ensureDigitwaceSenderCode (pas ici, pour ne pas
            // bloquer un sender Peex/personnel qui n'en a pas besoin).
            'sender_type'=>'nullable|in:P,B',
            'business_name'=>'nullable|max:255',
            'business_type'=>'nullable|max:255',
            'business_register_date'=>'nullable|date',
            'business_comment'=>'nullable|max:255',
            'email'=>'nullable|email|max:255',
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
