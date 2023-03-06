<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Generator;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Routing\HandleRoute;
use Illuminate\Support\Facades\Artisan;
use Chronhub\Storm\Reporter\ReportEvent;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Reporter\ReportCommand;
use Chronhub\Storm\Tracker\GenericListener;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Symfony\Component\Console\Helper\TableCell;
use Chronhub\Storm\Reporter\DetachMessageListener;
use Chronhub\Storm\Contracts\Tracker\MessageTracker;
use Chronhub\Storm\Reporter\Subscribers\MakeMessage;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Storm\Reporter\Subscribers\ConsumeEvent;
use Chronhub\Storm\Reporter\Subscribers\ConsumeQuery;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;
use Chronhub\Storm\Reporter\Subscribers\ConsumeCommand;
use Chronhub\Storm\Routing\Exceptions\RoutingViolation;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Storm\Reporter\Subscribers\NameReporterService;
use Chronhub\Larastorm\Support\MessageDecorator\DecorateMessage;
use Chronhub\Larastorm\Support\Console\ListMessagerSubscribersCommand;

final class ListMessagerSubscribersCommandTest extends OrchestraTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->assertArrayNotHasKey('messager:list', Artisan::all());

        Artisan::registerCommand(new ListMessagerSubscribersCommand());
    }

    #[DataProvider('provideReporter')]
    #[Test]
    public function it_list_listeners_from_reporter(string $name, string $reporterFqn, string $consumerFqn): void
    {
        $this->app->resolving(Registrar::class, function (Registrar $registrar) use ($name, $consumerFqn): void {
            $registrar
                ->make(DomainType::from($name), 'default')
                ->withProducerStrategy('sync')
                ->withMessageSubscribers($consumerFqn);
        });

        $genericListener = class_basename(GenericListener::class);

        $headers = ['Listener', 'Subscriber class', 'On Event', 'Priority'];

        $rows = [
            [new TableCell($reporterFqn, ['colspan' => 4, 'rowspan' => 1, 'style' => null])],
            [$genericListener, MakeMessage::class, 'dispatch_event', 100000],
            [$genericListener, NameReporterService::class, 'dispatch_event', 99999],
            [$genericListener, DecorateMessage::class, 'dispatch_event', 90000],
            [$genericListener, HandleRoute::class, 'dispatch_event', 20000],
            [$genericListener, $consumerFqn, 'dispatch_event', 0],
        ];

        $this->artisan('messager:list', ['type' => $name, 'name' => 'default'])
            ->expectsTable($headers, $rows)
            ->assertSuccessful()
            ->run();
    }

    #[Test]
    public function it_raise_exception_when_group_is_not_registered(): void
    {
        $this->expectException(RoutingViolation::class);

        $this->expectExceptionMessage('Group with type command and name undefined not defined');
        $this->artisan('messager:list', ['type' => 'command', 'name' => 'undefined'])->run();
    }

    #[Test]
    public function it_sort_listeners_per_event_and_descendant_priority(): void
    {
        $dispatchedEvents = new class() implements MessageSubscriber
        {
            use DetachMessageListener;

            public function attachToReporter(MessageTracker $tracker): void
            {
                $tracker->watch(Reporter::DISPATCH_EVENT, fn () => null, 100);
                $tracker->watch(Reporter::DISPATCH_EVENT, fn () => null, -100);
                $tracker->watch(Reporter::DISPATCH_EVENT, fn () => null);
                $tracker->watch(Reporter::FINALIZE_EVENT, fn () => null, 100);
                $tracker->watch(Reporter::FINALIZE_EVENT, fn () => null, -100);
                $tracker->watch(Reporter::FINALIZE_EVENT, fn () => null);
            }
        };

        $this->app->resolving(Registrar::class, function (Registrar $registrar) use ($dispatchedEvents): void {
            $registrar
                ->make(DomainType::from('event'), 'default')
                ->withProducerStrategy('sync')
                ->withMessageSubscribers(ConsumeEvent::class, $dispatchedEvents);
        });

        $genericListener = class_basename(GenericListener::class);

        $headers = ['Listener', 'Subscriber class', 'On Event', 'Priority'];

        $rows = [
            [new TableCell(ReportEvent::class, ['colspan' => 4, 'rowspan' => 1, 'style' => null])],
            [$genericListener, MakeMessage::class, 'dispatch_event', 100000],
            [$genericListener, NameReporterService::class, 'dispatch_event', 99999],
            [$genericListener, DecorateMessage::class, 'dispatch_event', 90000],
            [$genericListener, HandleRoute::class, 'dispatch_event', 20000],
            [$genericListener, $dispatchedEvents::class, 'dispatch_event', 100],
            [$genericListener, ConsumeEvent::class, 'dispatch_event', 0],
            [$genericListener, $dispatchedEvents::class, 'dispatch_event', 0],
            [$genericListener, $dispatchedEvents::class, 'dispatch_event', -100],
            [$genericListener, $dispatchedEvents::class, 'finalize_event', 0],
            [$genericListener, $dispatchedEvents::class, 'finalize_event', -100],
        ];

        $this->artisan('messager:list', ['type' => 'event', 'name' => 'default'])
            ->expectsTable($headers, $rows)
            ->assertSuccessful()
            ->run();
    }

    public static function provideReporter(): Generator
    {
        yield ['command', ReportCommand::class, ConsumeCommand::class];
        yield ['event', ReportEvent::class, ConsumeEvent::class];
        yield ['query', ReportQuery::class, ConsumeQuery::class];
    }

    protected function getPackageProviders($app): array
    {
        return [MessagerServiceProvider::class, CqrsServiceProvider::class];
    }
}
