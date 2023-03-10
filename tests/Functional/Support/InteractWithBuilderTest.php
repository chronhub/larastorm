<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Support\ReadModel\InteractWithBuilder;
use Chronhub\Larastorm\Support\ReadModel\ReadModelConnection;

#[CoversClass(InteractWithBuilder::class)]
final class InteractWithBuilderTest extends OrchestraTestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_query_with_builder(): void
    {
        $connection = $this->app->make('db')->connection();

        $readModel = $this->readModelInstance($connection);

        $this->assertFalse($readModel->isInitialized());

        $readModel->initialize();

        $this->assertTrue($readModel->isInitialized());

        $event = SomeEvent::fromContent([
            'customer_id' => '1',
            'customer_email' => 'chronhubgit@gmail.com',
        ]);

        $readModel->stack('query', function (Builder $builder, string $key, SomeEvent $event): void {
            $builder->insert([
                $key => $event->content['customer_id'],
                'email' => $event->content['customer_email'],
            ]);
        }, $event);

        $readModel->persist();

        $result = $connection->table('read_customer')->find(1);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('chronhubgit@gmail.com', $result->email);
    }

    private function readModelInstance(Connection $connection): ReadModel
    {
        return new class($connection) extends ReadModelConnection
        {
            use InteractWithBuilder;

            protected function up(): callable
            {
                return function (Blueprint $table): void {
                    $table->id();
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
