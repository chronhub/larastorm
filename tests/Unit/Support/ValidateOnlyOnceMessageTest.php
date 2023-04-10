<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Support;

use Chronhub\Larastorm\Support\Validation\MessagePreValidation;
use Chronhub\Larastorm\Support\Validation\MessageValidation;
use Chronhub\Larastorm\Support\Validation\ValidateOnlyOnceMessage;
use Chronhub\Larastorm\Support\Validation\ValidationMessageFailed;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeCommand;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeEvent;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeQuery;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Message\AsyncMessage;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Message\HasConstructableContent;
use Chronhub\Storm\Message\Message;
use Chronhub\Storm\Producer\LogicalProducer;
use Chronhub\Storm\Producer\ProducerStrategy;
use Chronhub\Storm\Reporter\DomainCommand;
use Chronhub\Storm\Tracker\TrackMessage;
use Generator;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

class ValidateOnlyOnceMessageTest extends UnitTestCase
{
    private Factory|MockObject $validator;

    private LogicalProducer $producerUnity;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(Factory::class);
        $this->producerUnity = new LogicalProducer();
    }

    #[DataProvider('provideNoValidatableEvent')]
    public function testReturnEarlyWhenMessageEventCanNotBeValidated(object $event): void
    {
        $this->validator->expects($this->never())->method('make');

        $message = new Message($event);

        $this->processValidation($message);
    }

    #[DataProvider('provideSyncLogicalHeaderForValidation')]
    public function testSyncCommandToValidate(bool $dispatched, string $strategy): void
    {
        $validator = $this->createMock(Validator::class);
        $validator->expects($this->once())->method('fails')->willReturn(false);

        $this->validator
            ->expects($this->once())
            ->method('make')
            ->with(['name' => 'steph bug'], ['name' => 'required'])
            ->willReturn($validator);

        $event = $this->provideCommandToValidate(['name' => 'steph bug']);
        $message = new Message($event, [Header::EVENT_DISPATCHED => $dispatched, Header::EVENT_STRATEGY => $strategy]);

        $this->processValidation($message);
    }

    public function testCommandToPreValidate(): void
    {
        $validator = $this->createMock(Validator::class);
        $validator->expects($this->once())->method('fails')->willReturn(false);

        $this->validator
            ->expects($this->once())
            ->method('make')
            ->with(['name' => 'steph bug'], ['name' => 'required'])
            ->willReturn($validator);

        $event = $this->provideCommandToPreValidate(['name' => 'steph bug']);
        $message = new Message($event, [Header::EVENT_DISPATCHED => false, Header::EVENT_STRATEGY => ProducerStrategy::ASYNC->value]);

        $this->processValidation($message);
    }

    public function testAsyncCommandToPreValidate(): void
    {
        $validator = $this->createMock(Validator::class);
        $validator->expects($this->once())->method('fails')->willReturn(false);

        $this->validator
            ->expects($this->once())
            ->method('make')
            ->with(['name' => 'steph bug'], ['name' => 'required'])
            ->willReturn($validator);

        $event = $this->provideAsyncCommandToPreValidate(['name' => 'steph bug']);
        $message = new Message($event, [Header::EVENT_DISPATCHED => false, Header::EVENT_STRATEGY => ProducerStrategy::PER_MESSAGE->value]);

        $this->processValidation($message);
    }

    public function testAsyncCommandNotValidatedAgain(): void
    {
        $validator = $this->createMock(Validator::class);
        $validator->expects($this->never())->method('fails');

        $this->validator
            ->expects($this->never())
            ->method('make');

        $event = $this->provideAsyncCommandToPreValidate(['name' => 'steph bug']);
        $message = new Message($event, [Header::EVENT_DISPATCHED => true, Header::EVENT_STRATEGY => ProducerStrategy::PER_MESSAGE->value]);

        $this->processValidation($message);
    }

    public function testCommandNotValidatedAgain(): void
    {
        $validator = $this->createMock(Validator::class);
        $validator->expects($this->never())->method('fails');

        $this->validator
            ->expects($this->never())
            ->method('make');

        $event = $this->provideAsyncCommandToPreValidate(['name' => 'steph bug']);
        $message = new Message($event, [Header::EVENT_DISPATCHED => true, Header::EVENT_STRATEGY => ProducerStrategy::PER_MESSAGE->value]);

        $this->processValidation($message);
    }

    #[DataProvider('provideASyncLogicalHeaderForPreValidation')]
    public function testAsyncCommandToPreValidate_2(bool $dispatched, string $strategy): void
    {
        $validator = $this->createMock(Validator::class);
        $validator->expects($this->once())->method('fails')->willReturn(false);

        $this->validator
            ->expects($this->once())
            ->method('make')
            ->with(['name' => 'steph bug'], ['name' => 'required'])
            ->willReturn($validator);

        $event = $this->provideAsyncCommandToPreValidate(['name' => 'steph bug']);
        $message = new Message($event, [Header::EVENT_DISPATCHED => $dispatched, Header::EVENT_STRATEGY => $strategy]);

        $this->processValidation($message);
    }

    public function testExceptionRaisedOnValidation(): void
    {
        $messageBag = new MessageBag(['name' => 'missing']);

        $validator = $this->createMock(Validator::class);
        $validator->expects($this->once())->method('fails')->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('make')
            ->with(['name' => null], ['name' => 'required'])
            ->willReturn($validator);

        $validator
            ->expects($this->exactly(2))
            ->method('errors')
            ->willReturn($messageBag);

        $event = $this->provideCommandToValidate(['name' => null]);
        $message = new Message($event, [Header::EVENT_DISPATCHED => false, Header::EVENT_STRATEGY => ProducerStrategy::SYNC->value]);

        $validationFailed = null;

        try {
            $this->processValidation($message);
        } catch (ValidationMessageFailed $exception) {
            $validationFailed = $exception;
        }

        $this->assertInstanceOf(ValidationMessageFailed::class, $validationFailed);
        $this->assertSame($messageBag, $validationFailed->errors());
        $this->assertSame($validator, $validationFailed->getValidator());
        $this->assertSame($message, $validationFailed->failedMessage());
        $this->assertStringStartsWith('Validation rules fails:', $validationFailed->getMessage());
    }

    private function processValidation(Message $message): void
    {
        $tracker = new TrackMessage();
        $story = $tracker->newStory(Reporter::DISPATCH_EVENT);
        $story->withMessage($message);

        $subscriber = new ValidateOnlyOnceMessage($this->validator, $this->producerUnity);
        $subscriber->attachToReporter($tracker);

        $tracker->disclose($story);
    }

    private function provideCommandToValidate(array $content = []): DomainCommand|MessageValidation
    {
        return new class($content) extends DomainCommand implements MessageValidation
        {
            use HasConstructableContent;

            public function validationRules(): array
            {
                return ['name' => 'required'];
            }
        };
    }

    private function provideCommandToPreValidate(array $content = []): DomainCommand|MessagePreValidation
    {
        return new class($content) extends DomainCommand implements MessagePreValidation
        {
            use HasConstructableContent;

            public function validationRules(): array
            {
                return ['name' => 'required'];
            }
        };
    }

    private function provideAsyncCommandToPreValidate(array $content = []): DomainCommand|MessagePreValidation
    {
        return new class($content) extends DomainCommand implements AsyncMessage, MessagePreValidation
        {
            use HasConstructableContent;

            public function validationRules(): array
            {
                return ['name' => 'required'];
            }
        };
    }

    public static function provideNoValidatableEvent(): Generator
    {
        yield [new stdClass()];
        yield [new SomeCommand([])];
        yield [new SomeEvent([])];
        yield [new SomeQuery([])];
    }

    public static function provideSyncLogicalHeaderForValidation(): Generator
    {
        yield [false, ProducerStrategy::SYNC->value];
        yield [true, ProducerStrategy::SYNC->value];
        yield [false, ProducerStrategy::PER_MESSAGE->value];
        yield [true, ProducerStrategy::PER_MESSAGE->value];
        yield [true, ProducerStrategy::ASYNC->value];
    }

    public static function provideASyncLogicalHeaderForPreValidation(): Generator
    {
        yield [false, ProducerStrategy::PER_MESSAGE->value];
        yield [false, ProducerStrategy::ASYNC->value];
    }
}
