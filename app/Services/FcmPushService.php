<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FcmPushService
{
    public function sendToUser(Notification $notification): void
    {
        if (! $this->enabled()) {
            return;
        }

        $tokens = MobileDeviceToken::where('user_id', $notification->user_id)->pluck('token');

        foreach ($tokens as $token) {
            $this->sendToToken($token, $notification);
        }
    }

    private function sendToToken(string $token, Notification $notification): void
    {
        try {
            $accessToken = $this->accessToken();
            if (! $accessToken) {
                return;
            }

            $response = Http::withToken($accessToken)
                ->timeout(8)
                ->post($this->sendUrl(), [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $notification->title,
                            'body' => Str::limit($notification->message, 160),
                        ],
                        'data' => $this->dataPayload($notification),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'icon' => 'ic_notification',
                                'color' => '#123F6E',
                                'click_action' => 'OPEN_NOTIFICATION',
                            ],
                        ],
                    ],
                ]);

            if ($this->invalidTokenResponse($response->status(), $response->json())) {
                MobileDeviceToken::where('token_hash', hash('sha256', $token))->delete();
                return;
            }

            if (! $response->successful()) {
                Log::warning('No fue posible enviar push FCM', [
                    'notification_id' => $notification->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('No fue posible enviar push FCM', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function accessToken(): ?string
    {
        return Cache::remember('firebase_fcm_access_token', 3300, function () {
            $credentials = $this->credentials();
            if (! $credentials) {
                return null;
            }

            $jwt = $this->jwt($credentials);
            if (! $jwt) {
                return null;
            }

            $response = Http::asForm()
                ->timeout(8)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (! $response->successful()) {
                Log::warning('No fue posible obtener access token de Firebase', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    private function jwt(array $credentials): ?string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsigned = $this->base64Url(json_encode($header)).'.'.$this->base64Url(json_encode($payload));
        $privateKey = str_replace('\n', "\n", $credentials['private_key']);

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::warning('No fue posible firmar JWT para Firebase');
            return null;
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function credentials(): ?array
    {
        $path = config('services.firebase.credentials_path');
        if ($path && is_readable($path)) {
            return $this->validCredentials(json_decode(file_get_contents($path), true));
        }

        $json = config('services.firebase.credentials_json');
        if (! $json) {
            return null;
        }

        $content = trim($json);
        if (! str_starts_with($content, '{')) {
            $decoded = base64_decode($content, true);
            $content = $decoded ?: $content;
        }

        return $this->validCredentials(json_decode($content, true));
    }

    private function validCredentials(?array $credentials): ?array
    {
        if (! $credentials || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            return null;
        }

        return $credentials;
    }

    private function dataPayload(Notification $notification): array
    {
        $data = [
            'notification_id' => (string) $notification->id,
            'type' => (string) $notification->type,
            'url' => (string) ($notification->url() ?? ''),
        ];

        foreach (($notification->data_json ?? []) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $data[$key] = (string) $value;
            }
        }

        return $data;
    }

    private function invalidTokenResponse(int $status, ?array $body): bool
    {
        $errorStatus = data_get($body, 'error.status');

        return $status === 404 || in_array($errorStatus, ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'], true);
    }

    private function sendUrl(): string
    {
        return 'https://fcm.googleapis.com/v1/projects/'.config('services.firebase.project_id').'/messages:send';
    }

    private function enabled(): bool
    {
        return (bool) config('services.firebase.project_id')
            && (bool) (config('services.firebase.credentials_path') || config('services.firebase.credentials_json'));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
