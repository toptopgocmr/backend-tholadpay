<?php

namespace App\Api\V1\Requests;



use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class NoteRequest extends FormRequest
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
            'detail'=>'required|min:0|max:255',
            'status'=>'required|min:0|max:255',
            'user_id'=>'required|integer|exists:users,id',
            'verifiable_id'=>'required|required_with:verifiable_type|integer',
            'verifiable_type'=>'max:255',
        ];

        return RuleHelper::get_rules($this->method(),$rules);
    }
}
