<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\RoleUserRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\RoleUser;
use Illuminate\Support\Facades\Response;

/**
 * @group Auth
 * Role help define privilege to allow user to have certain control over certain specific actions
 * This class is intended to manage all action related to Role resource
 * Class RoleController
 * @package App\Api\V1\Controllers
 */
class RoleUserController extends Controller
{

    public function index(){
        return RestHelper::get(RoleUser::class);
    }

    /**
     * Store a newly created role in storage.
     *
     * @param RoleUserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(RoleUserRequest $request)
    {
        $this->validate(
            $request, [
                'user_id'=>'required|integer|exists:users,id',
                'role_id'=>'required|integer|exists:roles,id',
                'user_type' => 'required'
            ]
        );
        $role_user = RoleUser::where('user_id', $request->get('user_id'))
            ->where('role_id', $request->get('role_id'))
            ->first();
        if ($role_user)
            return Response::json(["message" => "Object already exists", "role_user" => $role_user], 500);
        $role_user = new RoleUser;
        $role_user->user_id = $request->get('user_id');
        $role_user->role_id = $request->get('role_id');
        $role_user->user_type = $request->get('user_type');
        $role_user->save();

        return Response::json($role_user, 200);
    }

    /**
     * Display the specified role.
     *
     * @param  int $role_id
     * @param $user_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($role_id, $user_id)
    {
        $role_user = $role_user = RoleUser::where('user_id', $user_id)
            ->where('role_id', $role_id)
            ->first();

        if ($role_user)
            return Response::json($role_user, 200);
        else
            return Response::json(["message" => "Object not found"], 404);
    }

    /**
     * Update the specified role in storage.
     *
     * @param RoleUserRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(RoleUserRequest $request,$id)
    {
        return RestHelper::update(RoleUser::class,$request->all(),$id);
    }

    /**
     * Remove the specified role from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($role_id, $user_id)
    {
        $role_user = $role_user = RoleUser::where('user_id', $user_id)
            ->where('role_id', $role_id)
            ->first();

        if ($role_user){
            $role_user->delete();
            return Response::json(["role_user" => $role_user, "message" => "Object has been deleted"], 200);
        }
        else{
            return Response::json(["message" => "Object not found role_id=". $role_id . " user_id=" . $user_id], 404);
        }
    }

}
