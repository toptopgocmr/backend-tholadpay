<?php

namespace App\Api\V1\Requests;

use App\Helpers\RuleHelper;
use Dingo\Api\Http\FormRequest;

class TaxRequest extends FormRequest
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
            'code'=>'required|max:255|unique:taxes,code',
            'libelle'=>'required|max:255',
            'type'=>'required|in:pourcentage,fixe',
            'valeur'=>'required|numeric',
            'assiette'=>'nullable|in:montant,frais',
            'zone_id'=>'integer|exists:zones,id',
            'statut'=>'boolean',
            'ordre'=>'integer',
        ];

        if($this->method()=='PUT'){
            $rules['code'].=',' .$this->route('taxes');
        }

        return RuleHelper::get_rules($this->method(),$rules);
    }
}
