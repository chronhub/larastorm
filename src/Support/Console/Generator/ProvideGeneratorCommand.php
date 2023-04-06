<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console\Generator;

use Illuminate\Support\Str;
use function array_slice;
use function explode;
use function implode;
use function ltrim;
use function str_replace;
use function trim;

trait ProvideGeneratorCommand
{
    protected function getStub(): string
    {
        return $this->getStubContents($this->getStubPath(), $this->getStubVariables());
    }

    protected function getStubContents(string $stub, array $stubVariables = []): string
    {
        $contents = $this->files->get($stub);

        foreach ($stubVariables as $search => $replace) {
            $contents = str_replace('$$'.$search.'$$', $replace, $contents);
        }

        return $contents;
    }

    protected function getPath(string $name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return $this->laravel['path'].'/'.str_replace('\\', '/', $name).'.php';
    }

    protected function qualifyClass(string $name): string
    {
        $name = ltrim($name, '\\/');

        $name = str_replace('/', '\\', $name);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        return $this->qualifyClass(
            $this->getDefaultNamespace(trim($rootNamespace, '\\')).'\\'.$name
        );
    }

    protected function rootNamespace(): string
    {
        return $this->laravel->getNamespace();
    }

    protected function getNamespace($name): string
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }
}
