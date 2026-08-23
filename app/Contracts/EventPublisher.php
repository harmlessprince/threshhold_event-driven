<?php

namespace App\Contracts;

/**
 * The seam an external message broker (SQS/Kafka topic, outbound webhook) would
 * implement so another service could subscribe to this app's domain events without
 * reaching into its database. Bound to LogEventPublisher for now — swapping the
 * binding is the whole migration to a real broker.
 */
interface EventPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $eventName, array $payload): void;
}
