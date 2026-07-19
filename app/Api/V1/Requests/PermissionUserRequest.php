<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use App\Http\Requests\Request;
use Dingo\Api\Http\FormRequest;

class PermissionUserRequest extends FormRequest
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
            'permission_id'=>'required|integer|exists:permissions,id',
            'user_id'=>'required|integer|exists:users,id',
            'user_type' => 'required'

        ];


        return RuleHelper::get_rules($this->method(), $rules,[]);
    }
}
