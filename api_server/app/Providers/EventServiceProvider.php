<?php

namespace App\Providers;

use Event;
use Illuminate\Database\Events\StatementPrepared;
use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;
use PDO;

class EventServiceProvider extends ServiceProvider
{

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [];

    /**
     * Register any other events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(StatementPrepared::class, function ($event) {
            $event->statement->setFetchMode(PDO::FETCH_ASSOC);
        });
    }
}
