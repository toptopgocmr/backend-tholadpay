<?php

namespace App\Console\Commands;

use App\Role;
use App\RoleUser;
use App\Sender;
use Illuminate\Console\Command;

/**
 * Corrige les clients déjà approuvés côté admin (senders.status = 'approuved')
 * mais qui n'ont jamais reçu le rôle "customer" (table role_user), car
 * CustomerController::validateCustomer() ne créait pas cette ligne avant ce
 * correctif. Le mobile refuse toute transaction pour un numéro tant que ce
 * rôle n'existe pas, même si le client est bien "approuved" dans l'admin.
 *
 * Usage : php artisan customers:backfill-role
 */
class BackfillCustomerRoles extends Command
{
    protected $signature = 'customers:backfill-role';

    protected $description = "Attribue le rôle 'customer' (role_user) à tous les senders déjà approuvés qui ne l'ont pas encore";

    public function handle()
    {
        $role = Role::where('name', 'customer')->first();
        if (!$role) {
            $this->error("Aucun rôle nommé 'customer' trouvé dans la table roles. Rien à faire.");
            return 1;
        }

        $senders = Sender::where('status', 'approuved')->whereNotNull('user_id')->get();
        $this->info("{$senders->count()} sender(s) approuvé(s) trouvé(s). Rôle 'customer' = id {$role->id}.");

        $created = 0;
        $alreadyOk = 0;

        foreach ($senders as $sender) {
            $exists = RoleUser::where('user_id', $sender->user_id)
                ->where('role_id', $role->id)
                ->exists();

            if ($exists) {
                $alreadyOk++;
                continue;
            }

            RoleUser::create([
                'user_id' => $sender->user_id,
                'role_id' => $role->id,
                'user_type' => 'App\User',
            ]);
            $created++;
            $this->line("Rôle 'customer' attribué à user_id={$sender->user_id} (sender #{$sender->id}).");
        }

        $this->info("Terminé. {$created} rôle(s) créé(s), {$alreadyOk} déjà en place.");
        return 0;
    }
}
