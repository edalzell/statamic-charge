<?php

namespace Statamic\Addons\Charge;

use Statamic\API\URL;
use Statamic\API\Config;

class Utils
{
    /**
     * Return the proper action link, based on if the subscription is set to auto-renew
     *
     * @param array $subscription
     *
     * @return string
     */
    public static function getActionLink($subscription)
    {
        $action = $subscription['auto_renew'] ? 'cancel' : 'resubscribe';

        return '<a href="' . URL::assemble($action, $subscription['id']) . '">' . ucfirst($action) . '</a>';
    }

    /**
     * This converts from a UTC timestamp to a DateTime in the local PHP timezone
     *
     * @param $timestamp int timestamp
     * @return \DateTime
     */
    public static function getLocalDateTimeFromUTC($timestamp)
    {
        /*
         * Convert to the server timezone
         */
        return carbon('@' . $timestamp, 'Etc/UTC')->tz(Config::get('system.timezone'));
    }
}
