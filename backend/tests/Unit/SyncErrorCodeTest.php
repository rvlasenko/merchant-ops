<?php

namespace Tests\Unit;

use App\Enums\AttemptResult;
use App\Enums\AttemptTrigger;
use App\Enums\SyncErrorCode;
use App\Enums\SyncStatus;
use PHPUnit\Framework\TestCase;

class SyncErrorCodeTest extends TestCase
{
    public function test_only_transient_errors_are_retryable(): void
    {
        $retryable = array_values(array_map(
            fn (SyncErrorCode $code) => $code->value,
            array_filter(SyncErrorCode::cases(), fn (SyncErrorCode $code) => $code->retryable()),
        ));

        $this->assertSame(['timeout', 'unavailable', 'rate_limited'], $retryable);
    }

    public function test_every_error_code_has_a_message(): void
    {
        foreach (SyncErrorCode::cases() as $code) {
            $this->assertNotSame('', $code->message(), "Empty message for {$code->value}");
        }
    }

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
