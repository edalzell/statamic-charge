<?php

namespace Statamic\Addons\Charge\Traits;

use Statamic\API\Crypt;
use Illuminate\Http\Request;

trait HasParameters
{
    public static $param_key = '_charge_params';

    /**
     * Merge the encrypted params (amount, description) with the data & request
     */
    public function getDetails(Request $request, array $data = []): array
    {
        // gotta merge the email stuff so there's just one
        $data = array_merge(
            $data,
            $request->only([
                'stripeEmail',
                'stripeToken',
                'plan',
                'description',
                'amount',
                'amount_dollar',
                'email',
                'coupon',
                'quantity',
                'trial_period_days',
                'store_payment_method',
                'payment_intent',
                'payment_method',
            ]),
            $this->decryptParams($request->input(self::$param_key)),
            $this->decryptParams($request->input('_params'))
        );

        // if `stripeEmail` is there, use that, otherwise, use `email`
        $data['email'] = $data['stripeEmail'] ?: $data['email'];

        return $data;
    }

    public function decryptParams(string $encryptedString = null): array
    {
        return $encryptedString ? Crypt::decrypt($encryptedString) : [];
    }
}
