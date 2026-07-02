<?php
declare(strict_types=1);

namespace App\Services;

final class GoogleAuthService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function authUrl(): string
    {
        $state = bin2hex(random_bytes(24));
        $_SESSION['google_oauth_state'] = $state;

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function userFromCallback(array $query): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google prijava nije konfigurirana.');
        }

        $state = (string) ($query['state'] ?? '');
        $expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
        unset($_SESSION['google_oauth_state']);

        if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('Google prijava nije prošla sigurnosnu provjeru.');
        }

        $code = (string) ($query['code'] ?? '');
        if ($code === '') {
            throw new \RuntimeException('Google nije vratio autorizacijski kod.');
        }

        $tokenResponse = $this->request(
            'https://oauth2.googleapis.com/token',
            [
                'code' => $code,
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]
        );

        $accessToken = (string) ($tokenResponse['access_token'] ?? '');
        if ($accessToken === '') {
            throw new \RuntimeException('Google nije vratio access token.');
        }

        return $this->request(
            'https://openidconnect.googleapis.com/v1/userinfo',
            [],
            ['Authorization: Bearer ' . $accessToken]
        );
    }

    /**
     * @return array<int, string>
     */
    public function allowedEmails(): array
    {
        $emails = array_filter(array_map(
            static fn(string $email): string => strtolower(trim($email)),
            explode(',', (string) getenv('GOOGLE_ALLOWED_EMAILS'))
        ));

        $adminEmail = strtolower(trim((string) (getenv('ADMIN_EMAIL') ?: 'admin@pontadesk.local')));
        if ($adminEmail !== '') {
            $emails[] = $adminEmail;
        }

        return array_values(array_unique($emails));
    }

    public function allowedDomain(): string
    {
        return strtolower(trim((string) getenv('GOOGLE_ALLOWED_DOMAIN')));
    }

    public function redirectUri(): string
    {
        $configured = trim((string) getenv('GOOGLE_REDIRECT_URI'));
        if ($configured !== '') {
            return $configured;
        }

        $scheme = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        if ($scheme === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . '/login/google/callback';
    }

    private function clientId(): string
    {
        return trim((string) getenv('GOOGLE_CLIENT_ID'));
    }

    private function clientSecret(): string
    {
        return trim((string) getenv('GOOGLE_CLIENT_SECRET'));
    }

    /**
     * @param array<string, string> $post
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function request(string $url, array $post = [], array $headers = []): array
    {
        $response = function_exists('curl_init')
            ? $this->curlRequest($url, $post, $headers)
            : $this->streamRequest($url, $post, $headers);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Google je vratio neispravan odgovor.');
        }

        if (isset($decoded['error'])) {
            throw new \RuntimeException('Google prijava nije uspjela.');
        }

        return $decoded;
    }

    /**
     * @param array<string, string> $post
     * @param array<int, string> $headers
     */
    private function curlRequest(string $url, array $post, array $headers): string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Nije moguće pokrenuti Google HTTP zahtjev.');
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 12);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if ($post !== []) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($response) || $httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException('Google HTTP zahtjev nije uspio.');
        }

        return $response;
    }

    /**
     * @param array<string, string> $post
     * @param array<int, string> $headers
     */
    private function streamRequest(string $url, array $post, array $headers): string
    {
        $method = $post === [] ? 'GET' : 'POST';
        $content = $post === [] ? '' : http_build_query($post);
        $requestHeaders = $headers;
        if ($post !== []) {
            $requestHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $requestHeaders),
                'content' => $content,
                'timeout' => 12,
                'ignore_errors' => true,
            ],
        ]));

        if (!is_string($response)) {
            throw new \RuntimeException('Google HTTP zahtjev nije uspio.');
        }

        return $response;
    }
}
