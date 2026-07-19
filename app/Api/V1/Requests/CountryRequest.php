<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use App\Http\Requests\Request;
use Dingo\Api\Http\FormRequest;

class CountryRequest extends FormRequest
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
            'capital'=>'required|max:255',
            'citizenship'=>'required|max:255',
            'country_code'=>'required|max:255',
            'currency'=>'required|max:255',
            'currency_code'=>'required|max:255',
            'currency_sub_unit'=>'required|max:255',
            'currency_symbol'=>'required|max:255',
            'full_name'=>'required|max:255',
            'iso_3166_2'=>'required|max:2',
            'iso_3166_3'=>'required|max:3',
            'name'=>'required|max:255',
            'region_code'=>'required|max:3',
            'sub_region_code'=>'required|max:3',
            'eea'=>'required|integer|max:1',
            'calling_code'=>'required|max:3'

        ];
        return RuleHelper::get_rules($this->method(),$rules);
    }
}
