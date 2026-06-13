<?php

namespace YehiaTarek\ERPNext\Auth;

use YehiaTarek\ERPNext\Contracts\AuthDriver;
use YehiaTarek\ERPNext\Exceptions\AuthenticationException;

/**
 * OAuth 2.0 Bearer token authentication.
 *
 * Pass the access_token obtained via ERPNext's OAuth flow.
 */
class OAuthAuth implements AuthDriver
{
    public function __construct(
        private readonly string $accessToken,
    ) {
        if (empty($accessToken)) {
            throw new AuthenticationException(
                'ERPNEXT_ACCESS_TOKEN must be set for OAuth authentication.'
            );
        }
    }

    public function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
        ];
    }
}
