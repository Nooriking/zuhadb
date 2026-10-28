<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        putenv('DB_DATABASE=zuha_testing');
        $_ENV['DB_DATABASE'] = 'zuha_testing';
        $_SERVER['DB_DATABASE'] = 'zuha_testing';

        $app = parent::createApplication();
        $app['config']->set('database.connections.mysql.database', 'zuha_testing');
        $app['db']->purge();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');
        if ($database !== 'zuha_testing') {
            $this->fail("Refusing to run tests against database [{$database}]. Expected zuha_testing.");
        }
    }
}
