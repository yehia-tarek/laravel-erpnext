<?php

namespace YehiaTarek\ERPNext\Contracts;

abstract class AuthDriver
{
    abstract public function getHeaders(): array;

    // Default: no-op. Override in PasswordAuth.
    public function authenticate(\GuzzleHttp\Client $http): void {}

    // Default: no cookie jar. Override in PasswordAuth.
    public function getCookieJar(): ?\GuzzleHttp\Cookie\CookieJar { return null; }
}
