<?php

namespace YehiaTarek\ERPNext\Contracts;

interface AuthDriver
{
    /**
     * Return the headers needed to authenticate every HTTP request.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;
}
