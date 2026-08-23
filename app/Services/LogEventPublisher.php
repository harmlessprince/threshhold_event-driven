<?php

namespace App\Services;

use App\Contracts\EventPublisher;
use Illuminate\Support\Facades\Log;

class LogEventPublisher implements EventPublisher
{
    public function publish(string $eventName, array $payload): void
    {
        Log::info("EventPublisher: would publish [{$eventName}]", $payload);
    }
}
