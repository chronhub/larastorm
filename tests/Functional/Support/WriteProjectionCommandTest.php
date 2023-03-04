<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Generator;
use InvalidArgumentException;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Storm\Projector\ProjectionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Storm\Projector\InMemoryProjectionQueryScope;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Contracts\Projector\PersistentProjector;
use Chronhub\Larastorm\Support\Console\WriteProjectionCommand;
use function str_starts_with;

#[CoversClass(WriteProjectionCommand::class)]
final class WriteProjectionCommandTest extends OrchestraTestCase
{
    private ProjectorManager $projector;

    private StreamName $streamName;

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
            'scope' => InMemoryProjectionQueryScope::class,
        ]);

        $this->projector = Project::setDefaultDriver('in_memory')->create('testing');
        $this->streamName = new StreamName('transaction');
    }

    #[DataProvider('provideOperation')]
    #[Test]
    public function it_mark_projection(string $operation, ProjectionStatus $status): void
    {
        $projection = $this->setUpProjection();

        $this->assertEquals('idle', $this->projector->statusOf('transaction'));

        $this->artisan(
            'projector:write',
            ['operation' => $operation, 'stream' => 'transaction', 'projector' => 'testing']
        )
            ->expectsConfirmation("Are you sure you want to $operation stream transaction", 'yes')
            ->expectsOutput("Operation $operation on transaction projection successful")
            ->run();

        $this->assertEquals($status->value, $this->projector->statusOf('transaction'));

        $projection->run(false);

        if (str_starts_with($status->value, 'deleting')) {
            $this->assertFalse($this->projector->exists('transaction'));
        } else {
            $this->assertEquals('idle', $this->projector->statusOf('transaction'));
        }
    }

    #[DataProvider('provideOperation')]
    #[Test]
    public function it_print_error_when_projection_not_found(string $operation): void
    {
        $this->artisan(
            'projector:write',
            ['operation' => $operation, 'stream' => 'foo', 'projector' => 'testing']
        )
            ->expectsOutput('Projection not found with stream foo')
            ->run();
    }

    #[DataProvider('provideOperation')]
    #[Test]
    public function it_abort_operation_on_answering_question(string $operation): void
    {
        $this->setUpProjection();

        $this->assertEquals('idle', $this->projector->statusOf('transaction'));

        $this->artisan(
            'projector:write',
            ['operation' => $operation, 'stream' => 'transaction', 'projector' => 'testing']
        )
            ->expectsConfirmation("Are you sure you want to $operation stream transaction")
            ->expectsOutput("Operation $operation on stream transaction aborted")
            ->run();
    }

    #[Test]
    public function it_raise_exception_with_invalid_operation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->setUpProjection();

        $this->assertEquals('idle', $this->projector->statusOf('transaction'));

        $this->artisan(
            'projector:write',
            ['operation' => 'invalid_op', 'stream' => 'transaction', 'projector' => 'testing']
        )
            ->run();
    }

    #[DataProvider('provideOperation')]
    #[Test]
    public function it_raise_exception_with_invalid_projector(string $operation): void
    {
        $this->expectException(\Chronhub\Storm\Projector\Exceptions\InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name not_defined is not defined');

        $this->artisan(
            'projector:write',
            ['operation' => $operation, 'stream' => 'transaction', 'projector' => 'not_defined']
        )
            ->run();
    }

    private function setUpProjection(): PersistentProjector
    {
        $this->app['es.in_memory']->firstCommit(new Stream($this->streamName));

        $this->assertFalse($this->projector->exists($this->streamName->name));

        $projection = $this->projector->projectProjection($this->streamName->name);

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
        return[
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
