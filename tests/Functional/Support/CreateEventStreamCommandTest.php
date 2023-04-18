<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Support\Console\CreateEventStreamCommand;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerFactory;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CreateEventStreamCommand::class)]
final class CreateEventStreamCommandTest extends OrchestraTestCase
{
    private Chronicler $eventStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStore = Chronicle::shouldUse('in_memory', InMemoryChroniclerFactory::class)
            ->create('standalone');
    }

    protected function getPackageProviders($app): array
    {
        return [ChroniclerServiceProvider::class];
    }

    public function testCreateStreamCommand(): void
    {
        $streamName = new StreamName('foo');

        $this->assertFalse($this->eventStore->hasStream($streamName));

        $this
            ->callArtisan(['stream' => $streamName->name, 'chronicler' => 'standalone'])
            ->expectsOutput('Stream foo created')
            ->assertExitCode(0)
            ->run();

        $this->assertTrue($this->eventStore->hasStream($streamName));
    }

    public function testErrorPrintedWhenStreamAlreadyExists(): void
    {
        $streamName = new StreamName('foo');

        $this->assertFalse($this->eventStore->hasStream($streamName));

        $this
            ->callArtisan(['stream' => $streamName->name, 'chronicler' => 'standalone'])
            ->expectsOutput('Stream foo created')
            ->assertExitCode(0)
            ->run();

        $this->assertTrue($this->eventStore->hasStream($streamName));

        $this->callArtisan(['stream' => $streamName->name, 'chronicler' => 'standalone'])
            ->expectsOutput('Stream foo already exists')
            ->assertExitCode(1)
            ->run();
    }

    private function callArtisan(array $arguments): PendingCommand|int
    {
        return $this->artisan('stream:create', $arguments);
    }
}
