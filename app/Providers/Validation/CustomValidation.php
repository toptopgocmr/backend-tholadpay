<?php
/**
 * Created by PhpStorm.
 * User: evari
 * Date: 11/11/2018
 * Time: 12:12 PM
 */

namespace App\Providers\Validation;


// It's important to extend the Validator class
// By doing that we make sure we keep all the other rules as well!
// Now if you want to override a rule you can do that here as well
// For now we will only focus on creating rules


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Validation\Validator;

class CustomValidation extends Validator
{
    // Laravel keeps a certain convention for rules
    // So the function is called validateGreaterThen
    // Then the rule is greater_then
    // A validation rule accepts three parameters
    // $attribute This is the name of the input
    // $value This is the value of the input
    // $parameters This is a parameter for the rule, so greater_then:1,2 has two parameters
    // the $parameters are returned as an array so for the first parameter: $parameters[0]
    // Now that we know how a rule works let's create one
    /**
     * $attribute Input name
     * $value Input value
     * $parameters Table, field1
     */
    public function __construct($translator, $data, $rules, $messages = array(), $customAttributes = array())
    {
        parent::__construct($translator, $data, $rules, $messages, $customAttributes);
        //$this->implicitRules[] = Str::studly('is_in_ticket_method');
    }
    public function validateUniqueWith($attribute, $value, $parameters)
    {
        // Now that we have our data we can check for the data
        // We first grab the correct table which is passed to the function
        // Now we need to do some checking using Eloquent
        // If you don't understand this, please let me know
        $table = array_shift($parameters);
        $val2 = Input::get($parameters[1]);
        $result = DB::table($table)->where(function ($query) use ($attribute, $value, $val2, $parameters) {
            $query->where($attribute, '=', $value)
                ->where($parameters[0], '=', $val2); // where lastname = value
        })->first();
        //dd($result);
        if ($result) {
            return count($parameters) == 3 and $result->id == $parameters[2] ? true : false;
        } else {
            return true;
        }
        //return !$result;
    }
    public function validateMacheable($attribute, $value, $parameters)
    {
        // Now that we have our data we can check for the data
        // We first grab the correct table which is passed to the function
        // Now we need to do some checking using Eloquent
        // If you don't understand this, please let me know
       return false;
        //return !$result;
    }
    public function validateMorphExists($attribute, $value, $parameters)
    {
        // Now that we have our data we can check for the data
        // We first grab the correct table which is passed to the function
        // Now we need to do some checking using Eloquent
        // If you don't understand this, please let me know
        if (!$objectType = array_get($this->getData(), $parameters[0], false)) {
            return false;
        }

        return !empty(resolve($objectType)->find($value));
    }
    // If you need more examples, let me know ;)
}