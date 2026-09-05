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
        $data = $request->all();
        $targetUser = User::find($id);
        if (!$targetUser) {
            return Response::json(['error' => "l'utilisateur demandé n'existe pas"], 422);
        }

        // AJOUT (2026-09-05, demande explicite) : qui a le droit de bloquer /
        // débloquer (is_active / status) quel profil. Avant ce correctif,
        // n'importe quel compte authentifié (même un simple caissier / PSA)
        // pouvait, en appelant directement cette route, changer le statut de
        // n'importe quel autre utilisateur — seul le panel admin filtrait ça
        // côté formulaire, jamais le backend.
        if (array_key_exists('is_active', $data) || array_key_exists('status', $data)) {
            $wantsReactivation = (array_key_exists('is_active', $data) && intval($data['is_active']) === 1)
                || (array_key_exists('status', $data) && intval($data['status']) === 1);

            $error = $this->authorizeStatusChange(Auth::user(), $targetUser, $wantsReactivation);
            if ($error) {
                return $error;
            }

            // Cas particulier (règle 4, demande explicite) : un compte
            // auto-verrouillé après 5 tentatives de connexion échouées ne peut
            // être réactivé QUE par le super admin (déjà vérifié ci-dessus) ;
            // quand c'est bien lui qui réactive, on remet le compteur à zéro
            // pour ne pas re-verrouiller le compte au prochain échec isolé.
            if ($wantsReactivation && $targetUser->failed_password_attemps >= 5) {
                $data['failed_password_attemps'] = 0;
            }
        }

        return RestHelper::update(User::class, $data, $id);
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
        if (!$m) {
            return Response::json(['error' => "l'utilisateur demandé n'existe pas"], 422);
        }

        // AJOUT (2026-09-05, demande explicite) : même règle d'autorisation que
        // pour le blocage (voir update() ci-dessus) — supprimer un compte est au
        // moins aussi sensible que le désactiver. Avant ce correctif, cette
        // route ne vérifiait que jwt.auth : n'importe quel profil connecté
        // pouvait supprimer définitivement n'importe quel autre utilisateur.
        $error = $this->authorizeStatusChange(Auth::user(), $m, false);
        if ($error) {
            return $error;
        }

        $m->delete();
        return Response::json($m, 200, [], JSON_NUMERIC_CHECK);
//        return RestHelper::destroy(User::class,$id);
    }

    /**
     * Détermine si $actingUser a le droit d'agir (bloquer/débloquer/supprimer)
     * sur $targetUser, selon la règle métier (demande explicite du 2026-09-05) :
     *   - super admin (rôle "administrator") : toujours autorisé.
     *   - finance_manager : autorisé sur les PMA (rôle "agent") et les PSA
     *     (rôle "cashier") uniquement.
     *   - agent (PMA) : autorisé uniquement sur les PSA (rôle "cashier") qu'il
     *     a lui-même créés (même hiérarchie d'agence, via agents.agent_id).
     *   - tout autre profil : jamais autorisé.
     *   - Réactivation d'un compte auto-verrouillé (5 échecs de connexion) :
     *     réservée au super admin, quel que soit le profil normalement
     *     autorisé à débloquer ce compte.
     *
     * @return \Illuminate\Http\JsonResponse|null null si autorisé, sinon la
     *         réponse d'erreur 403 à renvoyer telle quelle.
     */
    private function authorizeStatusChange($actingUser, User $targetUser, bool $wantsReactivation)
    {
        if (!$actingUser) {
            return Response::json(['error' => 'Non authentifié'], 401);
        }

        if ($actingUser->hasRole('administrator')) {
            return null;
        }

        if ($wantsReactivation && $targetUser->failed_password_attemps >= 5) {
            return Response::json([
                'error' => 'Ce compte a été verrouillé après 5 tentatives de connexion échouées : seul le super admin peut le réactiver'
            ], 403);
        }

        $targetRole = ($targetUser->roles && $targetUser->roles->first()) ? $targetUser->roles->first()->name : null;

        if ($actingUser->hasRole('finance_manager')) {
            if (in_array($targetRole, ['agent', 'cashier'])) {
                return null;
            }
            return Response::json(['error' => "Vous n'êtes pas autorisé à modifier ce profil"], 403);
        }

        if ($actingUser->hasRole('agent')) {
            $sameHierarchy = $targetRole === 'cashier'
                && $targetUser->agent
                && $actingUser->agent
                && $targetUser->agent->agent_id == $actingUser->agent->id;
            if ($sameHierarchy) {
                return null;
            }
            return Response::json(['error' => "Vous ne pouvez agir que sur les PSA que vous avez créés"], 403);
        }

        return Response::json(['error' => "Vous n'êtes pas autorisé à effectuer cette action"], 403);
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