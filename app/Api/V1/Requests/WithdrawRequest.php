<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class WithdrawRequest extends FormRequest
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
            'mobile_phone'=>'required|min:0|max:255',
            'code'=>'required|min:0|max:10',
            'amount'=>'required',
            'ranking'=>'required',
            'withdraw_status'=>'required',
            'status'=>'boolean',
            'user_id'=>'nullable|integer|exists:users,id',
            'justif_picture'=>'nullable|min:0|max:255'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
