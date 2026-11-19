<?php

namespace App\Domain\Catalogue\Images;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Server-side Google Drive access with a service account (read-only scope).
 * Share the Drive folder with the service account's email. Credentials never leave the server.
 */
class GoogleDriveClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    public function isConfigured(): bool
    {
        $path = config('sudar.google_drive.service_account_json');

        return config('sudar.google_drive.enabled') && $path && is_readable($path);
    }

    /** @return array{id:string, name:string, mimeType:string, size:int} */
    public function metadata(string $fileId): array
    {
        $response = Http::timeout(15)->withToken($this->token())
            ->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId), [
                'fields' => 'id,name,mimeType,size,trashed',
                'supportsAllDrives' => 'true',
            ]);
        if ($response->status() === 404 || $response->status() === 403) {
            throw new ImageImportException('Drive file not found or not shared with the service account.');
        }
        $response->throw();
        $data = $response->json();
        if (! empty($data['trashed'])) {
            throw new ImageImportException('The Drive file is in the trash.');
        }

        return ['id' => $data['id'], 'name' => (string) ($data['name'] ?? ''), 'mimeType' => (string) ($data['mimeType'] ?? ''), 'size' => (int) ($data['size'] ?? 0)];
    }

    public function download(string $fileId, int $maxBytes): string
    {
        $response = Http::timeout(60)->withToken($this->token())
            ->withOptions(['stream' => false])
            ->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId), ['alt' => 'media', 'supportsAllDrives' => 'true']);
        $response->throw();
        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            throw new ImageImportException('The Drive file is larger than the allowed size.');
        }

        return $body;
    }

    private function token(): string
    {
        if (! $this->isConfigured()) {
            throw new ImageImportException('Google Drive import is not configured on the server.');
        }

        return Cache::remember('google-drive-token', now()->addMinutes(50), function () {
            $key = json_decode((string) file_get_contents(config('sudar.google_drive.service_account_json')), true);
            if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
                throw new ImageImportException('The Google service-account key file is invalid.');
            }
            $tokenUri = $key['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();
            $segments = [
                self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                self::b64(json_encode(['iss' => $key['client_email'], 'scope' => self::SCOPE, 'aud' => $tokenUri, 'iat' => $now, 'exp' => $now + 3600])),
            ];
            $signature = '';
            if (! openssl_sign(implode('.', $segments), $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new ImageImportException('Could not sign the Google service-account token.');
            }
            $segments[] = self::b64($signature);
            $response = Http::asForm()->timeout(15)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);
            $response->throw();

            return (string) $response->json('access_token');
        });
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
