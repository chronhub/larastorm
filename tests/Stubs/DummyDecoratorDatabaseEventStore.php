<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs;

use Chronhub\Larastorm\Support\Contracts\ChroniclerDB;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;

abstract class DummyDecoratorDatabaseEventStore implements ChroniclerDB, ChroniclerDecorator
{
}
