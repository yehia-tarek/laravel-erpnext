<?php

namespace YehiaTarek\ERPNext\Tests;

use YehiaTarek\ERPNext\ERPNextServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ERPNextServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ERPNext' => \YehiaTarek\ERPNext\Facades\ERPNext::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('erpnext.base_url', 'https://demo.erpnext.com');
        $app['config']->set('erpnext.auth.method', 'token');
        $app['config']->set('erpnext.auth.api_key', 'test_key');
        $app['config']->set('erpnext.auth.api_secret', 'test_secret');
    }
}
