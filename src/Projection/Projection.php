<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Chronhub\Storm\Contracts\Projector\ProjectionModel;
use JsonSerializable;

final readonly class Projection implements ProjectionModel, JsonSerializable
{
    private string $position;

    private string $state;

    public function __construct(
        private string $name,
        private string $status,
        ?string $position,
        ?string $state,
        private ?string $lockedUntil
    ) {
        $this->position = $position ?? '{}';
        $this->state = $state ?? '{}';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): string
    {
        return $this->position;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function lockedUntil(): ?string
    {
        return $this->lockedUntil;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'position' => $this->position,
            'state' => $this->state,
            'locked_until' => $this->lockedUntil,
        ];
    }
}
