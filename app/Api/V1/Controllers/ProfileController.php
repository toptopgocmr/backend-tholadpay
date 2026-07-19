<?php

namespace App\Api\V1\Controllers;

use App\Address;
use App\Api\V1\Requests\ProfileRequest;
use App\Http\Controllers\Controller;
use App\Person;
use Auth;
use Illuminate\Http\Request;

/**
 * @group Devise
 * Class ProfileController
 * @package App\Api\V1\Controllers
 */
class ProfileController extends Controller{

    public function index(Request $request){
        $person = $request->user()->person();
        if($person->first()){
            return response()->json($person->first());
        }
        else{
            return response()->json([
                'success' => 'user profile is not defined'
            ]);
        }
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ProfileRequest $request){
        $person = $request->user()->person();
        if(!$person->first()){
            $person->create($request->all());
            return response()->json($person->first());
        }
        else{
            $person->first()->update($request->all());
            $person->first()->save();
            return response()->json($person->first());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function update(ProfileRequest $request, $id){
        //
        $person = Person::findOrFail($id);
        $person->update($request->all());
        $person->save();
        return response()->json($person);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function destroy(Address $address){
        //
    }
}
