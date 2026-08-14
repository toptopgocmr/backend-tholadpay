<?php

namespace App\Console\Commands;

use App\Address;
use App\Country;
use App\Town;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nettoie la table `towns` (villes) pour le Congo : des années de saisie
 * libre non contrôlée côté mobile (avant l'ajout d'une liste déroulante le
 * 2026-08-08, voir TransactionPage::addTown() dans mobile-tholadpay) ont
 * rempli cette table de doublons (casse/espaces différents : "Brazzaville",
 * " brazzaville", "BRAZZAVILLE ") et d'entrées qui ne sont même pas des
 * villes ("Congo", "Paris", "Rwanda", "Portugal"...), créées à chaque
 * transaction à partir du texte tapé par l'agent, sans normalisation ni
 * vérification.
 *
 * Deux opérations, TOUJOURS dans cet ordre de prudence :
 *
 *  1) Doublons d'une VRAIE ville (liste blanche ci-dessous, alignée sur
 *     congoTowns() dans
 *     mobile-tholadpay/src/app/services/global-functions/global-functions.service.ts) :
 *     ré-associe toutes les addresses des doublons vers UNE SEULE ligne
 *     canonique (la plus ancienne, id le plus petit), puis supprime les
 *     doublons devenus orphelins. Sûr : c'est réellement la même ville, donc
 *     ré-associer l'adresse d'un client à la ligne canonique ne change rien
 *     pour lui.
 *
 *  2) Entrées "garbage" (hors liste blanche) : supprimées UNIQUEMENT si
 *     aucune address ne les référence (ligne orpheline, sans impact réel).
 *     Toute entrée garbage encore référencée par une vraie address est
 *     laissée en l'état et seulement signalée dans le rapport — la supprimer
 *     entraînerait sa suppression EN CASCADE côté addresses (towns->addresses
 *     a onDelete('cascade'), voir migration create_addresses_table), et on ne
 *     devine pas à la place de l'agence quelle est la "bonne" ville pour
 *     cette adresse précise. Ces cas restent listés dans le rapport pour
 *     correction manuelle si besoin.
 *
 * Usage :
 *   php artisan towns:cleanup --dry-run   (rapport seul, aucune écriture — à lancer en premier)
 *   php artisan towns:cleanup             (applique les changements, dans une transaction DB)
 */
class CleanupTowns extends Command
{
    protected $signature = 'towns:cleanup {--dry-run : Affiche ce qui serait fait sans rien modifier}';

    protected $description = "Nettoie la table towns (Congo) : fusionne les doublons d'une vraie ville et supprime les entrées garbage orphelines";

    // Alignée sur congoTowns() (mobile-tholadpay/src/app/services/global-functions/global-functions.service.ts) —
    // si cette liste évolue côté mobile, la mettre à jour ici aussi.
    private const WHITELIST = [
        'Brazzaville', 'Pointe-Noire', 'Dolisie', 'Nkayi', 'Ouesso', 'Owando',
        'Sibiti', 'Impfondo', 'Madingou', 'Gamboma', 'Mossendjo', 'Kinkala',
        'Djambala', 'Ewo', 'Loandjili', 'Mindouli', 'Makoua', 'Sembé',
    ];

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? '--- MODE DRY-RUN (aucune écriture) ---' : '--- MODE RÉEL (les changements seront appliqués) ---');

        // Résolu dynamiquement (pas de country_id en dur) : plus robuste si les
        // ids diffèrent entre environnements (local/staging/prod).
        $country = Country::where('name', 'Congo')->orWhere('iso_3166_3', 'COG')->first();
        if (!$country) {
            $this->error("Aucun pays 'Congo' (iso_3166_3=COG) trouvé dans la table countries. Rien à faire.");
            return 1;
        }
        $this->line("Pays résolu : Congo (id={$country->id}).");

        $towns = Town::where('country_id', $country->id)->orderBy('id')->get();
        $this->line("{$towns->count()} ligne(s) dans towns pour ce pays avant nettoyage.");

        $whitelistLower = array_map('mb_strtolower', self::WHITELIST);

        // Regrouper par nom normalisé (trim + minuscules) pour repérer les doublons.
        $groups = [];
        foreach ($towns as $town) {
            $key = mb_strtolower(trim($town->name));
            $groups[$key][] = $town;
        }

        $mergedDuplicates = 0;
        $deletedOrphanGarbage = 0;
        $keptGarbageWithAddresses = [];

        DB::beginTransaction();
        try {
            foreach ($groups as $key => $rows) {
                $isWhitelisted = in_array($key, $whitelistLower, true);

                if ($isWhitelisted && count($rows) > 1) {
                    // 1) Doublons d'une vraie ville : fusionner sur la plus ancienne (id le plus petit).
                    usort($rows, function ($a, $b) {
                        return $a->id <=> $b->id;
                    });
                    $canonical = array_shift($rows);

                    // Normaliser la casse/l'orthographe du nom canonique sur la liste blanche
                    // (ex: "brazzaville" -> "Brazzaville").
                    $canonicalName = self::WHITELIST[array_search($key, $whitelistLower, true)];
                    if ($canonical->name !== $canonicalName) {
                        $this->line("  -> Renommage id={$canonical->id} : \"{$canonical->name}\" -> \"{$canonicalName}\"");
                        if (!$dryRun) {
                            $canonical->name = $canonicalName;
                            $canonical->save();
                        }
                    }

                    foreach ($rows as $dup) {
                        $count = Address::where('town_id', $dup->id)->count();
                        $this->line("  -> Fusion \"{$dup->name}\" (id={$dup->id}, {$count} adresse(s)) -> id={$canonical->id} (\"{$canonicalName}\")");
                        if (!$dryRun) {
                            Address::where('town_id', $dup->id)->update(['town_id' => $canonical->id]);
                            $dup->delete();
                        }
                        $mergedDuplicates++;
                    }
                } elseif (!$isWhitelisted) {
                    // 2) Garbage (hors liste blanche) : supprimer seulement si orpheline.
                    foreach ($rows as $row) {
                        $count = Address::where('town_id', $row->id)->count();
                        if ($count === 0) {
                            $this->line("  -> Suppression garbage orpheline \"{$row->name}\" (id={$row->id})");
                            if (!$dryRun) {
                                $row->delete();
                            }
                            $deletedOrphanGarbage++;
                        } else {
                            $keptGarbageWithAddresses[] = "\"{$row->name}\" (id={$row->id}, {$count} adresse(s))";
                        }
                    }
                }
                // else : ville blanche déjà unique -> rien à faire.
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Erreur, rollback effectué (aucune modification appliquée) : ' . $e->getMessage());
            return 1;
        }

        $this->info('');
        $this->info('--- Résumé ---');
        $this->info("Doublons de vraies villes fusionnés : {$mergedDuplicates}");
        $this->info("Entrées garbage orphelines supprimées : {$deletedOrphanGarbage}");
        if (count($keptGarbageWithAddresses) > 0) {
            $this->warn(count($keptGarbageWithAddresses) . " entrée(s) garbage CONSERVÉE(S) car référencée(s) par une vraie adresse (à traiter manuellement si besoin) :");
            foreach ($keptGarbageWithAddresses as $line) {
                $this->warn('  - ' . $line);
            }
        }
        if ($dryRun) {
            $this->info('');
            $this->info('Dry-run terminé, AUCUNE modification appliquée. Relancer sans --dry-run pour appliquer.');
        }

        return 0;
    }
}
