<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use Chronhub\Larastorm\Support\Facade\Report;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Contracts\Tracker\Listener;
use Chronhub\Storm\Tracker\GenericListener;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\TableCell;
use function array_merge;

#[AsCommand(name: 'messager:subscribers', description: 'Display reporter listeners per type')]
final class ListMessagerSubscribersCommand extends Command
{
    protected $signature = 'messager:subscribers 
                           { name : reporter name }
                           { type : reporter type }';

    protected $description = 'Display reporter listeners per type';

    protected array $tableHeaders = ['Listener', 'Subscriber class', 'On Event', 'Priority'];

    public function handle(): int
    {
        [$reporterService, $messageListeners] = $this->collectListeners();

        $rows = [[new TableCell($reporterService, ['colspan' => 4, 'rowspan' => 1, 'style' => null])]];

        foreach ($messageListeners as $listener) {
            $rows[] = [
                class_basename($listener),
                $listener->origin(),
                $listener->name(),
                $listener->priority(),
            ];
        }

        $this->table($this->tableHeaders, $rows);

        return self::SUCCESS;
    }

    /**
     * @return array<string|Collection<Listener>>
     */
    protected function collectListeners(): array
    {
        $reporter = Report::create($this->argument('type'), $this->argument('name'));

        $listeners = $reporter->tracker()->listeners();

        return [
            $reporter::class,
            array_merge(
                $this->sortPerEventTypeAndDescendantOrder(Reporter::DISPATCH_EVENT, $listeners),
                $this->sortPerEventTypeAndDescendantOrder(Reporter::FINALIZE_EVENT, $listeners),
            ),
        ];
    }

    protected function sortPerEventTypeAndDescendantOrder(string $eventType, Collection $listeners): array
    {
        return $listeners
            ->filter(fn (GenericListener $listener) => $listener->name() === $eventType)
            ->sortByDesc(fn ($listener) => $listener->priority(), SORT_NUMERIC)
            ->toArray();
    }
}
