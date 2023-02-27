<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console\Generator;

class MakeQueryProjectionCommand extends ProjectionGeneratorCommand
{
    protected $signature = 'make:projection-query
                            { name              : The name of the class }
                            { projection-from   : Available from are: all, category, stream }
                            { --stream=*        : The names of the streams/categories, *required* for "projection-from" stream/category, default TODO }';

    protected $description = 'Make a new persistent projection';

    protected function getProjectionType(): string
    {
        return 'query';
    }
}
