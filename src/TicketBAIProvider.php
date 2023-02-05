<?php
namespace EBethus\LaravelTicketBAI;

use Illuminate\Support\ServiceProvider;

class TicketBAIProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ticketbai', function ($app) {
            return new TicketBAI(config('services.ticketbai'));
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [TicketBAI::class];
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/database');
    }
}
