<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\UserRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\RoleUser;
use App\User;
use Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * @group User
 *
 * Controller allowing user to get connected, to create session
 * Class UserController
 * @package App\Api\V1\Controllers
 */
class UserController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.auth', ['except' => ['store', 'index', 'show']]);
    }


    public function index()
    {
        return RestHelper::get(User::class);
    }

    /**
     * Store a newly created town in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UserRequest $request)
    {
        $response = RestHelper::store(User::class, $request->all());

        // FIX (2026-08-24, incident "Ce numéro existe dans le système mais n'a
        // pas de compte client actif.") : le mobile (transaction.page.ts::
        // addUser(), Stratégie 2 "POST /api/users") envoie 'role_id' (4,
        // "customer") directement dans le corps de cette requête, en pensant
        // que ça suffit à assigner le rôle. Mais 'role_id' n'est PAS dans
        // $fillable de App\User (voir app/User.php) : RestHelper::pre_store()
        // filtre le payload sur $m->getFillable() et ignore silencieusement
        // tout champ absent de cette liste (aucune erreur renvoyée). Le rôle
        // vit dans une table pivot séparée (role_user, voir RoleUserController/
        // app/RoleUser.php), jamais créée par ce endpoint. Conséquence :
        // TOUT compte créé via ce flux mobile se retrouvait sans rôle, et
        // transaction.page.ts::findSenderF1() bloquait ensuite systématiquement
        // avec "... n'a pas de compte client actif" au prochain envoi — pas un
        // cas isolé de données, un bug reproductible à 100% pour tout nouveau
        // client inscrit via le mobile.
        //
        // On complète donc ici la création : si 'role_id' est fourni, on crée
        // explicitement la ligne role_user correspondante pour l'utilisateur
        // qui vient d'être créé, plutôt que de compter sur un correctif mobile
        // (qui exigerait un nouveau build APK pour prendre effet). 'user_type'
        // suit la convention Laratrust déjà utilisée par App\User (voir
        // LaratrustUserTrait) : le nom de classe complet du modèle User.
        $roleId = $request->get('role_id');
        if ($roleId) {
            try {
                $created = json_decode($response->getContent());
                $userId = $created->id ?? null;
                if ($userId) {
                    $alreadyLinked = RoleUser::where('user_id', $userId)
                        ->where('role_id', $roleId)
                        ->first();
                    if (!$alreadyLinked) {
                        $roleUser = new RoleUser();
                        $roleUser->user_id = $userId;
                        $roleUser->role_id = $roleId;
                        $roleUser->user_type = User::class;
                        $roleUser->save();
                    }
                }
            } catch (\Exception $e) {
                // Ne bloque jamais la création du compte pour un souci
                // d'assignation de rôle (ex: role_id inexistant) : le compte
                // reste utilisable, seul le rattachement au rôle échoue, et on
                // trace l'anomalie pour investigation plutôt que de renvoyer
                // une erreur 500 sur un utilisateur déjà bien créé.
                Log::warning('[UserController::store] échec assignation role_user pour user créé : ' . $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Display the specified town.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(User::class, $id);
    }

    /**
     * Display the specified town.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function user_mobile($id)
    {
        return RestHelper::show(User::class, $id);
    }

    /**
     * Update the specified town in storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UserRequest $request, $id)
    {
        return RestHelper::update(User::class, $request->all(), $id);
    }

    /**
     * Remove the specified town from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $Model = User::class;
        $m = $Model::find($id);
        $m->delete();
        return Response::json($m, 200, [], JSON_NUMERIC_CHECK);
//        return RestHelper::destroy(User::class,$id);
    }

    /**
     * Get the authenticated User
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(Auth::guard()->user());
    }
}