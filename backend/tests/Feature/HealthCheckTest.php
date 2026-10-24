<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_reports_database_connection(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()->assertExactJson(['status' => 'ok', 'database' => 'ok']);
    }

    public function test_tests_run_against_separate_database(): void
    {
        $this->assertSame('merchantops_test', DB::connection()->getDatabaseName());
    }
}
