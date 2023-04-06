<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Double;

use RuntimeException;
use Throwable;

final class SomeException extends RuntimeException
{
    public function __construct(string $message = 'some error occurred', int $code = 0, ?Throwable $previousException = null)
    {
        parent::__construct($message, $code, $previousException);
    }

    public function setCodeAsString(string $errorCode): void
    {
        $this->code = $errorCode;
    }
}
