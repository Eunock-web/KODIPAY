<?php
namespace App\Gateways\Fedapay;

use App\Core\Payments\Contract\PaymentsGatewayInterface;
use FedaPay\Payout;
use FedaPay\Transaction;
use FedaPay\Webhook;

class FedapayDriver implements PaymentsGatewayInterface
{
    public function __construct(
        private string $publicKey,
        private string $privateKey,
        private bool $is_live,
        private ?string $webhookSecret = null
    ) {}

    public function makePayment(int $amount, string $currency, array $options = []): object
    {
        \FedaPay\FedaPay::setEnvironment($this->is_live ? 'live' : 'sandbox');

        // Sécurité : Vérifier si on n'a pas mis une public key à la place de la private key
        if (str_starts_with($this->privateKey, 'pk_')) {
            \Illuminate\Support\Facades\Log::error('FedaPay Configuration Error: Public Key found in Private Key field.');
            return (object) ['status' => 'failed', 'message' => 'Clé API Secrète invalide (commence par pk au lieu de sk)'];
        }

        \FedaPay\FedaPay::setApiKey($this->privateKey);

        $params = [
            'amount' => $amount,
            'currency' => ['iso' => $currency],
            'description' => $options['description'] ?? 'Paiement KODIPAY',
            'callback_url' => route('payments.callback', ['gateway_id' => $options['gateway_id'] ?? 'unknown']),
            'customer' => [
                'firstname' => $options['firstname'] ?? 'Client',
                'lastname' => $options['lastname'] ?? 'KODIPAY',
                'email' => $options['email'] ?? 'customer@example.com',
                'phone_number' => [
                    'number' => $options['phone'] ?? '64000001',
                    'country' => $options['country'] ?? 'bj'
                ]
            ],
            // 'merchant_reference' => 'KODI-' . uniqid(),
            // 'custom_metadata' => [
            //     'payout_destination' => $options['payout_destination'] ?? null,
            // ],
            // 'send_now' => true
        ];

        \Illuminate\Support\Facades\Log::info('Initiating FedaPay payment', ['params' => $params]);

        try {
            $fedapayTransaction = \FedaPay\Transaction::create($params);
            $token = $fedapayTransaction->generateToken();

            \Illuminate\Support\Facades\Log::info('FedaPay payment initiated successfully', [
                'id' => $fedapayTransaction->id,
                'token' => $token->token
            ]);

            if ($options['direct'] ?? false) {
                // Pour FedaPay, le paiement direct (sans redirection) correspond au USSD Push
                // On détecte l'opérateur (MTN/Moov) en fonction du préfixe du numéro
                $phoneNumber = $options['phone'] ?? $options['phone_number']['number'] ?? '64000001';
                $mode = $this->detectMode($phoneNumber);

                \Illuminate\Support\Facades\Log::info('Triggering FedaPay USSD Push (direct)', [
                    'mode' => $mode,
                    'phone' => $phoneNumber
                ]);

                $fedapayTransaction->sendNowWithToken($mode, $token->token, [
                    'number' => $phoneNumber,
                    'country' => $options['country'] ?? 'bj'
                ]);
            }

            return (object) [
                'status' => 'success',
                'external_id' => $fedapayTransaction->id,
                'url' => $token->url,
                'token' => $token->token
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FedaPay Payment Error', [
                'message' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    public function payout(int $amount, string $currency, string $destination): object
    {
        //  Configuration du SDK (Toujours nécessaire dans chaque méthode du Driver)
        \FedaPay\FedaPay::setApiKey($this->privateKey);
        \FedaPay\FedaPay::setEnvironment($this->is_live ? 'live' : 'sandbox');

        try {
            // Création de l'ordre de virement
            // On suppose que la destination est un numéro de téléphone béninois pour l'instant
            $payout = Payout::create([
                'amount' => $amount,
                'currency' => ['iso' => $currency],
                // 'mode' => $this->detectMode($destination),  // Utilisation d'une méthode de détection
                'customer' => [
                    'firstname' => 'Vendeur',
                    'lastname' => 'KODIPAY',
                    'email' => 'payout@kodipay.com',
                    'phone_number' => [
                        'number' => $destination,
                        'country' => 'bj'  // Code pays (ex: bj, ci, tg)
                    ]
                ]
            ]);

            // Envoi effectif des fonds
            $payout->sendNow();

            return (object) [
                'status' => 'success',
                'external_id' => $payout->id,
                'raw' => $payout
            ];
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors du virement FedaPay : ' . $e->getMessage());
        }
    }

    /**
     * Petite méthode utilitaire pour détecter si c'est MTN ou Moov
     */
    private function detectMode(string $number): string
    {
        // Nettoyage du numéro : on garde les chiffres
        $cleanNumber = ltrim($number, '+0');

        // Préfixes MTN (Bénin, Togo, CI) : 97-96-61-62-51-52-53-54-60-64-59-90-91-42-46-67-66-69
        $mtnPrefixes = ['97', '96', '61', '62', '51', '52', '53', '54', '60', '64', '59', '90', '91', '42', '46', '67', '66', '69'];
        $prefix = substr($cleanNumber, 0, 2);

        // FedaPay : 'mtn' au Bénin/CI, 'moov' au Bénin/Togo, etc.
        // Utilisation du code standard 'mtn' pour plus de compatibilité
        return in_array($prefix, $mtnPrefixes) ? 'mtn' : 'moov';
    }

    public function validateWebhook(\Illuminate\Http\Request $request): object
    {
        \FedaPay\FedaPay::setApiKey($this->privateKey);
        \FedaPay\FedaPay::setEnvironment($this->is_live ? 'live' : 'sandbox');

        try {
            // Récupération de la signature
            $signature = $request->header('x-fedapay-signature');

            if (!$signature) {
                throw new \Exception('Signature manquante.');
            }

            // Vérification cryptographique via le SDK
            // Il est impératif d'utiliser le JSON **brut** reçu pour que la signature concorde.
            $event = \FedaPay\Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $this->webhookSecret ?? $this->privateKey  // Use webhookSecret if available, otherwise fallback to privateKey
            );

            // On retourne un objet standardisé pour notre PaymentService
            return (object) [
                'status' => 'success',
                'event' => $event->name,  // ex: 'transaction.approved', 'transaction.canceled'
                'external_id' => $event->data['id'] ?? null,
                'raw_data' => $event->data
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FedaPay Webhook Validation Error', [
                'error' => $e->getMessage()
            ]);
            // Si la signature est fausse, on bloque tout
            throw new \Exception('Validation Webhook échouée : ' . $e->getMessage());
        }
    }

    public function retrieveTransaction(string $externalId): object
    {
        \FedaPay\FedaPay::setApiKey($this->privateKey);
        \FedaPay\FedaPay::setEnvironment($this->is_live ? 'live' : 'sandbox');

        try {
            $fedapayTransaction = \FedaPay\Transaction::retrieve($externalId);

            \Illuminate\Support\Facades\Log::info('FedaPay transaction retrieved for reconciliation', [
                'id' => $externalId,
                'status' => $fedapayTransaction->status,
                'raw_status' => $fedapayTransaction->status ?? 'unknown'
            ]);

            // Mapper le statut FedaPay vers le statut KODIPAY si nécessaire
            // FedaPay status: approved, pending, canceled, declined, transferred, refunded
            $status = 'pending';
            if (in_array($fedapayTransaction->status, ['approved', 'transferred'])) {
                $status = 'success';
            } elseif (in_array($fedapayTransaction->status, ['canceled', 'declined', 'refunded'])) {
                $status = 'failed';
            }

            return (object) [
                'status' => $status,
                'external_id' => $fedapayTransaction->id,
                'event' => 'transaction.' . $fedapayTransaction->status,
                'raw_data' => $fedapayTransaction
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FedaPay Transaction Retrieval Error', [
                'id' => $externalId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
