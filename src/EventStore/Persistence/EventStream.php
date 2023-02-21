<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Eloquent\Model;
use Chronhub\Storm\Contracts\Chronicler\EventStreamModel;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use function array_map;

final class EventStream extends Model implements EventStreamProvider, EventStreamModel
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'event_streams';

    /**
     * @var array<string>
     */
    protected $fillable = ['stream_name', 'real_stream_name', 'category'];

    public function createStream(string $streamName, ?string $tableName, ?string $category = null): bool
    {
        return $this->newInstance([
            'real_stream_name' => $streamName,
            'stream_name' => $tableName,
            'category' => $category,
        ])->save();
    }

    public function deleteStream(string $streamName): bool
    {
        return 1 === $this->newQuery()
            ->where('real_stream_name', $streamName)
            ->delete();
    }

    public function filterByStreams(array $streamNames): array
    {
        return $this->newQuery()
            ->whereIn(
                'real_stream_name',
                array_map(
                    static fn (string|StreamName $streamName): string => $streamName instanceof StreamName ? $streamName->name : $streamName,
                    $streamNames)
            )
            ->orderBy('real_stream_name')
            ->get()
            ->pluck('real_stream_name')
            ->toArray();
    }

    public function filterByCategories(array $categoryNames): array
    {
        return $this->newQuery()
            ->whereIn('category', $categoryNames)
            ->orderBy('real_stream_name')
            ->get()
            ->pluck('real_stream_name')
            ->toArray();
    }

    public function allWithoutInternal(): array
    {
        return $this->newQuery()
            ->whereRaw("real_stream_name NOT LIKE '$%'")
            ->orderBy('real_stream_name')
            ->pluck('real_stream_name')
            ->toArray();
    }

    public function hasRealStreamName(string $streamName): bool
    {
        return $this->newQuery()
            ->where('real_stream_name', $streamName)
            ->exists();
    }

    public function realStreamName(): string
    {
        return $this['real_stream_name'];
    }

    public function tableName(): string
    {
        return $this['stream_name'];
    }

    public function category(): ?string
    {
        return $this['category'];
    }
}
