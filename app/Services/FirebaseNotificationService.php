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
            ?: 'storage/app/firebase/firebase-adminsdk.json';

        $this->credentialsPath = $this->resolveCredentialsPath($configuredPath);

        Log::info('Firebase credentials path check', [
            'configured_path' => $configuredPath,
            'resolved_path' => $this->credentialsPath,
            'exists' => file_exists($this->credentialsPath),
            'is_readable' => is_readable($this->credentialsPath),
        ]);

        if (!file_exists($this->credentialsPath)) {
            Log::error('Firebase credentials file not found', [
                'path' => $this->credentialsPath,
            ]);
            return;
        }

        if (!is_readable($this->credentialsPath)) {
            Log::error('Firebase credentials file not readable', [
                'path' => $this->credentialsPath,
            ]);
            return;
        }

        $fileContents = file_get_contents($this->credentialsPath);

        if ($fileContents === false || trim($fileContents) === '') {
            Log::error('Firebase credentials file empty or unreadable', [
                'path' => $this->credentialsPath,
            ]);
            return;
        }

        $credentials = json_decode($fileContents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Firebase credentials JSON decode failed', [
                'path' => $this->credentialsPath,
                'error' => json_last_error_msg(),
                'first_100_chars' => substr($fileContents, 0, 100),
            ]);
            return;
        }

        if (
            empty($credentials['project_id']) ||
            empty($credentials['client_email']) ||
            empty($credentials['private_key'])
        ) {
            Log::error('Firebase credentials missing required keys', [
                'path' => $this->credentialsPath,
                'has_project_id' => !empty($credentials['project_id']),
                'has_client_email' => !empty($credentials['client_email']),
                'has_private_key' => !empty($credentials['private_key']),
                'keys' => array_keys($credentials),
            ]);
            return;
        }

        $this->credentials = $credentials;
        $this->projectId = $credentials['project_id'];

        Log::info('Firebase credentials loaded successfully', [
            'project_id' => $this->projectId,
            'client_email' => $credentials['client_email'],
        ]);
    }

    public function sendToToken(?string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $deviceToken = is_string($deviceToken) ? trim($deviceToken) : '';

        if ($deviceToken === '') {
            Log::warning('Firebase notification skipped: device token empty');
            return false;
        }

        if (empty($this->credentials) || empty($this->projectId)) {
            Log::warning('Firebase notification skipped: credentials missing', [
                'credentials_path' => $this->credentialsPath,
                'credentials_loaded' => !empty($this->credentials),
                'project_id' => $this->projectId,
            ]);
            return false;
        }

        // try {
            $accessToken = $this->getAccessToken();

            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $this->stringifyData($data),
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $payload);

            if (!$response->successful()) {
                Log::error('Firebase notification failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'device_token' => $this->maskToken($deviceToken),
                ]);
                return false;
            }

            Log::info('Firebase notification sent successfully', [
                'response' => $response->json(),
                'device_token' => $this->maskToken($deviceToken),
            ]);

            return true;

        // } catch (\Throwable $e) {
        //     Log::error('Firebase notification exception', [
        //         'error' => $e->getMessage(),
        //         'device_token' => $this->maskToken($deviceToken),
        //     ]);
        //     return false;
        // }
    }

    public function sendToTokens(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        $deviceTokens = array_values(array_unique(array_filter(array_map(function ($token) {
            return is_string($token) ? trim($token) : '';
        }, $deviceTokens))));

        $success = 0;
        $failed = 0;

        foreach ($deviceTokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
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
        $path = trim($path, " \t\n\r\0\x0B\"'");

        if ($path === '') {
            return storage_path('app/firebase/firebase-adminsdk.json');
        }

        // Windows absolute path: C:\...
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
            return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }

        // Linux absolute path
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }

        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // storage/app/firebase/firebase-adminsdk.json
        if (str_starts_with($normalizedPath, 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR)) {
            return base_path($normalizedPath);
        }

        // only filename
        if (basename($normalizedPath) === $normalizedPath) {
            return storage_path('app/firebase/' . $normalizedPath);
        }

        return base_path($normalizedPath);
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

        $privateKey = str_replace('\\n', "\n", $this->credentials['private_key']);

        $signed = openssl_sign(
            $signatureInput,
            $signature,
            $privateKey,
            'sha256WithRSAEncryption'
        );

        if (!$signed) {
            throw new \Exception('Firebase access token error: unable to sign JWT.');
        }

        $jwt = $signatureInput . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Firebase access token error: ' . $response->body());
        }

        $accessToken = $response->json('access_token');

        if (empty($accessToken)) {
            throw new \Exception('Firebase access token missing in Google response.');
        }

        return $accessToken;
    }

    private function stringifyData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $result[(string) $key] = is_array($value) || is_object($value)
                ? json_encode($value)
                : (string) $value;
        }

        return $result;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 16) {
            return '***';
        }

        return substr($token, 0, 8) . '...' . substr($token, -8);
    }
}