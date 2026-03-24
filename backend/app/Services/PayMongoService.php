<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    private $secretKey;
    private $publicKey;
    private $baseUrl = 'https://api.paymongo.com/v1';

    public function __construct()
    {
        $this->secretKey = config('services.paymongo.secret_key');
        $this->publicKey = config('services.paymongo.public_key');
    }

    /**
     * Create a payment from a chargeable source (for GCash/Maya)
     */
    public function createPayment(array $data)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/payments", [
                    'data' => [
                        'attributes' => [
                            'amount' => $data['amount'], // Amount in centavos
                            'currency' => $data['currency'] ?? 'PHP',
                            'source' => [
                                'id' => $data['source_id'],
                                'type' => 'source'
                            ],
                            'description' => $data['description'] ?? 'Payment',
                            'statement_descriptor' => $data['statement_descriptor'] ?? 'FlowerShop',
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            Log::error('PayMongo Payment Creation Error:', $response->json());
            return [
                'success' => false,
                'error' => $response->json()['errors'][0]['detail'] ?? 'Payment creation failed'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Exception:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a payment intent for card payments
     */
    public function createPaymentIntent(array $data)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/payment_intents", [
                    'data' => [
                        'attributes' => [
                            'amount' => $data['amount'], // Amount in centavos
                            'currency' => 'PHP',
                            'payment_method_allowed' => ['card'],
                            'description' => $data['description'] ?? 'Order Payment',
                            'statement_descriptor' => $data['statement_descriptor'] ?? 'FlowerShop',
                            'metadata' => $data['metadata'] ?? [],
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            Log::error('PayMongo Payment Intent Error:', $response->json());
            return [
                'success' => false,
                'error' => $response->json()['errors'][0]['detail'] ?? 'Payment intent creation failed'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Exception:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a source for GCash or Maya payments
     */
    public function createSource(array $data)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/sources", [
                    'data' => [
                        'attributes' => [
                            'amount' => $data['amount'], // Amount in centavos
                            'currency' => 'PHP',
                            'type' => $data['type'], // gcash or grab_pay (Maya)
                            'redirect' => [
                                'success' => $data['success_url'],
                                'failed' => $data['failed_url'],
                            ],
                            'billing' => $data['billing'] ?? null,
                            'metadata' => $data['metadata'] ?? [],
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            Log::error('PayMongo Source Error:', $response->json());
            return [
                'success' => false,
                'error' => $response->json()['errors'][0]['detail'] ?? 'Source creation failed'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Exception:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a payment method (for card payments)
     */
    public function createPaymentMethod(array $cardDetails)
    {
        try {
            $response = Http::withBasicAuth($this->publicKey, '')
                ->post("{$this->baseUrl}/payment_methods", [
                    'data' => [
                        'attributes' => [
                            'type' => 'card',
                            'details' => [
                                'card_number' => $cardDetails['card_number'],
                                'exp_month' => $cardDetails['exp_month'],
                                'exp_year' => $cardDetails['exp_year'],
                                'cvc' => $cardDetails['cvc'],
                            ],
                            'billing' => $cardDetails['billing'] ?? null,
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            Log::error('PayMongo Payment Method Error:', $response->json());
            return [
                'success' => false,
                'error' => $response->json()['errors'][0]['detail'] ?? 'Payment method creation failed'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Exception:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Attach payment method to payment intent
     */
    public function attachPaymentMethod($paymentIntentId, $paymentMethodId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/payment_intents/{$paymentIntentId}/attach", [
                    'data' => [
                        'attributes' => [
                            'payment_method' => $paymentMethodId,
                            'return_url' => config('app.url') . '/payment/callback'
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            Log::error('PayMongo Attach Error:', $response->json());
            return [
                'success' => false,
                'error' => $response->json()['errors'][0]['detail'] ?? 'Payment attachment failed'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Exception:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Retrieve payment intent status
     */
    public function retrievePaymentIntent($paymentIntentId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->get("{$this->baseUrl}/payment_intents/{$paymentIntentId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to retrieve payment intent'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Exception:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Retrieve source status
     */
    public function retrieveSource($sourceId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->get("{$this->baseUrl}/sources/{$sourceId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to retrieve source'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Exception:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Convert amount to centavos (PayMongo uses centavos)
     */
    public function toCentavos($amount): int
    {
        return (int) ($amount * 100);
    }

    /**
     * Convert centavos to pesos
     */
    public function toPesos($centavos): float
    {
        return $centavos / 100;
    }
}