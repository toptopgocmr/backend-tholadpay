<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class BankRequest extends FormRequest
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
            'bank_account_no'=>'required|min:0|max:255',
            'short_code'=>'',
            'organisation'=>'',
            'bank_address'=>'nullable|string',
            'outbound_id'=>'integer|exists:outbounds,id',
            'inbound_id'=>'integer|exists:inbounds,id'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
