<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Chronhub\Storm\Contracts\Message\EventHeader;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Reporter\DomainEvent;
use Chronhub\Storm\Stream\StreamName;

abstract class AbstractStreamPersistence implements StreamPersistence
{
    public function __construct(protected readonly StreamEventSerializer $serializer)
    {
    }

    public function tableName(StreamName $streamName): string
    {
        return '_'.$streamName->name;
    }

    /**
     * @return array{
     *      "event_id": non-empty-string,
     *      "event_type": non-empty-string,
     *      "aggregate_id": non-empty-string,
     *      "aggregate_type": non-empty-string,
     *      "aggregate_version": positive-int,
     *      "headers": non-empty-string,
     *      "content": non-empty-string,
     *      "created_at": non-empty-string,
     *      "no"?: positive-int
     * }
     */
    public function serialize(DomainEvent $event): array
    {
        $data = $this->serializer->serializeEvent($event);

        $normalized = [
            'event_id' => $data['headers'][Header::EVENT_ID],
            'event_type' => $data['headers'][Header::EVENT_TYPE],
            'aggregate_id' => $data['headers'][EventHeader::AGGREGATE_ID],
            'aggregate_type' => $data['headers'][EventHeader::AGGREGATE_TYPE],
            'aggregate_version' => $data['headers'][EventHeader::AGGREGATE_VERSION],
            'headers' => $this->serializer->encodePayload($data['headers']),
            'content' => $this->serializer->encodePayload($data['content']),
            'created_at' => $data['headers'][Header::EVENT_TIME],
        ];

        if (! $this->isAutoIncremented()) {
            $normalized['no'] = $data['headers'][EventHeader::AGGREGATE_VERSION];
        }

        return $normalized;
    }
}
