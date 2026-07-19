<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use App\Http\Requests\Request;
use Dingo\Api\Http\FormRequest;

class UserRequest extends FormRequest
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
            'email'=>'required|email|max:255|unique:users,email',
            'phone_number'=>'required|max:255|unique:users,phone_number',
            'failed_password_attemps'=>'interger',
            'is_active'=>'boolean',
            'first_name'=>'required|min:0|max:255',
            'last_name'=>'required|min:0|max:255',
            'country'=>'max:10',
            'status'=>'max:255',
            'password'=>'required|min:4',
            'picture'=>'',
            'last_login'=>'date'

        ];

        if($this->method()=='PUT'){
            $rules['email'].=',' .$this->route('users');
            $rules['phone_number'].=',' .$this->route('users');
        }


        return RuleHelper::get_rules($this->method(),$rules);
    }
}
