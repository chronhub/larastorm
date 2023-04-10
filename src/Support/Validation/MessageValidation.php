<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Validation;

use Chronhub\Storm\Contracts\Reporter\Reporting;

interface MessageValidation extends Reporting
{
    public function validationRules(): array;
}
