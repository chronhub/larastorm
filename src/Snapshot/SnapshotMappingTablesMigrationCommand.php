<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Snapshot;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'snapshot:migrate',
    description: 'Create snapshot mapping tables migration',
)]
class SnapshotMappingTablesMigrationCommand extends Command
{
    protected $signature = 'snapshot:migrate';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
