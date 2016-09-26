<?php

namespace Statamic\Addons\Charge;

use Statamic\API\Config;
use Statamic\Extend\ServiceProvider;

class ChargeServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    public $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Global addon variables
        $addon = [
            'timezone'    => Config::get('system.timzone'),
            'version'     => $this->getMeta()['version'],
            'addon_name'  => $this->getAddonName(),
            'cp_path'     => CP_ROUTE
        ];

        view()->share('charge', $addon);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
