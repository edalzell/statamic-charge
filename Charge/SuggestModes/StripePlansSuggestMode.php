<?php

namespace Statamic\Addons\Charge\SuggestModes;

use Stripe\Plan;
use Stripe\Stripe;
use Statamic\Addons\Suggest\Modes\AbstractMode;

class StripePlansSuggestMode extends AbstractMode
{
    public function suggestions()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        return collect(Plan::all(['expand' => ['data.product']])->data)
            ->map(function ($plan, $key) {
                return ['value' => $plan->id, 'text' => $plan->product->name];
            })
            ->values()
            ->all();
    }
}
