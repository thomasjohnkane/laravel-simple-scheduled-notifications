<?php

declare(strict_types=1);

namespace Thomasjohnkane\Snooze;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\PostgresConnection;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Support\Str;

class Serializer
{
    use SerializesAndRestoresModelIdentifiers;

    /** @var ConnectionInterface */
    protected $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function serialize(object $notifiable): string
    {
        $result = serialize($this->getSerializedPropertyValue(clone $notifiable, withRelations: ! (config('snooze.doNotLoadRelationsOnSerialize', false) === true)));
        if ($this->connection instanceof PostgresConnection && Str::contains($result, "\0")) {
            $result = base64_encode($result);
        }

        return $result;
    }

    public function unserialize(string $serialized)
    {
        if ($this->connection instanceof PostgresConnection && ! Str::contains($serialized, [':', ';'])) {
            $serialized = base64_decode($serialized);
        }

        $object = unserialize($serialized);

        return $this->getRestoredPropertyValue(
            $object
        );
    }
}
