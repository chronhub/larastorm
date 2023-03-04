<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Generator;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Message\Message;
use Chronhub\Storm\Stream\StreamName;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Tracker\TrackMessage;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Chronicler\TrackStream;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Storm\Chronicler\EventChronicler;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Larastorm\Tests\Double\SomeCommand;
use Chronhub\Storm\Contracts\Message\EventHeader;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Larastorm\Support\Bridge\MakeCausationCommand;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;

/**
 * @coversDefaultClass \Chronhub\Larastorm\Support\Bridge\MakeCausationCommand
 */
final class MakeCausationCommandTest extends UnitTestCase
{
    #[DataProvider('provideStreamEventName')]
    #[Test]
    public function it_add_correlation_headers_from_dispatched_command_on_stream_event(string $streamEventName): void
    {
        $command = (SomeCommand::fromContent(['foo' => 'bar']))
            ->withHeaders(
                [
                    Header::EVENT_ID => '123',
                    Header::EVENT_TYPE => SomeCommand::class,
                ]
            );

        $messageTracker = new TrackMessage();
        $messageStory = $messageTracker->newStory(Reporter::DISPATCH_EVENT);
        $messageStory->withMessage(new Message($command));

        $event = SomeEvent::fromContent(['foo' => 'bar']);
        $stream = new Stream(new StreamName('baz'), [$event]);

        $streamTracker = new TrackStream();
        $streamStory = $streamTracker->newStory($streamEventName);
        $streamStory->deferred(fn (): Stream => $stream);

        $chronicler = $this->createMock(Chronicler::class);
        $eventChronicler = new EventChronicler($chronicler, $streamTracker);

        $subscriber = new MakeCausationCommand();
        $subscriber->attachToReporter($messageTracker);
        $subscriber->attachToChronicler($eventChronicler);

        $messageTracker->disclose($messageStory);

        $this->assertInstanceOf(SomeCommand::class, ReflectionProperty::getProperty($subscriber, 'currentCommand'));

        $streamTracker->disclose($streamStory);

        $decoratedEvent = $streamStory->promise()->events()->current();

        $this->assertEquals([
            EventHeader::EVENT_CAUSATION_ID => '123',
            EventHeader::EVENT_CAUSATION_TYPE => SomeCommand::class,
        ], $decoratedEvent->headers());

        $finalizeMessageStory = $messageTracker->newStory(Reporter::FINALIZE_EVENT);
        $messageTracker->disclose($finalizeMessageStory);

        $this->assertNull(ReflectionProperty::getProperty($subscriber, 'currentCommand'));
    }

    #[DataProvider('provideStreamEventName')]
    #[Test]
    public function it_does_not_add_correlation_headers_if_already_exists(string $streamEventName): void
    {
        $message = (SomeCommand::fromContent(['foo' => 'bar']))
            ->withHeaders(
                [
                    Header::EVENT_ID => '123',
                    Header::EVENT_TYPE => SomeCommand::class,
                ]
            );

        $messageTracker = new TrackMessage();
        $dispatchMessageStory = $messageTracker->newStory(Reporter::DISPATCH_EVENT);
        $dispatchMessageStory->withMessage(new Message($message));

        $event = (SomeEvent::fromContent(['foo' => 'bar']))->withHeaders(
            [
                EventHeader::EVENT_CAUSATION_ID => '321',
                EventHeader::EVENT_CAUSATION_TYPE => 'another-command',
            ]
        );

        $stream = new Stream(new StreamName('baz'), [$event]);

        $streamTracker = new TrackStream();
        $streamStory = $streamTracker->newStory($streamEventName);
        $streamStory->deferred(fn (): Stream => $stream);

        $chronicler = $this->createMock(Chronicler::class);

        $eventChronicler = new EventChronicler($chronicler, $streamTracker);

        $subscriber = new MakeCausationCommand();
        $subscriber->attachToReporter($messageTracker);
        $subscriber->attachToChronicler($eventChronicler);

        $messageTracker->disclose($dispatchMessageStory);

        $this->assertInstanceOf(SomeCommand::class, ReflectionProperty::getProperty($subscriber, 'currentCommand'));

        $streamTracker->disclose($streamStory);

        $decoratedEvent = $streamStory->promise()->events()->current();

        $this->assertEquals([
            EventHeader::EVENT_CAUSATION_ID => '321',
            EventHeader::EVENT_CAUSATION_TYPE => 'another-command',
        ], $decoratedEvent->headers());

        $finalizeMessageStory = $messageTracker->newStory(Reporter::FINALIZE_EVENT);
        $messageTracker->disclose($finalizeMessageStory);

        $this->assertNull(ReflectionProperty::getProperty($subscriber, 'currentCommand'));
    }

    public static function provideStreamEventName(): Generator
    {
        yield [EventableChronicler::FIRST_COMMIT_EVENT];
        yield [EventableChronicler::PERSIST_STREAM_EVENT];
    }
}
