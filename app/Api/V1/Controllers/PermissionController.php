<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\PermissionRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Permission;


/**
 * @group permission
 *
 * In this class we try to gather all action around the permission resource
 * Class PermissionController
 * @package App\Api\V1\Controllers
 */
class PermissionController extends Controller
{
    /**
     * Entry point, used to list all permission inside the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Permission::class);
    }

    /**
     * Store a newly created permission in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PermissionRequest $request)
    {
        return RestHelper::store(Permission::class, $request->all());
    }

    /**
     * Display the specified permission given his id.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Permission::class,$id);
    }

    /**
     * Update the specified permission in storage given his id.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PermissionRequest $request,$id)
    {
        return RestHelper::update(Permission::class,$request->all(),$id);
    }

    /**
     * Remove the specified permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Permission::class,$id);
    }

}
