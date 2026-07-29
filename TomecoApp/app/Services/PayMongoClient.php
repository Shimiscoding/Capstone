<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoClient
{
    public function __construct(private readonly Settings $settings) {}

    public function createCheckoutSession(Payment $payment): array
    {
        $payment->loadMissing(['violation', 'user']);

        $response = $this->request()->post('/v1/checkout_sessions', [
            'data' => ['attributes' => [
                'billing' => array_filter([
                    'name' => $payment->user?->fullName ?? $payment->violation->driver_name,
                    'email' => $payment->user?->email,
                    'phone' => $payment->user?->phoneNumber,
                ]),
                'line_items' => [[
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'description' => 'Plate '.$payment->violation->plate_number,
                    'name' => $payment->violation->violation_type,
                    'quantity' => 1,
                ]],
                'payment_method_types' => $this->settings->get('payments.methods', config('services.paymongo.payment_methods')),
                'reference_number' => $payment->reference,
                'description' => 'TOMECO violation fine payment',
                'send_email_receipt' => true,
                'show_description' => true,
                'show_line_items' => true,
                'success_url' => route('payments.success', $payment),
                'cancel_url' => route('payments.cancel', $payment),
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                    'violation_id' => (string) $payment->violation_id,
                ],
            ]],
        ]);

        if ($response->failed()) {
            report(new RuntimeException('PayMongo checkout failed: '.$response->body()));
            throw new RuntimeException($response->json('errors.0.detail', 'PayMongo could not create the checkout.'));
        }

        return $response->json('data');
    }

    private function request(): PendingRequest
    {
        $secret = (string) config('services.paymongo.secret_key');
        if ($secret === '') {
            throw new RuntimeException('PayMongo is not configured. Add PAYMONGO_SECRET_KEY to your .env file.');
        }

        return Http::baseUrl(config('services.paymongo.base_url'))
            ->withBasicAuth($secret, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry(2, 250);
    }
}
