<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use JsonSerializable;
use Chronhub\Storm\Contracts\Chronicler\EventStreamModel;

final readonly class EventStream implements EventStreamModel, JsonSerializable
{
    public function __construct(private string $streamName,
                                private string $tableName,
                                private ?string $category = null)
    {
    }

    public function realStreamName(): string
    {
        return $this->streamName;
    }

    public function tableName(): string
    {
        return $this->tableName;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function jsonSerialize(): array
    {
        return [
            'real_stream_name' => $this->streamName,
            'stream_name' => $this->tableName,
            'category' => $this->category,
        ];
    }
}
