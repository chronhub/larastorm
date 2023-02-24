<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use Chronhub\Storm\Contracts\Projector\Projector;
use Chronhub\Larastorm\Support\Console\CreatePersistentProjectionCommand;

final class CreatePersistentProjectionCommandStub extends CreatePersistentProjectionCommand
{
    protected $signature = 'test:create-projection { projector } { --in_background= } { --signal= }';

    public function __invoke(): int
    {
        $this->projector = $this->project('foo', null);

        $this->projector
            ->fromStreams('foo')
            ->whenAny(function () {
                //
            })
            ->run($this->shouldRunInBackground());

        return self::SUCCESS;
    }

    public function getProjector(): Projector
    {
        return $this->projector;
    }

    public function getDefaultSignal(): bool
    {
        return $this->dispatchSignal;
    }

    public function isRunningInBackground(): bool
    {
        return $this->dispatchSignal;
    }
}
