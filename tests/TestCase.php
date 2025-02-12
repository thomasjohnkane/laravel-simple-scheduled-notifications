<?php

declare(strict_types=1);

namespace Thomasjohnkane\Snooze\Tests;

use File;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Thomasjohnkane\Snooze\Facades\Snooze;
use Thomasjohnkane\Snooze\ServiceProvider;
use Thomasjohnkane\Snooze\Tests\Models\Child;
use Thomasjohnkane\Snooze\Tests\Models\User;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        $this->initializeDirectory($this->getTempDirectory());
        $this->setUpDatabase($this->app);
    }

    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'snooze' => Snooze::class,
        ];
    }

    public function getTempDirectory($suffix = '')
    {
        return __DIR__.DIRECTORY_SEPARATOR.'temp'.($suffix == '' ? '' : DIRECTORY_SEPARATOR.$suffix);
    }

    protected function initializeDirectory($directory)
    {
        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }
        File::makeDirectory($directory);
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('mail.driver', 'log');

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $this->getTempDirectory().'/database.sqlite',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', '6rE9Nz59bGRbeMATftriyQjrpF7DcOQm');
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function setUpDatabase($app)
    {
        file_put_contents($this->getTempDirectory().'/database.sqlite', null);

        $this->artisan('migrate')->run();

        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password', 60);
            $table->rememberToken();
            $table->timestamps();
        });

        $app['db']->connection()->getSchemaBuilder()->create('children', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();
        });

        foreach (range(1, 5) as $index) {
            $user = User::create([
                'name' => "user{$index}",
                'email' => "user{$index}@example.com",
                'password' => "password{$index}",
            ]);

            // Create two children for each user
            foreach (range(1, 2) as $childIndex) {
                Child::create([
                    'name' => "child{$childIndex}_user{$index}",
                    'user_id' => $user->id,
                ]);
            }
        }
    }
}
