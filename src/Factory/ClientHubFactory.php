<?php

declare(strict_types=1);

namespace Szemul\SentryErrorHandler\Factory;

use Sentry\SentrySdk;
use Sentry\State\HubInterface;

class ClientHubFactory
{
    public function getSentryClientHub(): HubInterface
    {
        return SentrySdk::getCurrentHub();
    }
}
