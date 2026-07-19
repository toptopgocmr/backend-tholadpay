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
            'user_id'=>'',
            'sex'=>'',
            'valid',
            'cni_picture'=>'nullable|min:0|max:255',
            'justif_picture'=>'nullable|min:0|max:255'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
