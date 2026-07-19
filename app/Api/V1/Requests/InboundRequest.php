<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class InboundRequest extends FormRequest
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
            'description'=>'',
            'transaction_id'=>'required|integer|exists:transactions,id'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
