<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'stream:create', description: 'create first commit for one stream for single stream strategy')]
final class CreateEventStreamCommand extends Command
{
    protected $signature = 'stream:create
                            { stream     : stream name }
                            { chronicler : chronicler name }';

    protected $description = 'Create first commit for one stream for single stream strategy';

    public function handle(): int
    {
        $streamName = new StreamName($this->argument('stream'));

        $name = $this->argument('chronicler');

        $chronicler = Chronicle::create($name);

        if ($chronicler->hasStream($streamName)) {
            $this->error("Stream $streamName already exists");

            return self::FAILURE;
        }

        $chronicler->firstCommit(new Stream($streamName));

        $this->info("Stream $streamName created");

        return self::SUCCESS;
    }
}
