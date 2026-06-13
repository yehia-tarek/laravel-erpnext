<?php

namespace YehiaTarek\ERPNext\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use YehiaTarek\ERPNext\Contracts\AuthDriver;
use YehiaTarek\ERPNext\Exceptions\AuthenticationException;

/**
 * Password-based (session cookie) authentication.
 *
 * Logs in via /api/method/login and persists the session cookie
 * for all subsequent requests.
 */
class PasswordAuth implements AuthDriver
{
    private CookieJar $jar;
    private bool $authenticated = false;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly array  $httpOptions = [],
    ) {
        if (empty($username) || empty($password)) {
            throw new AuthenticationException(
                'Both ERPNEXT_USERNAME and ERPNEXT_PASSWORD must be set for password authentication.'
            );
        }

        $this->jar = new CookieJar();
    }

    public function getHeaders(): array
    {
        // No extra headers needed; the cookie jar is injected at the client level.
        return [];
    }

    /**
     * Perform the login request and populate the cookie jar.
     * Called lazily by ERPNextClient before the first request.
     */
    public function authenticate(Client $httpClient): void
    {
        if ($this->authenticated) {
            return;
        }

        $response = $httpClient->post($this->baseUrl . '/api/method/login', [
            'json'       => ['usr' => $this->username, 'pwd' => $this->password],
            'cookies'    => $this->jar,
            'headers'    => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);

        if (! isset($body['message']) || $body['message'] !== 'Logged In') {
            throw new AuthenticationException('ERPNext password authentication failed.');
        }

        $this->authenticated = true;
    }

    public function getCookieJar(): CookieJar
    {
        return $this->jar;
    }
}
