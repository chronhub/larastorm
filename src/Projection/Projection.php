<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Chronhub\Storm\Contracts\Projector\ProjectionModel;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;

final class Projection extends Model implements ProjectionModel, ProjectionProvider
{
    public $table = 'projections';

    public $timestamps = false;

    protected $primaryKey = 'no';

    protected $guarded = ['name', 'status', 'position', 'state', 'locked_until'];

    public function createProjection(string $name, string $status): bool
    {
        $projection = $this->newInstance();

        $projection['name'] = $name;
        $projection['status'] = $status;
        $projection['position'] = '{}';
        $projection['state'] = '{}';
        $projection['locked_until'] = null;

        return $projection->save();
    }

    public function updateProjection(string $name, array $data): bool
    {
        return $this->newQuery()->where('name', $name)->update($data) === 1;
    }

    public function projectionExists(string $name): bool
    {
        return $this->newQuery()->where('name', $name)->exists();
    }

    public function retrieve(string $name): ?ProjectionModel
    {
        /** @var ProjectionModel|null $projection */
        $projection = $this->newQuery()->where('name', $name)->first();

        return $projection;
    }

    public function filterByNames(string ...$names): array
    {
        return $this->newQuery()
            ->whereIn('name', $names)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    public function deleteProjection(string $name): bool
    {
        return $this->newQuery()->where('name', $name)->delete() === 1;
    }

    public function acquireLock(string $name, string $status, string $lockedUntil, string $datetime): bool
    {
        $query = $this->newQuery()
            ->where('name', $name)
            ->where(static function (Builder $query) use ($datetime): void {
                $query->whereRaw('locked_until IS NULL OR locked_until < ?', [$datetime]);
            })->update([
                'status' => $status,
                'locked_until' => $lockedUntil,
            ]);

        return $query === 1;
    }

    public function name(): string
    {
        return $this['name'];
    }

    public function position(): string
    {
        return $this['position'];
    }

    public function state(): string
    {
        return $this['state'];
    }

    public function status(): string
    {
        return $this['status'];
    }

    public function lockedUntil(): ?string
    {
        return $this['locked_until'];
    }
}
