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

    public function testInsertData(): void
    {
        $connection = $this->app->make('db')->connection();

        $readModel = $this->readModelInstance($connection);

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

        $result = $connection->table('read_customer')->find($customerId);

        $this->assertEquals($customerId, $result->id);
        $this->assertEquals('chronhubgit@gmail.com', $result->email);
    }

    public function testResetTable(): void
    {
        $connection = $this->app->make('db')->connection();

        $readModel = $this->readModelInstance($connection);

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

        $result = $connection->table('read_customer')->find($customerId);

        $this->assertEquals($customerId, $result->id);
        $this->assertEquals('chronhubgit@gmail.com', $result->email);

        $readModel->reset();

        $this->assertEmpty($connection->table('read_customer')->get());
    }

    public function testDeleteTable(): void
    {
        $connection = $this->app->make('db')->connection();

        $readModel = $this->readModelInstance($connection);

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

        $result = $connection->table('read_customer')->find($customerId);

        $this->assertEquals($customerId, $result->id);
        $this->assertEquals('chronhubgit@gmail.com', $result->email);

        $readModel->down();

        $this->assertFalse($connection->getSchemaBuilder()->hasTable('read_customer'));
    }

    private function readModelInstance(Connection $connection): ReadModel
    {
        return new class($connection) extends AbstractReadModelConnection
        {
            public function insert(string $uid, string $email): void
            {
                $this->queryBuilder()->insert([
                    $this->getKey() => $uid,
                    'email' => $email,
                ]);
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
                return function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('email');
                };
            }

            protected function tableName(): string
            {
                return 'read_customer';
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