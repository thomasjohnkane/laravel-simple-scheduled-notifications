<?php

declare(strict_types=1);

namespace Thomasjohnkane\Snooze\Tests;

use Illuminate\Database\ConnectionInterface;
use Mockery;
use Thomasjohnkane\Snooze\Serializer;
use Thomasjohnkane\Snooze\Tests\Models\User;

class SerializerTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function testSerializesModelWithoutRelationsWhenConfigIsTrue()
    {
        config(['snooze.doNotLoadRelationsOnSerialize' => true]);
        $connection = Mockery::mock(ConnectionInterface::class);
        $serializer = new Serializer($connection);

        $user = User::with('children')->find(1);

        $serialized = $serializer->serialize($user);
        $unserialized = $serializer->unserialize($serialized);

        $this->assertEquals($user->id, $unserialized->id);
        $this->assertEquals($user->name, $unserialized->name);
        $this->assertFalse($unserialized->relationLoaded('children'));
    }

    public function testSerializesModelWithRelationsWhenConfigIsFalse()
    {
        config(['snooze.doNotLoadRelationsOnSerialize' => false]);
        $connection = Mockery::mock(ConnectionInterface::class);
        $serializer = new Serializer($connection);

        $user = User::with('children')->find(1);

        $serialized = $serializer->serialize($user);
        $unserialized = $serializer->unserialize($serialized);

        $this->assertEquals($user->id, $unserialized->id);
        $this->assertEquals($user->name, $unserialized->name);
        $this->assertTrue($unserialized->relationLoaded('children'));
    }
}
