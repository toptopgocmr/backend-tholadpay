<?php

namespace App\Api\V1\Controllers;

use App\Agent;
use App\Image;
use App\Prefunding;
use App\Sender;
use App\User;
use App\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

/**
 * @group Image
 * This class intend to implement all actions around Image resource
 * Class ImageController
 * @package App\Api\V1\Controllers
 */
define('UPLOAD_DIR_IMAGE', 'uploads/digipay/');
class ImageController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $image = Image::all();
        return $this->sendResponse($image->toArray(), 'Image retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'cni_picture' => 'nullable|string',
            'justif_picture' => 'nullable|string',
            'justif_picture_withdraw' => 'nullable|string',
            'avatar_picture' => 'nullable|string',
            'logo_picture' => 'nullable|string',
            'user_id' => 'nullable|integer|exists:users,id',
            'sender_id' => 'nullable|integer|exists:senders,id',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'withdraw_id' => 'nullable|integer|exists:withdraws,id'
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }
        try {
            $cni_picture = isset($input['cni_picture']) ? $input['cni_picture'] : '';
            $justif_picture = isset($input['justif_picture']) ? $input['justif_picture'] : '';
            $justif_picture_withdraw = isset($input['justif_picture_withdraw']) ? $input['justif_picture_withdraw'] : '';
            $avatar_picture = isset($input['avatar_picture']) ? $input['avatar_picture'] : '';
            $logo_picture = isset($input['logo_picture']) ? $input['logo_picture'] : '';
            $prove_picture = isset($input['prove_picture']) ? $input['prove_picture'] : '';
            $user_id = isset($input['user_id']) ? $input['user_id'] : '';
            $sender_id = isset($input['sender_id']) ? $input['sender_id'] : '';
            $agent_id = isset($input['agent_id']) ? $input['agent_id'] : '';
            $prefunding_id = isset($input['prefunding_id']) ? $input['prefunding_id'] : '';
            $withdraw_id = isset($input['withdraw_id']) ? $input['withdraw_id'] : '';
            if ($cni_picture && $cni_picture !== null && $cni_picture !== '') {
                $image = new Image();
                $pos = strpos($cni_picture, ';');
                $mime = explode(':', substr($cni_picture, 0, $pos))[1];
                $type = explode('/', $mime)[1];
                $ig = str_replace('data:image/' . $type . ';base64,', '', $cni_picture);
                $ig = str_replace(' ', '+', $ig);
                $data = base64_decode($ig);
                $file = uniqid() . '.' . $type;
                $file = UPLOAD_DIR_IMAGE . '' . $file;
                $storage = Storage::disk('public')->put($file, $data, 'public');
                $image->name = $file;
                $image->alt = 'cni_picture';
                $image->path = 'uploads/digipay/';
                $image->sender_id = $sender_id;
                $sender = Sender::find($sender_id);
                $sender->cni_picture = $file;
                $image->save();
                $sender->save();
            }
            if ($justif_picture && $justif_picture !== null && $justif_picture !== '') {
                $image = new Image();
                $pos = strpos($justif_picture, ';');
                $mime = explode(':', substr($justif_picture, 0, $pos))[1];
                $type = explode('/', $mime)[1];
                $ig = str_replace('data:image/' . $type . ';base64,', '', $justif_picture);
                $ig = str_replace(' ', '+', $ig);
                $data = base64_decode($ig);
                $file = uniqid() . '.' . $type;
                $file = UPLOAD_DIR_IMAGE . '' . $file;
                $storage = Storage::disk('public')->put($file, $data, 'public');
                $image->name = $file;
                $image->alt = 'justif_picture';
                $image->path = 'uploads/digipay/';
                $image->sender_id = $sender_id;
                $sender = Sender::find($sender_id);
                $sender->justif_picture = $file;
                $image->save();
                $sender->save();
            }
            if ($justif_picture_withdraw && $justif_picture_withdraw !== null && $justif_picture_withdraw !== '') {
                $image = new Image();
                $pos = strpos($justif_picture_withdraw, ';');
                $mime = explode(':', substr($justif_picture_withdraw, 0, $pos))[1];
                $type = explode('/', $mime)[1];
                $ig = str_replace('data:image/' . $type . ';base64,', '', $justif_picture_withdraw);
                $ig = str_replace(' ', '+', $ig);
                $data = base64_decode($ig);
                $file = uniqid() . '.' . $type;
                $file = UPLOAD_DIR_IMAGE . '' . $file;
                $storage = Storage::disk('public')->put($file, $data, 'public');
                $image->name = $file;
                $image->alt = 'justif_picture_withdraw';
                $image->path = 'uploads/digipay/';
                $image->withdraw_id = $withdraw_id;
                $withdraw = Withdraw::find($withdraw_id);
                $withdraw->justif_picture = $file;
                $image->save();
                $withdraw->save();
            }
            if ($avatar_picture && $avatar_picture !== null && $avatar_picture !== '') {
                $d = explode( ',', $avatar_picture );
                $pos = strpos($avatar_picture, ';');
                $mime = explode(':', substr($avatar_picture, 0, $pos))[1];
                $type = explode('/', $mime)[1];
                $ig = str_replace('data:image/' . $type . ';base64,', '', $avatar_picture);
                $ig = str_replace(' ', '+', $ig);
                $data = base64_decode($d[1]);
                $file = uniqid() . '.' . $type;
                $file = UPLOAD_DIR_IMAGE . '' . $file;
                $storage = Storage::disk('public')->put($file, $data, 'public');
                $image = new Image();
                $image->name = $file;
                $image->alt = 'picture';
                $image->path = 'uploads/digipay/';
                $image->user_id = $user_id;
                $user = User::find($user_id);
                $user->picture = $file;
                $image->save();
                $user->save();
            }
            if ($logo_picture && $logo_picture !== null && $logo_picture !== '') {
                $image = new Image();
                $pos = strpos($logo_picture, ';');
                $mime = explode(':', substr($logo_picture, 0, $pos))[1];
                $type = explode('/', $mime)[1];
                $ig = str_replace('data:image/' . $type . ';base64,', '', $logo_picture);
                $ig = str_replace(' ', '+', $ig);
                $data = base64_decode($ig);
                $file = uniqid() . '.' . $type;
                $file = UPLOAD_DIR_IMAGE . '' . $file;
                $storage = Storage::disk('public')->put($file, $data, 'public');
                $image->name = $file;
                $image->alt = 'logo';
                $image->path = 'uploads/digipay/';
                $image->agent_id = $agent_id;
                $user = Agent::find($agent_id);
                $user->logo = $file;
                $image->save();
                $user->save();
            }
            if ($prove_picture && $prove_picture !== null && $prove_picture !== '') {
                $image = new Image();
                $pos = strpos($prove_picture, ';');
                $mime = explode(':', substr($prove_picture, 0, $pos))[1];
                $type = explode('/', $mime)[1];
                $ig = str_replace('data:image/' . $type . ';base64,', '', $prove_picture);
                $ig = str_replace(' ', '+', $ig);
                $data = base64_decode($ig);
                $file = uniqid() . '.' . $type;
                $file = UPLOAD_DIR_IMAGE . '' . $file;
                $storage = Storage::disk('public')->put($file, $data, 'public');
                $image->name = $file;
                $image->alt = 'logo';
                $image->path = 'uploads/digipay/';
                $image->prefunding_id = $prefunding_id;
                $pref = Prefunding::find($prefunding_id);
                $pref->prove = $file;
                $image->save();
                $pref->save();
            }
            return $this->sendResponse(true, 'Image created successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Creation Error.', $exception->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        $image = Image::find($id);

        if (is_null($image)) {
            return $this->sendError('Image not found.');
        }

        return $this->sendResponse($image->toArray(), 'Image retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, Image $image)
    {
        $input = $request->all();

        return $this->sendResponse($image->toArray(), 'Image updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy(Image $image)
    {
        try {
            $image->delete();
        } catch (\Exception $exception) {
            return $this->sendError('Migration Error.', $exception->getMessage());
        }
        return $this->sendResponse($image->toArray(), 'Image deleted successfully.');
    }
}
