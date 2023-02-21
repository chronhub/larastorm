<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Generator;
use InvalidArgumentException;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Support\Facades\Artisan;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Storm\Projector\InMemoryProjectionQueryScope;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;

final class ReadProjectionCommandTest extends OrchestraTestCase
{
    private ProjectorManager $projector;

    private StreamName $streamName;

    protected function setUp(): void
    {
        parent::setUp();

        $commands = Artisan::all();
        $this->assertArrayHasKey('projector:read', $commands);

        $this->app->singleton('es.in_memory', function (): Chronicler {
            return Chronicle::setDefaultDriver('in_memory')->create('standalone');
        });

        $this->app['config']->set('projector.projectors.in_memory.testing', [
            'chronicler' => 'es.in_memory',
            'provider' => 'in_memory',
            'options' => 'in_memory',
            'scope' => InMemoryProjectionQueryScope::class,
        ]);

        $this->projector = Project::setDefaultDriver('in_memory')->create('testing');
        $this->streamName = new StreamName('transaction');
    }

    /**
     * @test
     *
     * @dataProvider provideField
     */
    public function it_read_projection(string $field): void
    {
        $this->setUpProjection();
        $this->assertEquals([], $this->projector->stateOf('transaction'));

        $this->artisan(
            'projector:read',
            ['field' => $field, 'stream' => 'transaction', 'projector' => 'testing']
        )
            ->expectsOutputToContain("$field of transaction projection is")
            ->run();
    }

    /**
     * @test
     *
     * @dataProvider provideField
     */
    public function it_raise_exception_when_projection_not_found(string $field): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->artisan(
            'projector:read',
            ['field' => $field, 'stream' => 'not_defined', 'projector' => 'testing']
        )
            ->run();
    }

    /**
     * @test
     */
    public function it_raise_exception_with_invalid_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid field');

        $this->setUpProjection();

        $this->assertEquals('idle', $this->projector->statusOf('transaction'));

        $this->artisan(
            'projector:read',
            ['field' => 'invalid_op', 'stream' => 'transaction', 'projector' => 'testing']
        )
            ->run();
    }

    /**
     * @test
     *
     * @dataProvider provideField
     */
    public function it_raise_exception_with_invalid_projector(string $field): void
    {
        $this->expectException(\Chronhub\Storm\Projector\Exceptions\InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name not_defined is not defined');

        $this->artisan(
            'projector:read',
            ['field' => $field, 'stream' => 'transaction', 'projector' => 'not_defined']
        )
            ->run();
    }

    private function setUpProjection(): void
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
    }

    public function provideField(): Generator
    {
        yield ['state'];
        yield ['positions'];
        yield ['status'];
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
