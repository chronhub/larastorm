<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Generator;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\QueryException;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;

abstract class EventStoreConnection implements ChroniclerConnection, ChroniclerDecorator
{
    public function __construct(protected readonly ChroniclerConnection|TransactionalChronicler $chronicler)
    {
    }

    public function firstCommit(Stream $stream): void
    {
        try {
            $this->chronicler->firstCommit($stream);
        } catch (QueryException $exception) {
            $this->handleException($exception, $stream->name());
        }
    }

    public function amend(Stream $stream): void
    {
        try {
            $this->chronicler->amend($stream);
        } catch (QueryException $exception) {
            $this->handleException($exception, $stream->name());
        }
    }

    public function delete(StreamName $streamName): void
    {
        $this->chronicler->delete($streamName);
    }

    public function retrieveAll(StreamName $streamName, AggregateIdentity $aggregateId, string $direction = 'asc'): Generator
    {
        return $this->chronicler->retrieveAll($streamName, $aggregateId, $direction);
    }

    public function retrieveFiltered(StreamName $streamName, QueryFilter $queryFilter): Generator
    {
        return $this->chronicler->retrieveFiltered($streamName, $queryFilter);
    }

    public function filterStreamNames(StreamName ...$streamNames): array
    {
        return $this->chronicler->filterStreamNames(...$streamNames);
    }

    public function filterCategoryNames(string ...$categoryNames): array
    {
        return $this->chronicler->filterCategoryNames(...$categoryNames);
    }

    public function hasStream(StreamName $streamName): bool
    {
        return $this->chronicler->hasStream($streamName);
    }

    public function getEventStreamProvider(): EventStreamProvider
    {
        return $this->chronicler->getEventStreamProvider();
    }

    public function innerChronicler(): Chronicler
    {
        return $this->chronicler;
    }

    public function isDuringCreation(): bool
    {
        return $this->chronicler->isDuringCreation();
    }

    /**
     * Handle query exception depends on connection driver
     *
     * @param  QueryException  $exception
     * @param  StreamName  $streamName
     * @return void
     *
     * @throws StreamNotFound when stream not found on update
     * @throws StreamAlreadyExists when stream already exist on creation
     * @throws ConnectionConcurrencyException when stream already exist on update
     * @throws ConnectionQueryFailure when any other query exception is raised
     */
    abstract protected function handleException(QueryException $exception, StreamName $streamName): void;
}
