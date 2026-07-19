<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class TransactionRequest extends FormRequest
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
            'recipient_first_name'=>'required|min:0|max:255',
            'recipient_last_name'=>'required|min:0|max:255',
            'receiving_country'=>'required|min:0|max:255',
            'transaction_reference'=>'required|min:0|max:255',
            'transaction_reason'=>'required|min:0|max:255',
            'transaction_status'=>'required|min:0|max:255',
            'ranking'=>'required|min:0|max:25',
            'recipient_phone'=>'required',
            'amount'=>'required',
            'fxrate'=>'',
            'montant_beneficiaire'=>'',
            'frais_envoi'=>'',
            'aml_cft'=>'',
            'from_currency'=>'required',
            'to_currency'=>'required',
            'sender_id'=>'integer|exists:senders,id',
            'user_id'=>'integer|exists:users,id'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
