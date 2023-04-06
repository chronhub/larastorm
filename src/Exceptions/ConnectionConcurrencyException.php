<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Exceptions;

use Chronhub\Storm\Chronicler\Exceptions\ConcurrencyException;
use Illuminate\Database\QueryException;
use function is_array;
use function sprintf;

class ConnectionConcurrencyException extends ConcurrencyException
{
    public static function failedToAcquireLock(): self
    {
        return new self('Failed to acquire lock');
    }

    public static function fromUnlockStreamFailure(QueryException $exception): self
    {
        $message = "Events or Aggregates ids have already been used in the same stream\n";

        $errorInfo = $exception->errorInfo;

        if (is_array($errorInfo)) {
            $message .= sprintf("Error %s\n Error-Info %s", $errorInfo[0], $errorInfo[2]);
        }

        return new self($message);
    }
}
