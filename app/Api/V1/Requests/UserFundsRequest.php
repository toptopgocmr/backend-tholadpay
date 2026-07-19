<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class UserFundsRequest extends FormRequest
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
            'daily'=>'required|numeric',
            'monthly'=>'required|numeric',
            'yearly'=>'required|numeric',
            'user_id'=>'required|integer|exists:users,id',
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
