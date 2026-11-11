<?php

namespace Tests\Unit;

use App\Enums\AttemptResult;
use App\Enums\AttemptTrigger;
use App\Enums\SyncErrorCode;
use App\Enums\SyncStatus;
use PHPUnit\Framework\TestCase;

class EnumValuesTest extends TestCase
{
    public function test_enum_values_match_the_spec(): void
    {
        $this->assertSame(
            ['timeout', 'unavailable', 'rate_limited', 'validation_failed', 'unauthorized'],
            array_column(SyncErrorCode::cases(), 'value'),
        );
        $this->assertSame(['pending', 'synced', 'failed'], array_column(SyncStatus::cases(), 'value'));
        $this->assertSame(['initial', 'retry'], array_column(AttemptTrigger::cases(), 'value'));
        $this->assertSame(['success', 'failed'], array_column(AttemptResult::cases(), 'value'));
    }
}
