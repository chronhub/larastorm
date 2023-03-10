<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Chronhub\Storm\Stream\StreamName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Contracts\Chronicler\InMemoryChronicler;
use Chronhub\Larastorm\Support\Console\CreateEventStreamCommand;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider;

#[CoversClass(CreateEventStreamCommand::class)]
final class CreateEventStreamCommandTest extends OrchestraTestCase
{
    private InMemoryChronicler $eventStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStore = Chronicle::shouldUse('in_memory', InMemoryChroniclerProvider::class)
            ->create('standalone');
    }

    protected function getPackageProviders($app): array
    {
        return [ChroniclerServiceProvider::class];
    }

    #[Test]
    public function it_create_stream_from_console(): void
    {
        $streamName = new StreamName('foo');

        $this->assertFalse($this->eventStore->hasStream($streamName));

        $this->artisan(
            'stream:create', ['stream' => $streamName->name, 'chronicler' => 'standalone']
        )->run();

        $this->assertTrue($this->eventStore->hasStream($streamName));
    }

    #[Test]
    public function it_print_error_message_if_stream_already_exist(): void
    {
        $streamName = new StreamName('foo');

        $this->assertFalse($this->eventStore->hasStream($streamName));

        $this->artisan(
            'stream:create', ['stream' => $streamName->name, 'chronicler' => 'standalone']
        )
            ->expectsOutput('Stream foo created')
            ->run();

        $this->assertTrue($this->eventStore->hasStream($streamName));

        $this->artisan(
            'stream:create', ['stream' => $streamName->name, 'chronicler' => 'standalone']
        )
            ->expectsOutput('Stream foo already exists')
            ->run();
    }
}
