<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Double;

use Chronhub\Storm\Reporter\DomainCommand;
use Chronhub\Storm\Message\HasConstructableContent;

final class SomeCommand extends DomainCommand
{
    use HasConstructableContent;
}
