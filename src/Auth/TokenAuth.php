<?php

namespace YehiaTarek\ERPNext\Auth;

use YehiaTarek\ERPNext\Contracts\AuthDriver;
use YehiaTarek\ERPNext\Exceptions\AuthenticationException;

/**
 * Token-based authentication using an API Key and API Secret.
 *
 * The Authorization header format is:  token api_key:api_secret
 */
class TokenAuth implements AuthDriver
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {
        if (empty($apiKey) || empty($apiSecret)) {
            throw new AuthenticationException(
                'Both ERPNEXT_API_KEY and ERPNEXT_API_SECRET must be set for token authentication.'
            );
        }
    }

    public function getHeaders(): array
    {
        return [
            'Authorization' => 'token ' . $this->apiKey . ':' . $this->apiSecret,
        ];
    }
}
