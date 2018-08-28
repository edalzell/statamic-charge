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
            'timezone' => Config::get('system.timezone'),
            'version' => $this->getMeta()['version'],
            'addon_name' => $this->getAddonName(),
            'cp_path' => CP_ROUTE
        ];

        view()->share('charge', $addon);

        // gotta add myself to the CSRF exclude list so Stripe can send the webhooks
        $excludes = Config::get('system.csrf_exclude', []);

        $excludes[] = $this->actionUrl('webhook');
        Config::set('system.csrf_exclude', array_unique($excludes));
    }
}
