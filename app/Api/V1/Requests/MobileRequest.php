<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class MobileRequest extends FormRequest
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
            'mobile_phone_credit'=>'required|min:0|max:255',
            'mobile_phone_debit'=>'',
            'outbound_id'=>'integer|exists:outbounds,id',
            'inbound_id'=>'integer|exists:inbounds,id'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
