<?php

namespace Statamic\Addons\Charge;

use Stripe\Stripe;
use Statamic\API\File;
use Statamic\API\YAML;
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
        $charge = StripeCharge::create(array(
            'source' => $purchase['stripeToken'],
            'amount'   =>$purchase['amount'],
            'currency' => 'usd',
            'receipt_email' => $purchase['stripeEmail'],
            'description' => $purchase['description']
        ))->__toArray(true);

        $this->storage->putYAML(time(), $charge);

        return $charge;
    }

    public function getCharges()
    {
        $files = Folder::disk('storage')->getFilesByTypeRecursively('addons/' . $this->getAddonClassName(), 'yaml');

        return collect($files)->map(function ($path) {
            return YAML::parse(File::disk('storage')->get($path));
        });
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
}
