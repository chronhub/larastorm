<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use Chronhub\Larastorm\Support\Console\CreatePersistentProjectionCommand;

#[CoversClass(CreatePersistentProjectionCommand::class)]
final class CreatePersistentProjectionCommandTest extends OrchestraTestCase
{
    use RefreshDatabase;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->app['db']->connection();
        $this->assertTrue($this->connection->getSchemaBuilder()->hasTable('projections'));
        $this->assertTrue($this->connection->getSchemaBuilder()->hasTable('event_streams'));
    }

    #[Test]
    public function it_raise_exception_if_projection_table_is_not_set(): void
    {
        $this->connection->getSchemaBuilder()->dropAllTables();

        $this->expectException(QueryException::class);

        Artisan::registerCommand(new CreatePersistentProjectionCommandStub());

        $command = $this->artisan('test:create-projection', ['projector' => 'emit', '--signal' => 0]);

        $command->run();
    }

    #[Test]
    public function it_raise_exception_if_projector_name_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name foo is not defined');

        Artisan::registerCommand(new CreatePersistentProjectionCommandStub());

        $command = $this->artisan('test:create-projection', ['projector' => 'foo', '--signal' => 0]);

        $command->run();
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
