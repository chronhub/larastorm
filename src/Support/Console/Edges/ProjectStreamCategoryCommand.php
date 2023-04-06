<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console\Edges;

use Closure;
use Illuminate\Console\Command;
use Chronhub\Storm\Reporter\DomainEvent;
use Chronhub\Storm\Contracts\Message\Header;
use Symfony\Component\Console\Attribute\AsCommand;
use Chronhub\Storm\Contracts\Projector\EmitterProjector;
use Chronhub\Storm\Contracts\Projector\EmitterCasterInterface;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use function pcntl_async_signals;

#[AsCommand(name: 'projector:edge-category', description: 'optimize query by projecting all events per category')]
final class ProjectStreamCategoryCommand extends Command implements SignalableCommandInterface
{
    protected $signature = 'projector:edge-category 
                            { projector         : projector name } 
                            { --signal=1        : dispatch async signal } 
                            { --in-background=1 : run in background }';

    private EmitterProjector $projector;

    public function handle(ProjectorServiceManager $serviceManager): int
    {
        if ((int) $this->option('signal') === 1) {
            pcntl_async_signals(true);
        }

        $projector = $serviceManager->create($this->argument('projector'));

        $this->projector = $projector->emitter('$by_category');

        $this->projector
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition())
            ->fromAll()
            ->whenAny($this->eventHandler())
            ->run($this->shouldRunInBackground());

        return self::SUCCESS;
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal): void
    {
        $this->projector->stop();
    }

    private function eventHandler(): Closure
    {
        return function (DomainEvent $event): void {
            /** @var EmitterCasterInterface $this */
            $messageName = $event->header(Header::EVENT_TYPE);

            $this->linkTo('$ct-'.$messageName, $event);
        };
    }

    private function shouldRunInBackground(): bool
    {
        return (int) $this->option('in-background') === 1;
    }
}
