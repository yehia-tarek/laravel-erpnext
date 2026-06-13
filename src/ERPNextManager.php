<?php

namespace YehiaTarek\ERPNext;

use InvalidArgumentException;
use YehiaTarek\ERPNext\Auth\OAuthAuth;
use YehiaTarek\ERPNext\Auth\PasswordAuth;
use YehiaTarek\ERPNext\Auth\TokenAuth;
use YehiaTarek\ERPNext\Contracts\AuthDriver;

/**
 * Manages one or more ERPNextClient instances, keyed by connection name.
 * Proxies all ERPNextClient methods to the default connection.
 *
 * @mixin ERPNextClient
 */
class ERPNextManager
{
    /** @var array<string, ERPNextClient> */
    private array $connections = [];

    public function __construct(private readonly array $config) {}

    // =========================================================================
    // Connection management
    // =========================================================================

    /**
     * Get a named connection (lazy-created).
     */
    public function connection(string $name = 'default'): ERPNextClient
    {
        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Forget a cached connection (forces re-auth on next use).
     */
    public function purge(string $name = 'default'): void
    {
        unset($this->connections[$name]);
    }

    // =========================================================================
    // Dynamic proxying to default connection
    // =========================================================================

    public function __call(string $method, array $args): mixed
    {
        return $this->connection()->{$method}(...$args);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    private function makeConnection(string $name): ERPNextClient
    {
        // Allow array-keyed connections or fall back to top-level config
        $cfg = ($name === 'default')
            ? $this->config
            : ($this->config['connections'][$name] ?? throw new InvalidArgumentException(
                "ERPNext connection '{$name}' is not configured."
            ));

        $baseUrl = rtrim($cfg['base_url'] ?? '', '/');
        $auth    = $this->resolveAuth($cfg['auth'] ?? []);

        return new ERPNextClient($baseUrl, $auth, $cfg);
    }

    private function resolveAuth(array $auth): AuthDriver
    {
        return match ($auth['method'] ?? 'token') {
            'token'    => new TokenAuth($auth['api_key'] ?? '', $auth['api_secret'] ?? ''),
            'password' => new PasswordAuth('', $auth['username'] ?? '', $auth['password'] ?? []),
            'oauth'    => new OAuthAuth($auth['access_token'] ?? ''),
            default    => throw new InvalidArgumentException(
                "Unknown ERPNext auth method: {$auth['method']}. Supported: token, password, oauth"
            ),
        };
    }
}
