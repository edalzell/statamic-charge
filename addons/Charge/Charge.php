<?php

namespace Statamic\Addons\Charge;

use Stripe\Stripe;
use Stripe\Refund;
use Statamic\API\URL;
use Statamic\API\File;
use Statamic\API\YAML;
use Statamic\API\Crypt;
use Statamic\API\Folder;
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
     * @return string           the Stripe id for the Charge
     */
    public function processPayment($purchase)
    {
        /** @var \Stripe\Charge $charge */
        return StripeCharge::create(array(
            'source' => $purchase['stripeToken'],
            'amount'   =>$purchase['amount'],
            'currency' => 'usd',
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
     * @param $timestamp    UTC timestamp
     * @return \DateTime
     */
    public static function getLocalDateTimeFromUTC($timestamp)
    {
        /** @var \DateTime $dt */

        $dt = new \DateTime( '@' . $timestamp, new \DateTimeZone('Etc/UTC'));

        /*
         * Convert to the server timezone
         */
        $dt->setTimezone(new \DateTimeZone(ini_get('date.timezone')));

        return $dt;
    }

    /**
     * Return the refund action link
     *
     * @param string $id
     *
     * @return string
     */
    public static function getRefundLink($id)
    {
        $action = $customer['auto_renew'] ? 'withdraw' : 'reinstate';

        return '<a href="' . URL::assemble( 'charge', 'refund', $id) . '">Refund</a>';
    }
}
