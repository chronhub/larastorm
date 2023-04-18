<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Support\Console\ListMessagerSubscribersCommand;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;
use Chronhub\Storm\Contracts\Tracker\MessageTracker;
use Chronhub\Storm\Message\DecorateMessage;
use Chronhub\Storm\Reporter\DetachMessageListener;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Reporter\OnDispatchPriority;
use Chronhub\Storm\Reporter\ReportCommand;
use Chronhub\Storm\Reporter\ReportEvent;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Reporter\Subscribers\ConsumeCommand;
use Chronhub\Storm\Reporter\Subscribers\ConsumeEvent;
use Chronhub\Storm\Reporter\Subscribers\ConsumeQuery;
use Chronhub\Storm\Reporter\Subscribers\MakeMessage;
use Chronhub\Storm\Reporter\Subscribers\NameReporterService;
use Chronhub\Storm\Routing\Exceptions\RoutingViolation;
use Chronhub\Storm\Routing\HandleRoute;
use Chronhub\Storm\Tracker\GenericListener;
use Generator;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Helper\TableCell;

#[CoversClass(ListMessagerSubscribersCommand::class)]
final class ListMessagerSubscribersCommandTest extends OrchestraTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->assertArrayNotHasKey('subscribers:list', Artisan::all());

        Artisan::registerCommand(new ListMessagerSubscribersCommand());
    }

    #[DataProvider('provideReporter')]
    public function testListListeners(string $name, string $reporterFqn, string $consumerFqn): void
    {
        $this->app->resolving(Registrar::class, function (Registrar $registrar) use ($name, $consumerFqn): void {
            $registrar
                ->make(DomainType::from($name), 'default')
                ->withStrategy('sync')
                ->withSubscribers($consumerFqn);
        });

        $genericListener = class_basename(GenericListener::class);

        $headers = ['Listener', 'Subscriber class', 'On Event', 'Priority'];

        $dispatchEvent = Reporter::DISPATCH_EVENT;

        $rows = [
            [new TableCell($reporterFqn, ['colspan' => 4, 'rowspan' => 1, 'style' => null])],
            [$genericListener, MakeMessage::class, $dispatchEvent, OnDispatchPriority::MESSAGE_FACTORY->value],
            [$genericListener, NameReporterService::class, $dispatchEvent, OnDispatchPriority::MESSAGE_FACTORY->value - 1],
            [$genericListener, DecorateMessage::class, $dispatchEvent, OnDispatchPriority::MESSAGE_DECORATOR->value],
            [$genericListener, HandleRoute::class, $dispatchEvent, OnDispatchPriority::ROUTE->value],
            [$genericListener, $consumerFqn, $dispatchEvent, OnDispatchPriority::INVOKE_HANDLER->value],
        ];

        $this->artisan('messager:subscribers', ['type' => $name, 'name' => 'default'])
            ->expectsTable($headers, $rows)
            ->assertSuccessful()
            ->run();
    }

    public function testExceptionRaisedWhenGroupNameIsNotRegistered(): void
    {
        $this->expectException(RoutingViolation::class);

        $this->expectExceptionMessage('Group with type command and name undefined not defined');
        $this->artisan('messager:subscribers', ['type' => 'command', 'name' => 'undefined'])->run();
    }

    public function testSortSubscribersPerEventAndPriority(): void
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
                ->withStrategy('sync')
                ->withSubscribers(ConsumeEvent::class, $dispatchedEvents);
        });

        $genericListener = class_basename(GenericListener::class);

        $headers = ['Listener', 'Subscriber class', 'On Event', 'Priority'];

        $dispatchEvent = Reporter::DISPATCH_EVENT;
        $finalizeEvent = Reporter::FINALIZE_EVENT;

        $rows = [
            [new TableCell(ReportEvent::class, ['colspan' => 4, 'rowspan' => 1, 'style' => null])],
            [$genericListener, MakeMessage::class, $dispatchEvent, OnDispatchPriority::MESSAGE_FACTORY->value],
            [$genericListener, NameReporterService::class, $dispatchEvent, OnDispatchPriority::MESSAGE_FACTORY->value - 1],
            [$genericListener, DecorateMessage::class, $dispatchEvent, OnDispatchPriority::MESSAGE_DECORATOR->value],
            [$genericListener, HandleRoute::class, $dispatchEvent, OnDispatchPriority::ROUTE->value],
            [$genericListener, $dispatchedEvents::class, $dispatchEvent, 100],
            [$genericListener, ConsumeEvent::class, $dispatchEvent, OnDispatchPriority::INVOKE_HANDLER->value],
            [$genericListener, $dispatchedEvents::class, $dispatchEvent, 0],
            [$genericListener, $dispatchedEvents::class, $dispatchEvent, -100],
            [$genericListener, $dispatchedEvents::class, $finalizeEvent, 0],
            [$genericListener, $dispatchedEvents::class, $finalizeEvent, -100],
        ];

        $this->artisan('messager:subscribers', ['type' => 'event', 'name' => 'default'])
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
        return [
            ClockServiceProvider::class,
            MessagerServiceProvider::class,
            CqrsServiceProvider::class,
        ];
    }
}
