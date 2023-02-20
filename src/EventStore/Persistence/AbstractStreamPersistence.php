<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use stdClass;
use Chronhub\Storm\Stream\StreamName;
use Chronhub\Storm\Reporter\DomainEvent;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Message\EventHeader;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

abstract class AbstractStreamPersistence implements StreamPersistence
{
    public function __construct(protected readonly StreamEventSerializer $serializer)
    {
    }

    public function tableName(StreamName $streamName): string
    {
        return '_'.$streamName->name;
    }

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

    public function toDomainEvent(iterable|stdClass $payload): DomainEvent
    {
        if ($payload instanceof stdClass) {
            $payload = (array) $payload;
        }

        return $this->serializer->unserializeContent($payload)->current();
    }
}
