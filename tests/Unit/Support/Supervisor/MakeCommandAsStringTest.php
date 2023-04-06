<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Support\Supervisor;

use Chronhub\Larastorm\Support\Supervisor\MakeCommandAsString;
use Chronhub\Larastorm\Support\Supervisor\PhpBinary;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(MakeCommandAsString::class)]
final class MakeCommandAsStringTest extends UnitTestCase
{
    #[Test]
    public function it_assert_command(): void
    {
        $phpPath = PhpBinary::path();

        $this->assertEquals(
            'exec '.$phpPath.' artisan foo:bar',
            MakeCommandAsString::toCommandString('foo', 'bar')
        );
    }
}
