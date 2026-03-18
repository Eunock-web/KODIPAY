<?php

namespace App\Console\Commands;

use App\Core\Payments\PaymentService;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReleaseEscrowFunds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kodipay:release-funds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Libère les fonds en séquestre après expiration du délai';

    /**
     * Execute the console command.
     */
    public function handle(PaymentService $paymentService)
    {
        $this->info('Vérification des fonds en séquestre...');

        //  Récupérer les transactions bloquées
        $transactions = Transaction::where('status', 'held')->get();

        foreach ($transactions as $transaction) {
            $expiresAt = Carbon::parse($transaction->metadata['expires_at'] ?? null);

            // Vérifier si le délai est dépassé
            if (now()->greaterThan($expiresAt)) {
                $this->warn("Libération de la transaction : {$transaction->id}");

                try {
                    // Initialiser le driver via le service
                    $driver = $paymentService->resolveDriver($transaction->gateway);

                    // Exécuter le virement (Payout)
                    // Note: 'destination' devrait être stocké dans les settings ou metadata
                    $destination = $transaction->metadata['payout_destination'] ?? '00000000';

                    $driver->payout($transaction->amount, $transaction->currency, $destination);

                    //  Marquer comme terminé
                    $transaction->update(['status' => 'completed']);

                    $this->info("Succès pour la transaction {$transaction->id}");
                } catch (\Throwable $e) {
                    $attempts = ($transaction->metadata['payout_attempts'] ?? 0) + 1;
                    if ($attempts >= 3) {
                        // Après 3 essais, on abandonne et on alerte
                        $transaction->update([
                            'status' => 'payout_failed',
                            'metadata' => array_merge($transaction->metadata, [
                                'payout_attempts' => $attempts,
                                'last_error' => $e->getMessage(),
                                'requires_manual_intervention' => true
                            ])
                        ]);
                        $this->error("Paiement ID {$transaction->id} définitivement échoué.");
                    } else {
                        // On incrémente juste le compteur, la commande réessaiera au prochain passage
                        $transaction->update([
                            'metadata' => array_merge($transaction->metadata, [
                                'payout_attempts' => $attempts,
                                'last_error' => $e->getMessage()
                            ])
                        ]);
                        $this->warn("Échec temporaire pour ID {$transaction->id} (Tentative $attempts/3)");
                    }
                }
            }
        }

        $this->info('Terminé !');
    }
}
