<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Generator;
use RuntimeException;
use Chronhub\Storm\Message\Message;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Storm\Tracker\TrackMessage;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Larastorm\Tests\Double\SomeCommand;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Larastorm\Support\Bridge\HandleTransactionalCommand;
use Chronhub\Storm\Contracts\Chronicler\TransactionalEventableChronicler;

final class HandleTransactionalCommandTest extends ProphecyTestCase
{
    private TransactionalEventableChronicler|ObjectProphecy $chronicler;

    private Message $message;

    public function setup(): void
    {
        $this->chronicler = $this->prophesize(TransactionalEventableChronicler::class);
        $this->message = new Message(SomeCommand::fromContent(['name' => 'stephbug']));
    }

    /**
     * @test
     */
    public function it_begin_transaction_on_dispatch_command(): void
    {
        $this->chronicler->beginTransaction()->shouldBeCalledOnce();

        $subscriber = new HandleTransactionalCommand($this->chronicler->reveal());

        $tracker = new TrackMessage();
        $story = $tracker->newStory(Reporter::DISPATCH_EVENT);
        $story->withMessage($this->message);

        $subscriber->attachToReporter($tracker);

        $tracker->disclose($story);
    }

    /**
     * @test
     */
    public function it_commit_transaction_on_finalize_when_no_exception_found_in_context_and_chronicler_in_transaction(): void
    {
        $this->chronicler->inTransaction()->willReturn(true)->shouldBeCalled();
        $this->chronicler->commitTransaction()->shouldBeCalled();

        $subscriber = new HandleTransactionalCommand($this->chronicler->reveal());

        $tracker = new TrackMessage();
        $story = $tracker->newStory(Reporter::FINALIZE_EVENT);
        $story->withMessage($this->message);

        $this->assertFalse($story->hasException());

        $subscriber->attachToReporter($tracker);

        $tracker->disclose($story);
    }

    /**
     * @test
     */
    public function it_does_not_commit_transaction_when_chronicler_not_in_transaction(): void
    {
        $this->chronicler->inTransaction()->willReturn(false)->shouldBeCalledOnce();

        $this->chronicler->commitTransaction()->shouldNotBeCalled();
        $subscriber = new HandleTransactionalCommand($this->chronicler->reveal());

        $tracker = new TrackMessage();
        $story = $tracker->newStory(Reporter::FINALIZE_EVENT);
        $story->withMessage($this->message);

        $this->assertFalse($story->hasException());

        $subscriber->attachToReporter($tracker);

        $tracker->disclose($story);
    }

    /**
     * @test
     */
    public function it_rollback_transaction_when_context_has_exception(): void
    {
        $this->chronicler->inTransaction()->willReturn(true)->shouldBeCalledOnce();

        $this->chronicler->commitTransaction()->shouldNotBeCalled();
        $this->chronicler->rollbackTransaction()->shouldBeCalledOnce();

        $subscriber = new HandleTransactionalCommand($this->chronicler->reveal());

        $tracker = new TrackMessage();
        $context = $tracker->newStory(Reporter::FINALIZE_EVENT);
        $context->withMessage($this->message);
        $context->withRaisedException(new RuntimeException('failed'));

        $subscriber->attachToReporter($tracker);

        $tracker->disclose($context);
    }

    /**
     * @test
     *
     * @dataProvider provideNotHandledEventStore
     */
    public function it_does_not_commit_transaction_if_chronicler_is_not_transactional_and_eventable(Chronicler $chronicler): void
    {
        $subscriber = new HandleTransactionalCommand($chronicler);

        $tracker = new TrackMessage();
        $story = $tracker->newStory(Reporter::FINALIZE_EVENT);
        $story->withMessage($this->message);

        $this->assertFalse($story->hasException());

        $subscriber->attachToReporter($tracker);

        $tracker->disclose($story);
    }

    public function provideNotHandledEventStore(): Generator
    {
        yield [$this->prophesize(Chronicler::class)->reveal()];
        yield [$this->prophesize(ChroniclerDecorator::class)->reveal()];
        yield [$this->prophesize(ChroniclerConnection::class)->reveal()];
        yield [$this->prophesize(TransactionalChronicler::class)->reveal()];
    }
}
