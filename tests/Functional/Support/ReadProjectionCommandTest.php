<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Support\Console\ReadProjectionCommand;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException as ProjectorException;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Chronhub\Storm\Projector\InMemoryQueryScope;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ReadProjectionCommand::class)]
final class ReadProjectionCommandTest extends OrchestraTestCase
{
    private ProjectorManagerInterface $projector;

    private StreamName $streamName;

    private string $projectionName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(
            'es.in_memory',
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
        $this->projectionName = 'add';
    }

    public function testCommandIsLoaded(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('projector:read', $commands);
    }

    #[DataProvider('provideField')]
    public function testReadProjection(string $field): void
    {
        $this->setUpProjection();
        $this->assertEquals([], $this->projector->stateOf($this->projectionName));

        $this
            ->callArtisan(['field' => $field, 'projection' => $this->projectionName, 'projector' => 'testing'])
            ->expectsOutputToContain("$field of $this->projectionName projection is")
            ->assertExitCode(0)
            ->run();
    }

    #[DataProvider('provideField')]
    public function testExceptionRaisedWhenProjectionNotFound(string $field): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this
            ->callArtisan(['field' => $field, 'projection' => 'not_defined', 'projector' => 'testing'])
            ->run();
    }

    public function testExceptionRaisedWithInvalidField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid field');

        $this->setUpProjection();

        $this->assertEquals('idle', $this->projector->statusOf($this->projectionName));

        $this
            ->callArtisan(['field' => 'invalid_op', 'projection' => $this->projectionName, 'projector' => 'testing'])
            ->run();
    }

    #[DataProvider('provideField')]
    public function testExceptionRaisedWithInvalidProjectorName(string $field): void
    {
        $this->expectException(ProjectorException::class);
        $this->expectExceptionMessage('Projector configuration with name not_defined is not defined');

        $this
            ->callArtisan(['field' => $field, 'projection' => $this->projectionName, 'projector' => 'not_defined'])
            ->run();
    }

    private function setUpProjection(): void
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
    }

    public static function provideField(): Generator
    {
        yield ['state'];
        yield ['positions'];
        yield ['status'];
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
        return $this->artisan('projector:read', $arguments);
    }
}
