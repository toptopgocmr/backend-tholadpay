<?php

namespace App\Api\V1\Controllers;

use App\Address;
use App\Api\V1\Requests\AddressRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use Auth;


/**
 * @group Address
 *
 * This controller is used for the management of user's addresses
 * Class AddressController
 * @package App\Api\V1\Controllers
 */
class AddressController extends Controller{

    /**
     * Start action, use to show all addresses inside the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Address::class);
    }


    /**
     * Show the form for creating a new address.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Action to be execute to store a newly created address in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AddressRequest $request)
    {
        return RestHelper::store(Address::class,$request->all());
    }

    /**
     * Display the specified address. given the ID
     *
     * @param  int  $iduser
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Address::class,$id);
    }

    /**
     * Show the form for editing the specified address.
     *
     * @param  \App\Address  $address
     * @return \Illuminate\Http\Response
     */
    public function edit(Address $address)
    {
        //
    }

    /**
     * Update the specified address in databse.
     *
     * @param AddressRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AddressRequest $request, $id)
    {
        return RestHelper::update(Address::class,$request->all(),$id);
    }

    /**
     * Remove the specified address from database given his id.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Address::class,$id);
    }
}
