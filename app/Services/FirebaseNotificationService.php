<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected ?string $projectId = null;
    protected ?string $credentialsPath = null;
    protected ?array $credentials = null;

    public function __construct()
    {

        
        $configuredPath = config('services.firebase.credentials')
            ?: env('FIREBASE_CREDENTIALS')
            ?: storage_path('app/firebase/firebase-adminsdk.json');

        $this->credentialsPath = $this->resolveCredentialsPath($configuredPath);

        if (!file_exists($this->credentialsPath)) {
            Log::error('Firebase credentials file not found', [
                'path' => $this->credentialsPath,
            ]);

            return;
        }

        $this->credentials = json_decode(file_get_contents($this->credentialsPath), true);

        if (!$this->credentials || empty($this->credentials['project_id'])) {
            Log::error('Invalid Firebase credentials JSON', [
                'path' => $this->credentialsPath,
            ]);

            return;
        }

        $this->projectId = $this->credentials['project_id'];
    }

    public function sendToToken(?string $deviceToken, string $title, string $body, array $data = []): bool
    {
        if (empty($deviceToken)) {
            return false;
        }

        if (empty($this->credentials) || empty($this->projectId)) {
            Log::warning('Firebase notification skipped because credentials are missing.');
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();

            $payload = [
                'message' => [
                    'token' => trim($deviceToken),
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', array_filter($data, static function ($value) {
                        return $value !== null;
                    })),
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'sound' => 'default',
                        ],
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $payload);

            if (!$response->successful()) {
                Log::error('Firebase notification failed', [
                    'device_token' => $deviceToken,
                    'response' => $response->body(),
                ]);

                return false;
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('Firebase notification exception', [
                'device_token' => $deviceToken,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendToTokens(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        $success = 0;
        $failed = 0;

        $deviceTokens = array_values(array_unique(array_filter(array_map(
            static function ($token) {
                return is_string($token) ? trim($token) : '';
            },
            $deviceTokens
        ))));

        foreach ($deviceTokens as $token) {
            $sent = $this->sendToToken($token, $title, $body, $data);

            if ($sent) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'total' => count($deviceTokens),
        ];
    }
private function resolveCredentialsPath(string $path): string
    {
        if ($path === '') {
            return storage_path('app/firebase/firebase-adminsdk.json');
        }

        return strpos($path, DIRECTORY_SEPARATOR) === 0
            ? $path
            : base_path($path);
    }

    private function getAccessToken(): string
    {
        $now = time();

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $claim = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $jwtHeader = $this->base64UrlEncode(json_encode($header));
        $jwtClaim = $this->base64UrlEncode(json_encode($claim));

        $signatureInput = $jwtHeader . '.' . $jwtClaim;

        openssl_sign(
            $signatureInput,
            $signature,
            $this->credentials['private_key'],
            'sha256WithRSAEncryption'
        );

        $jwt = $signatureInput . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Firebase access token error: ' . $response->body());
        }

        return $response->json('access_token');
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}