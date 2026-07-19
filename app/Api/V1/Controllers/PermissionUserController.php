<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\PermissionUserRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\PermissionUser;
use Illuminate\Support\Facades\Response;

/**
 * @group Auth
 * Role help define privilege to allow user to have certain control over certain specific actions
 * This class is intended to manage all action related to Role resource
 * Class RoleController
 * @package App\Api\V1\Controllers
 */
class PermissionUserController extends Controller
{

    public function index(){
        return RestHelper::get(PermissionUser::class);
    }

    /**
     * Store a newly created role in storage.
     *
     * @param PermissionUserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PermissionUserRequest $request)
    {
        $this->validate(
            $request, [
                'user_id'=>'required|integer|exists:users,id',
                'permission_id'=>'required|integer|exists:permissions,id',
                'user_type' => 'required'
            ]
        );

        $permission_user = new PermissionUser;
        $permission_user->user_id = $request->get('user_id');
        $permission_user->permission_id = $request->get('permission_id');
        $permission_user->user_type = $request->get('user_type');
        $permission_user->save();

        return Response::json($permission_user, 200);
    }

    /**
     * Display the specified role.
     *
     * @param  int $role_id
     * @param $user_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($permission_id, $user_id)
    {
        $permission_user = PermissionUser::where(['user_id', '=', $user_id],
            ['permission_id', '=', $permission_id])->first();

        if ($permission_user)
            return Response::json($permission_user, 200);
        else
            return Response::json($permission_user, 404);
    }

    /**
     * Update the specified role in storage.
     *
     * @param PermissionUserRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PermissionUserRequest $request,$id)
    {
        return RestHelper::update(PermissionUser::class,$request->all(),$id);
    }

    /**
     * Remove the specified role from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(PermissionUser::class,$id);
    }

}
