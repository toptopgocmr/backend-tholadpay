<?php

namespace App\Api\V1\Requests;



use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class RetailOuletRequest extends FormRequest
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
            'name'=>'required|min:0|max:255',
            'description'=>'min:0|max:255',
            'town_id'=>'required|integer|exists:towns,id',
            'rue'=>'min:0|max:255',
            'status'=>'required|min:0|max:255'
        ];

        return RuleHelper::get_rules($this->method(),$rules);
    }
}
