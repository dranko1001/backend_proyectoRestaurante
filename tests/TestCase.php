<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['master'];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.mode', 'multi');
        Config::set('tenancy.master_subdomain', 'master');
        Config::set('tenancy.reserved_subdomains', ['www', 'api']);
    }
}
