<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Support\Supervisor;

use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Support\Supervisor\PhpBinary;
use Chronhub\Larastorm\Support\Supervisor\MakeCommandAsString;

/**
 * @coversDefaultClass \Chronhub\Larastorm\Support\Supervisor\MakeCommandAsString
 */
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
