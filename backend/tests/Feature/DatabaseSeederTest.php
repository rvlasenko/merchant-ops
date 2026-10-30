<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Models\Order;
use App\Models\SimulatedFailure;
use App\Models\SyncAttempt;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_fixed_data_set(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(20, Order::count());
        $this->assertSame(5, SimulatedFailure::count());
        $this->assertSame(0, SyncAttempt::count());
        $this->assertSame(20, Order::where('sync_status', SyncStatus::Pending)->count());
        $this->assertSame('MO-0001', Order::orderBy('id')->value('number'));
        $this->assertSame('MO-0020', Order::orderByDesc('id')->value('number'));

        $this->assertSame([
            'MO-0003' => ['timeout', 1],
            'MO-0005' => ['rate_limited', 2],
            'MO-0007' => ['unavailable', null],
            'MO-0009' => ['validation_failed', null],
            'MO-0011' => ['unauthorized', null],
        ], $this->simulations());
    }

    public function test_seeding_twice_produces_identical_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = [$this->orders(), $this->simulations()];

        DB::table('orders')->delete();
        $this->seed(DatabaseSeeder::class);

        // Ids differ after the delete, so rows are compared by order number.
        $this->assertSame($first, [$this->orders(), $this->simulations()]);
    }

    private function orders(): array
    {
        return DB::table('orders')
            ->orderBy('number')
            ->get(['number', 'customer_name', 'total_cents', 'currency', 'sync_status',
                'marketplace_order_id', 'last_error_code', 'attempts_count', 'last_attempt_at'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function simulations(): array
    {
        return DB::table('simulated_failures')
            ->join('orders', 'orders.id', '=', 'simulated_failures.order_id')
            ->orderBy('orders.number')
            ->get(['orders.number', 'simulated_failures.error_code', 'simulated_failures.remaining'])
            ->mapWithKeys(fn ($row) => [$row->number => [$row->error_code, $row->remaining]])
            ->all();
    }
}
