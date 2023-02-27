<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console\Generator;

use function array_merge;

class MakeReadModelProjectionCommand extends ProjectionGeneratorCommand
{
    protected $signature = 'make:projection-read-model
                            { name              : The name of the class }
                            { projection-name   : The name of the projection }
                            { projection-from   : Available from are: all, category, stream }
                            { read-model=TODO   : The read model service }
                            { --stream=*        : The names of the streams/categories, *required* for "projection-from" stream/category, default projection name }';

    protected $description = 'Make a new read model projection';

    protected function getStubVariables(): array
    {
        $defaultStubs = parent::getStubVariables();

        return array_merge($defaultStubs, [
            'PROJECTION_NAME' => $this->argument('projection-name'),
            'READ_MODEL_SERVICE' => $this->option('read-model') ?? $this->argument('projection-name'),
        ]);
    }

    protected function getProjectionType(): string
    {
        return 'read-model';
    }
}
