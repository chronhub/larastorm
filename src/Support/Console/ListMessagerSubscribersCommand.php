<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Chronhub\Larastorm\Support\Facade\Report;
use Chronhub\Storm\Contracts\Tracker\Listener;
use Symfony\Component\Console\Helper\TableCell;
use Laravel\SerializableClosure\Support\ReflectionClosure;

final class ListMessagerSubscribersCommand extends Command
{
    protected $signature = 'messager:list {name : reporter name} {type : reporter type}';

    protected $description = 'Display reporter listeners per type';

    protected array $tableHeaders = ['Listener', 'Subscriber class', 'On Event', 'Priority'];

    public function handle(): int
    {
        [$reporterService, $messageListeners] = $this->collectListenersOrderedByPriority();

        $rows = [];

        $rows[] = [new TableCell($reporterService, ['colspan' => 4, 'rowspan' => 1, 'style' => null])];

        foreach ($messageListeners as $listener) {
            $rows[] = [
                class_basename($listener),
                $this->parseCallback($listener->callback()) ?? 'no scope',
                $listener->name(),
                $listener->priority(),
            ];
        }

        $this->table($this->tableHeaders, $rows);

        return self::SUCCESS;
    }

    protected function parseCallback(callable $callback): ?string
    {
        $closure = new ReflectionClosure($callback);

        return $closure->getClosureScopeClass()?->getName();
    }

    /**
     * @return array<string|Collection<Listener>>
     */
    protected function collectListenersOrderedByPriority(): array
    {
        $reporter = Report::create($this->argument('type'), $this->argument('name'));

        $listeners = $reporter->tracker()->listeners();

        if (! $listeners instanceof Enumerable) {
            $listeners = new Collection($listeners);
        }

        return [
            $reporter::class,
            $listeners->sortByDesc(fn (Listener $listener): int => $listener->priority()),
        ];
    }
}
