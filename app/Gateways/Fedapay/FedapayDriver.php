<?php
namespace App\Gateways\Fedapay;

use App\Core\Payments\Contract\PaymentsGatewayInterface;
use FedaPay\Payout;
use FedaPay\Transaction;
use FedaPay\Webhook;

class FedapayDriver implements PaymentsGatewayInterface
{
    public function __construct(
        private string $apiKey,
        private bool $is_live
    ) {}

    public function makePayment(int $amount, string $currency, array $options): object
    {
        \FedaPay\FedaPay::setEnvironment($this->is_live ? 'live' : 'sandbox');
        \FedaPay\FedaPay::setApiKey($this->apiKey);

        // Création de la transaction chez FedaPay
        $fedapayTransaction = Transaction::create([
            'amount' => $amount,
            'currency' => ['iso' => $currency],
            'description' => $options['description'] ?? 'Paiement KODIPAY',
            'callback_url' => $options['callback_url'] ?? null,
            'customer' => [
                'firstname' => $options['firstname'] ?? 'Client',
                'lastname' => $options['lastname'] ?? 'KODIPAY',
                'email' => $options['email'],
                'phone_number' => [
                    'number' => $options['phone'] ?? '6600111000',
                    'country' => $options['country'] ?? 'bj'
                ]
            ]
        ]);

        $token = $fedapayTransaction->generateToken();

        return (object) [
            'external_id' => $fedapayTransaction->id,
            'url' => $token->url,
            'token' => $token->token
        ];
    }

    public function payout(int $amount, string $currency, string $destination): object
    {
        //  Configuration du SDK (Toujours nécessaire dans chaque méthode du Driver)
        \FedaPay\FedaPay::setApiKey($this->apiKey);
        \FedaPay\FedaPay::setEnvironment($this->is_live ? 'live' : 'sandbox');

        try {
            // Création de l'ordre de virement
            // On suppose que la destination est un numéro de téléphone béninois pour l'instant
            $payout = Payout::create([
                'amount' => $amount,
                'currency' => ['iso' => $currency],
                'mode' => $this->detectMode($destination),  // Utilisation d'une méthode de détection
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
            $payout->send();

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
        // Logique simplifiée : si le numéro commence par 97/61/etc c'est MTN, sinon Moov
        // À perfectionner selon les préfixes réels du pays
        $mtnPrefixes = ['97', '96', '61', '62', '51', '52', '53', '54'];
        $prefix = substr($number, 0, 2);

        return in_array($prefix, $mtnPrefixes) ? 'mtn' : 'moov';
    }

    public function validateWebhook(array $payload, array $headers): object
    {
        try {
            // Récupération de la signature (insensible à la casse des headers)
            $signature = $headers['x-fedapay-signature'][0] ?? null;

            if (!$signature) {
                throw new \Exception('Signature manquante.');
            }

            // Vérification cryptographique via le SDK
            // On repasse le payload en JSON et on compare avec la signature
            $event = Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $this->apiKey
            );

            // On retourne un objet standardisé pour notre PaymentService
            return (object) [
                'status' => 'success',
                'event' => $event->name,  // ex: 'transaction.approved', 'transaction.canceled'
                'external_id' => $event->data['id'] ?? null,
                'raw_data' => $event->data
            ];
        } catch (\Exception $e) {
            // Si la signature est fausse, on bloque tout
            throw new \Exception('Validation Webhook échouée : ' . $e->getMessage());
        }
    }
}
