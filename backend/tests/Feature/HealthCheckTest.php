<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_reports_database_connection(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00', 'UTC'));

        $response = $this->getJson('/api/health');

        $response->assertOk()->assertExactJson([
            'status' => 'ok',
            'database' => 'ok',
            'timestamp' => '2026-01-01T12:00:00+00:00',
        ]);
    }

    public function test_tests_run_against_separate_database(): void
    {
        $this->assertSame('merchantops_test', DB::connection()->getDatabaseName());
    }
}
