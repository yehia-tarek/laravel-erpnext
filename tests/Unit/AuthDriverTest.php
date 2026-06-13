<?php

namespace YehiaTarek\ERPNext\Tests\Unit;

use YehiaTarek\ERPNext\Auth\OAuthAuth;
use YehiaTarek\ERPNext\Auth\TokenAuth;
use YehiaTarek\ERPNext\Exceptions\AuthenticationException;
use YehiaTarek\ERPNext\Tests\TestCase;

class AuthDriverTest extends TestCase
{
    public function test_token_auth_produces_correct_header(): void
    {
        $auth = new TokenAuth('my_key', 'my_secret');

        $this->assertSame(
            ['Authorization' => 'token my_key:my_secret'],
            $auth->getHeaders()
        );
    }

    public function test_token_auth_throws_when_credentials_missing(): void
    {
        $this->expectException(AuthenticationException::class);
        new TokenAuth('', '');
    }

    public function test_oauth_auth_produces_bearer_header(): void
    {
        $auth = new OAuthAuth('my_access_token');

        $this->assertSame(
            ['Authorization' => 'Bearer my_access_token'],
            $auth->getHeaders()
        );
    }

    public function test_oauth_auth_throws_when_token_missing(): void
    {
        $this->expectException(AuthenticationException::class);
        new OAuthAuth('');
    }
}
