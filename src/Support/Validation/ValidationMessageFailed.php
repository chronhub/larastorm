<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Validation;

use Chronhub\Storm\Message\Message;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\Validation\Validator;
use RuntimeException;

class ValidationMessageFailed extends RuntimeException
{
    private static Validator $validator;

    private static Message $validatedMessage;

    public static function withValidator(Validator $validator, Message $message): self
    {
        self::$validator = $validator;
        self::$validatedMessage = $message;

        $exceptionMessage = "Validation rules fails:\n";
        $exceptionMessage .= $validator->errors();

        return new self($exceptionMessage);
    }

    public function getValidator(): Validator
    {
        return self::$validator;
    }

    public function failedMessage(): Message
    {
        return self::$validatedMessage;
    }

    public function errors(): MessageBag
    {
        return $this->getValidator()->errors();
    }
}
