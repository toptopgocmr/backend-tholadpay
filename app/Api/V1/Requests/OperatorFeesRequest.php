<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class OperatorFeesRequest extends FormRequest
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
            'country'=>'required|min:0|max:255',
            'operator_code'=>'required|min:0|max:255',
            'operator_name'=>'required|min:0|max:25',
            'type'=>'required|min:0|max:25',
            'min'=>'required',
            'max'=>'required',
            'fees'=>'required'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
