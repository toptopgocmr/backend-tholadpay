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
        $rules = [
            'remitance_purpose'=>'required|min:0|max:255',
            'mobile_phone'=>'required|min:0|max:255',
            'code'=>'required|min:0|max:10',
            'description'=>'',
            'transaction_id'=>'required|integer|exists:transactions,id'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
