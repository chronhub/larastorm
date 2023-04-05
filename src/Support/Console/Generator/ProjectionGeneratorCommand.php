<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console\Generator;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use function trim;
use function dirname;
use function is_array;

/**
 * todo config path and namespace (tied to laravel app namespace)
 * todo add directory per type ?
 * todo prompt missing arguments ?
 * todo tests
 */
abstract class ProjectionGeneratorCommand extends Command
{
    use ProvideGeneratorCommand;

    protected Filesystem $files;

    public function handle(Filesystem $files): int
    {
        $this->files = $files;

        $path = $this->getFilePath();

        if ($this->files->exists($path)) {
            $this->components->error("File : $path already exits");

            return self::FAILURE;
        }

        $this->makeDirectory(dirname($path));

        $stub = $this->getStub();

        $this->files->put($path, $stub);

        $this->components->info("File : $path created");

        return self::SUCCESS;
    }

    protected function getStubPath(): string
    {
        $projectionType = $this->getProjectionType();

        $projectionFrom = $this->argument('projection-from');

        $basePath = __DIR__.'/../Stubs/';

        return match ($projectionFrom) {
            'all' => $basePath.'project-'.$projectionType.'-from-all.stub',
            'category' => $basePath.'project-'.$projectionType.'-from-categories.stub',
            'stream' => $basePath.'project-'.$projectionType.'-from-streams.stub',
            default => throw new InvalidArgumentException("Invalid projection from $projectionFrom")
        };
    }

    protected function getStubVariables(): array
    {
        $fqn = $this->normalizeClassName($this->argument('name'));

        $defaultStubs = [
            'NAMESPACE' => $this->getNamespace($fqn),
            'CLASS_NAME' => class_basename($fqn),
            'COMMAND_SUFFIX' => Str::kebab($this->argument('name')),
        ];

        if ($this->argument('projection-from') === 'all') {
            return $defaultStubs;
        }

        $defaultStubs['STREAM_NAMES'] = $this->normalizeStreamNamesOptions();

        return $defaultStubs;
    }

    protected function normalizeStreamNamesOptions(): string
    {
        $streamNames = $this->option('stream');

        if (! is_array($streamNames) || empty($streamNames)) {
            return 'TODO';
        }

        $string = '';
        foreach ($streamNames as $streamName) {
            $string .= "'$streamName',";
        }

        return $string;
    }

    protected function getFilePath(): string
    {
        return $this->getPath(
            $this->normalizeClassName($this->argument('name'))
        );
    }

    protected function normalizeClassName(string $name): string
    {
        return $this->qualifyClass($this->formatClassName($name));
    }

    protected function formatClassName(string $name): string
    {
        return Str::studly(trim($name));
    }

    protected function makeDirectory(string $path): string
    {
        $this->files->ensureDirectoryExists($path, 0777);

        return $path;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Console\\Commands\\Projection';
    }

    /**
     * @return string{'query','projection','read-model'}
     */
    abstract protected function getProjectionType(): string;
}
