<?php

namespace Kommercio\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Kommercio\Events\OrderUpdate' => [
            'Kommercio\Listeners\OrderUpdateListener',
        ],
        'Kommercio\Events\OrderEvent' => [
            'Kommercio\Listeners\OrderListener'
        ]
    ];

    public function __construct($app)
    {
        parent::__construct($app);

        //Add package Cron Listener
        if(file_exists(base_path('packages/project/src/Project/Listeners/CronListener.php'))){
            $this->listen['Kommercio\Events\Cron'] = ['Project\Project\Listeners\CronListener'];
        }

        //Add package Store Listener
        if(file_exists(base_path('packages/project/src/Project/Listeners/StoreListener.php'))){
            $this->listen['Kommercio\Events\StoreEvent'] = ['Project\Project\Listeners\StoreListener'];
        }

        //Add package Order Event Listener
        if(file_exists(base_path('packages/project/src/Project/Listeners/OrderListener.php'))){
            $this->listen['Kommercio\Events\OrderEvent'][] = 'Project\Project\Listeners\OrderListener';
        }

        //Add package Catalog Query Builder Listener
        if(file_exists(base_path('packages/project/src/Project/Listeners/CatalogQueryBuilderListener.php'))){
            $this->listen['Kommercio\Events\CatalogQueryBuilder'] = ['Project\Project\Listeners\CatalogQueryBuilderListener'];
        }
    }

    /**
     * Register any other events for your application.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function boot(DispatcherContract $events)
    {
        parent::boot($events);

        //
    }
}
