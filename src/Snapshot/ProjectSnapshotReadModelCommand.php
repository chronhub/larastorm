<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Snapshot;

use Chronhub\Storm\Contracts\Projector\ProjectorFactory;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;

class ProjectSnapshotReadModelCommand extends Command implements SignalableCommandInterface
{
    protected $signature = 'snapshot:project-read-model
                            { stream_name  : stream name }
                            { projector    : projector name }
                            { --every=1000 : persist snapshot every n version }';

    private ProjectorFactory $projection;

    public function handle(SnapshotProjectionServiceFactory $service): int
    {
        $this->projection = $service->create(
            $this->argument('stream_name'),
            $this->argument('projector'),
            (int) $this->option('every')
        );

        $this->projection->run(true);

        return self::SUCCESS;
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal): void
    {
        $this->projection->stop();
    }
}
