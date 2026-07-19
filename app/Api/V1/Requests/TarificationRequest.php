<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class TarificationRequest extends FormRequest
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
            'tarif_1'=>'required',
            'tarif_2'=>'required',
            'frais'=>'required',
            'zone_id'=>'integer|exists:zones,id'
        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
