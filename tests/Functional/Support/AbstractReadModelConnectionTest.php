<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Support\ReadModel\AbstractReadModelConnection;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeEvent;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Uid\Uuid;

#[CoversClass(AbstractReadModelConnection::class)]
final class AbstractReadModelConnectionTest extends OrchestraTestCase
{
    use RefreshDatabase;

    private Connection $connection;

    private string $tableName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->app['db']->connection();
        $this->tableName = 'read_customer';
    }

    public function testInitializeReadModel(): void
    {
        $readModel = $this->newReadModel();

        $this->assertFalse($readModel->isInitialized());
        $this->assertFalse($this->connection->getSchemaBuilder()->hasTable($this->tableName));

        $readModel->initialize();

        $this->assertTrue($readModel->isInitialized());
        $this->assertTrue($this->connection->getSchemaBuilder()->hasTable($this->tableName));
    }

    public function testPersistReadModel(): void
    {
        $readModel = $this->newReadModel();

        $this->assertFalse($readModel->isInitialized());

        $readModel->initialize();

        $this->assertTrue($readModel->isInitialized());

        $customerId = Uuid::v4()->jsonSerialize();
        $event = SomeEvent::fromContent([
            'customer_id' => $customerId,
            'customer_email' => 'chronhubgit@gmail.com',
        ]);

        $readModel->stack('insert', $event->content['customer_id'], $event->content['customer_email']);

        $readModel->persist();

        $result = $this->connection->table($this->tableName)->find($customerId);

        $this->assertEquals($customerId, $result->id);
        $this->assertEquals('chronhubgit@gmail.com', $result->email);
    }

    public function testResetReadModel(): void
    {
        $readModel = $this->newReadModel();

        $this->assertFalse($readModel->isInitialized());

        $readModel->initialize();

        $this->assertTrue($readModel->isInitialized());

        $customerId = Uuid::v4()->jsonSerialize();
        $event = SomeEvent::fromContent([
            'customer_id' => $customerId,
            'customer_email' => 'chronhubgit@gmail.com',
        ]);

        $readModel->stack('insert', $event->content['customer_id'], $event->content['customer_email']);

        $readModel->persist();

        $result = $this->connection->table($this->tableName)->find($customerId);

        $this->assertEquals($customerId, $result->id);
        $this->assertEquals('chronhubgit@gmail.com', $result->email);

        $readModel->reset();

        $this->assertEmpty($this->connection->table($this->tableName)->get());
    }

    public function testDeleteReadModel(): void
    {
        $readModel = $this->newReadModel();

        $this->assertFalse($readModel->isInitialized());

        $readModel->initialize();

        $this->assertTrue($readModel->isInitialized());

        $customerId = Uuid::v4()->jsonSerialize();
        $event = SomeEvent::fromContent([
            'customer_id' => $customerId,
            'customer_email' => 'chronhubgit@gmail.com',
        ]);

        $readModel->stack('insert', $event->content['customer_id'], $event->content['customer_email']);

        $readModel->persist();

        $result = $this->connection->table($this->tableName)->find($customerId);

        $this->assertEquals($customerId, $result->id);
        $this->assertEquals('chronhubgit@gmail.com', $result->email);

        $readModel->down();

        $this->assertFalse($this->connection->getSchemaBuilder()->hasTable($this->tableName));
    }

    private function newReadModel(): ReadModel
    {
        $connection = $this->connection;
        $tableName = $this->tableName;

        return new class($connection, $tableName) extends AbstractReadModelConnection
        {
            public function __construct(Connection $connection, private readonly string $tableName)
            {
                parent::__construct($connection);
            }

            public function insert(string $uid, string $email): void
            {
                $this->queryBuilder()->insert([$this->getKey() => $uid, 'email' => $email]);
            }

            protected function queryBuilder(): Builder
            {
                return $this->connection->table($this->tableName());
            }

            protected function getKey(): string
            {
                return 'id';
            }

            protected function up(): callable
            {
                return static function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('email');
                };
            }

            protected function tableName(): string
            {
                return $this->tableName;
            }
        };
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
