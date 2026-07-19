<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\RoleRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Role;

/**
 * @group Auth
 * Role help define privilege to allow user to have certain control over certain specific actions
 * This class is intended to manage all action related to Role resource
 * Class RoleController
 * @package App\Api\V1\Controllers
 */
class RoleController extends Controller
{

    public function index(){
        return RestHelper::get(Role::class);
    }

    /**
     * Store a newly created role in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(RoleRequest $request)
    {
        return RestHelper::store(Role::class, $request->all());
    }

    /**
     * Display the specified role.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Role::class,$id);
    }

    /**
     * Update the specified role in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(RoleRequest $request,$id)
    {
        return RestHelper::update(Role::class,$request->all(),$id);
    }

    /**
     * Remove the specified role from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Role::class,$id);
    }

}
