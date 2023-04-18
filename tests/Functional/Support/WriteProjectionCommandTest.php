<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Support\Console\WriteProjectionCommand;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Projector\PersistentProjector;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException as ProjectorException;
use Chronhub\Storm\Projector\InMemoryQueryScope;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use function str_starts_with;

#[CoversClass(WriteProjectionCommand::class)]
final class WriteProjectionCommandTest extends OrchestraTestCase
{
    private ProjectorManagerInterface $projector;

    private StreamName $streamName;

    private string $projectionName;

    protected function setUp(): void
    {
        parent::setUp();

        $commands = Artisan::all();
        $this->assertArrayHasKey('projector:write', $commands);

        $this->app->singleton('es.in_memory',
            fn (): Chronicler => Chronicle::setDefaultDriver('in_memory')->create('standalone')
        );

        $this->app['config']->set('projector.projectors.in_memory.testing', [
            'chronicler' => 'es.in_memory',
            'provider' => 'in_memory',
            'options' => 'in_memory',
            'scope' => InMemoryQueryScope::class,
        ]);

        $this->projector = Project::setDefaultDriver('in_memory')->create('testing');
        $this->streamName = new StreamName('transaction');
        $this->projectionName = 'subtract';
    }

    #[DataProvider('provideOperation')]
    public function testUpdateProjection(string $operation, ProjectionStatus $status): void
    {
        $projection = $this->setUpProjection();

        $this->assertEquals('idle', $this->projector->statusOf($this->projectionName));

        $this
            ->callArtisan(['operation' => $operation, 'projection' => $this->projectionName, 'projector' => 'testing'])
            ->expectsConfirmation("Are you sure you want to $operation projection $this->projectionName", 'yes')
            ->expectsOutput("Operation $operation on $this->projectionName projection successful")
            ->assertExitCode(0)
            ->run();

        $this->assertEquals($status->value, $this->projector->statusOf($this->projectionName));

        $projection->run(false);

        if (str_starts_with($status->value, 'deleting')) {
            $this->assertFalse($this->projector->exists($this->projectionName));
        } else {
            $this->assertEquals('idle', $this->projector->statusOf($this->projectionName));
        }
    }

    #[DataProvider('provideOperation')]
    public function testErrorPrintedWhenProjectionNotFound(string $operation): void
    {
        $this
            ->callArtisan(['operation' => $operation, 'projection' => 'foo', 'projector' => 'testing'])
            ->expectsOutput('Projection not found with name foo')
            ->run();
    }

    #[DataProvider('provideOperation')]
    public function testAbortOperation(string $operation): void
    {
        $this->setUpProjection();

        $this->assertEquals('idle', $this->projector->statusOf($this->projectionName));

        $this->callArtisan(['operation' => $operation, 'projection' => $this->projectionName, 'projector' => 'testing'])
            ->expectsConfirmation("Are you sure you want to $operation projection $this->projectionName", 'no')
            ->expectsOutput("Operation $operation on projection $this->projectionName aborted")
            ->assertExitCode(1)
            ->run();
    }

    public function testExceptionRaisedWithInvalidOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->setUpProjection();

        $this->assertEquals('idle', $this->projector->statusOf($this->projectionName));

        $this
            ->callArtisan(['operation' => 'invalid_op', 'projection' => $this->projectionName, 'projector' => 'testing'])
            ->run();
    }

    #[DataProvider('provideOperation')]
    public function testExceptionRaisedWithInvalidProjectorName(string $operation): void
    {
        $this->expectException(ProjectorException::class);
        $this->expectExceptionMessage('Projector configuration with name not_defined is not defined');

        $this
            ->callArtisan(['operation' => $operation, 'projection' => $this->projectionName, 'projector' => 'not_defined'])
            ->run();
    }

    private function setUpProjection(): PersistentProjector
    {
        $this->app['es.in_memory']->firstCommit(new Stream($this->streamName));

        $this->assertFalse($this->projector->exists($this->projectionName));

        $projection = $this->projector->emitter($this->projectionName);

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams($this->streamName->name)
            ->when(['foo' => function () {
                //
            }])
            ->run(false);

        return $projection;
    }

    public static function provideOperation(): Generator
    {
        yield ['stop', ProjectionStatus::STOPPING];
        yield ['reset', ProjectionStatus::RESETTING];
        yield ['delete', ProjectionStatus::DELETING];
        yield ['deleteIncl', ProjectionStatus::DELETING_WITH_EMITTED_EVENTS];
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClockServiceProvider::class,
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }

    private function callArtisan(array $arguments = []): PendingCommand|int
    {
        return $this->artisan('projector:write', $arguments);
    }
}
