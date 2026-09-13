<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_database_connection_uses_testing_database(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $connectionName = config('database.default');

        $this->assertSame(
            'mysql',
            config("database.connections.{$connectionName}.driver")
        );

        $this->assertSame(
            'testing',
            config("database.connections.{$connectionName}.database")
        );

        $result = DB::selectOne('SELECT DATABASE() AS database_name');

        $this->assertSame('testing', $result->database_name);
    }
}
