<?php

namespace Statamic\Addons\Charge;

use Stripe\Stripe;
use Stripe\Refund;
use Carbon\Carbon;
use Statamic\API\Config;
use Statamic\API\Crypt;
use Statamic\Extend\Addon;
use Stripe\Charge as StripeCharge;

class Charge extends Addon
{
    public function init()
	{
		Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
	}

    /**
     * Charge a card
     * @param array $purchase   array of charging details
     *
     * @return array           the Charge data
     */
    public function processPayment($purchase)
    {
        /** @var \Stripe\Charge $charge */
        return StripeCharge::create(array(
            'source' => $purchase['stripeToken'],
            'amount'   =>$purchase['amount'],
            'currency' => array_get($purchase, 'currency', $this->getConfig('currency', 'usd')),
            'receipt_email' => $purchase['stripeEmail'],
            'description' => $purchase['description']
        ))->__toArray(true);
    }

    public function refund($id)
    {
        $re = Refund::create(array("charge" => $id));
    }

    public function getCharges()
    {
        $charges = StripeCharge::all(array(['limit'=>100]))->__toArray(true);

        // only want the ones that have NOT been refunded
        return collect($charges['data'])->filter(function ($charge) {
            return !$charge['refunded'];
        });
    }

    public function decryptParams()
    {
        return Crypt::decrypt(request()->input('_charge_params'));
    }

    /**
     * This converts from a UTC timestamp to a DateTime in the local PHP timezone
     *
     * @param $timestamp    UTC timestamp
     * @return \DateTime
     */
    public static function getLocalDateTimeFromUTC($timestamp)
    {
        /** @var \DateTime $dt */

        $dt = new Carbon( '@' . $timestamp, 'Etc/UTC');

        /*
         * Convert to the server timezone
         */
        $dt->tz(Config::get('system.timezone'));

        return $dt;
    }
}
