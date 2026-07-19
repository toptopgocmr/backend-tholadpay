<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\AddressRequest;
use App\Api\V1\Requests\VerificationRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Verification;
use Auth;

/**
 * @group Verification
 * Class VerificationController
 * @package App\Api\V1\Controllers
 */
class VerificationController extends Controller
{

    public function index(){
        return RestHelper::get(Verification::class);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(VerificationRequest $request)
    {
        return RestHelper::store(Verification::class,$request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Verification::class,$id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param VerificationRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(VerificationRequest $request, $id)
    {
        return RestHelper::update(Verification::class,$request->all(),$id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Verification::class,$id);
    }

    /**
     * Function to verify phone number
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function phone_number(Request $request){
        // $user = Auth::user();
        $phone_id = $request->get('phone_number_id');
        $code = $request->get('code');

        $phoneNumber = PhoneNumber::find($phone_id);
        $verification = Verification::where('code', $code)
                                    ->where('verifiable_type', PhoneNumber::class)
                                    ->get();
        if ($verification != null){
            $phoneNumber->is_verified = true;
            $phoneNumber->save();
            return response()->json($phoneNumber);
        }else{
            return response()->json("Wrong code", 422);
        }
    }
}
