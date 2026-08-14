<?php

namespace App\Console\Commands;

use App\Services\TaxCalculationService;
use App\Tarification;
use App\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Calcule et enregistre a posteriori le detail des taxes CEMAC (TTF,
 * Commission COBAC, TVA, Timbre electronique) pour les transactions creees
 * AVANT le deploiement de cette fonctionnalite (voir migration
 * add_taxes_to_transactions_table, 2026-08-11, et TaxCalculationService).
 * Ces transactions ont ttf/commission_cobac/tva/timbre_electronique/
 * total_taxes/frais_envoi_ttc a 0 par defaut (valeur de la migration), pas
 * parce que ces taxes ne s'appliquaient pas, mais parce qu'elles n'etaient
 * simplement pas encore calculees a l'epoque.
 *
 * IMPORTANT : ce backfill utilise les TAUX ACTUELS de la table `taxes` (pas
 * les taux historiques a la date de la transaction, qui ne sont pas
 * conserves) — c'est une reconstitution a titre comptable/affichage, pas une
 * refacturation. Il ne touche JAMAIS a `amount` ni `frais_envoi` (montants
 * reellement factures au client a l'epoque) : voir le fix du 2026-08-13 dans
 * TaxCalculationService, frais_envoi_ttc = frais_envoi, les taxes sont
 * deduites de ce frais, jamais ajoutees par-dessus.
 *
 * Cible : les transactions dont total_taxes vaut 0 (heuristique fiable ici,
 * car Timbre electronique est un montant fixe non nul des qu'il est calcule
 * au moins une fois — total_taxes ne peut valoir 0 qu'en absence totale de
 * calcul, sauf si toutes les taxes ont ete desactivees entretemps, cas
 * couvert par --dry-run pour verification avant execution).
 *
 * Usage :
 *   php artisan transactions:backfill-taxes --dry-run   (rapport seul, aucune ecriture — a lancer en premier)
 *   php artisan transactions:backfill-taxes             (applique les changements, dans une transaction DB)
 */
class BackfillTransactionTaxes extends Command
{
    protected $signature = 'transactions:backfill-taxes {--dry-run : Affiche ce qui serait fait sans rien modifier}';

    protected $description = "Calcule et enregistre le detail des taxes CEMAC (TTF, Commission COBAC, TVA, Timbre electronique) pour les transactions creees avant le deploiement de cette fonctionnalite";

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? '--- MODE DRY-RUN (aucune ecriture) ---' : '--- MODE REEL (les changements seront appliques) ---');

        $transactions = Transaction::where('total_taxes', 0)->orderBy('id')->get();
        $this->line("{$transactions->count()} transaction(s) sans detail de taxes calcule (total_taxes = 0).");

        if ($transactions->count() === 0) {
            $this->info('Rien a faire.');
            return 0;
        }

        // Cache tarif_id -> zone_id pour eviter une requete par transaction.
        $zoneByTarifId = [];

        $service = new TaxCalculationService();
        $updated = 0;
        $skippedZeroBase = 0;

        DB::beginTransaction();
        try {
            foreach ($transactions as $transaction) {
                $tarifId = $transaction->tarif_id;
                $zoneId = null;
                if ($tarifId) {
                    if (!array_key_exists($tarifId, $zoneByTarifId)) {
                        $tarification = Tarification::find($tarifId);
                        $zoneByTarifId[$tarifId] = $tarification ? $tarification->zone_id : null;
                    }
                    $zoneId = $zoneByTarifId[$tarifId];
                }

                $montant = (float) $transaction->amount;
                $frais = (float) $transaction->frais_envoi;

                if ($montant <= 0 && $frais <= 0) {
                    // Rien sur quoi asseoir une taxe (transaction incomplete/annulee
                    // avant saisie du montant) : on ne force pas le timbre fixe seul
                    // sur une ligne sans substance economique, on la laisse a 0 et on
                    // la signale.
                    $skippedZeroBase++;
                    $this->line("  -> #{$transaction->id} ({$transaction->ranking}) : montant et frais a 0, ignoree");
                    continue;
                }

                $taxes = $service->calculate($montant, $frais, $zoneId);

                $this->line(sprintf(
                    '  -> #%d (%s) : ttf=%.2f cobac=%.2f tva=%.2f timbre=%.2f total=%.2f',
                    $transaction->id,
                    $transaction->ranking,
                    $taxes['ttf'],
                    $taxes['commission_cobac'],
                    $taxes['tva'],
                    $taxes['timbre_electronique'],
                    $taxes['total_taxes']
                ));

                if (!$dryRun) {
                    $transaction->ttf = $taxes['ttf'];
                    $transaction->commission_cobac = $taxes['commission_cobac'];
                    $transaction->tva = $taxes['tva'];
                    $transaction->timbre_electronique = $taxes['timbre_electronique'];
                    $transaction->total_taxes = $taxes['total_taxes'];
                    $transaction->frais_envoi_ttc = $taxes['frais_envoi_ttc'];
                    $transaction->save();
                }
                $updated++;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Erreur, rollback effectue (aucune modification appliquee) : ' . $e->getMessage());
            return 1;
        }

        $this->info('');
        $this->info('--- Resume ---');
        $this->info("Transactions mises a jour : {$updated}");
        if ($skippedZeroBase > 0) {
            $this->warn("Transactions ignorees (montant et frais a 0) : {$skippedZeroBase}");
        }
        if ($dryRun) {
            $this->info('');
            $this->info('Dry-run termine, AUCUNE modification appliquee. Relancer sans --dry-run pour appliquer.');
        }

        return 0;
    }
}
