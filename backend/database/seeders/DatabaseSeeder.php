<?php

namespace Database\Seeders;

use App\Enums\SyncErrorCode;
use App\Models\Order;
use App\Models\SimulatedFailure;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const CUSTOMERS = [
        'Ada Lovelace', 'Alan Turing', 'Grace Hopper', 'Edsger Dijkstra',
        'Barbara Liskov', 'Donald Knuth', 'Margaret Hamilton', 'Ken Thompson',
        'Frances Allen', 'Dennis Ritchie', 'Radia Perlman', 'John McCarthy',
        'Katherine Johnson', 'Niklaus Wirth', 'Shafi Goldwasser', 'Tony Hoare',
        'Hedy Lamarr', 'Leslie Lamport', 'Adele Goldberg', 'Bjarne Stroustrup',
    ];

    /**
     * Simulated marketplace failures: order number => [error code, remaining].
     * A null remaining means the order fails forever.
     */
    private const SIMULATIONS = [
        'MO-0003' => [SyncErrorCode::Timeout, 1],
        'MO-0005' => [SyncErrorCode::RateLimited, 2],
        'MO-0007' => [SyncErrorCode::Unavailable, null],
        'MO-0009' => [SyncErrorCode::ValidationFailed, null],
        'MO-0011' => [SyncErrorCode::Unauthorized, null],
    ];

    /**
     * Seed a fixed, reproducible data set. All values are explicit so the
     * result does not depend on the factory counter.
     */
    public function run(): void
    {
        $orders = Order::factory()
            ->count(count(self::CUSTOMERS))
            ->state(new Sequence(fn (Sequence $sequence) => [
                'number' => sprintf('MO-%04d', $sequence->index + 1),
                'customer_name' => self::CUSTOMERS[$sequence->index],
                'total_cents' => 1000 * ($sequence->index + 1) + 99,
                'currency' => 'EUR',
            ]))
            ->create()
            ->keyBy('number');

        foreach (self::SIMULATIONS as $number => [$code, $remaining]) {
            $factory = SimulatedFailure::factory()->for($orders[$number]);

            ($remaining === null ? $factory->always($code) : $factory->limited($code, $remaining))->create();
        }
    }
}
