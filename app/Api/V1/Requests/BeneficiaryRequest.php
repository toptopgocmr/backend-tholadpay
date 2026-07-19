<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class BeneficiaryRequest extends FormRequest
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
            'first_name'=>'required|min:0|max:255',
            'last_name'=>'required|min:0|max:255',
            'phone_number'=>'required|min:0|max:25',
            'type'=>'required|min:0|max:25',
            'country'=>'required|min:0|max:75',
            'phone_number_credit'=>'min:0|max:25',
            'iban'=>'min:0|max:50',
            'short_code'=>'min:0|max:50',
            'bank_name'=>'min:0|max:150',
            'sender_id'=>'integer|exists:senders,id'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
