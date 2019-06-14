<?php

namespace Statamic\Addons\Charge\Events;

use Statamic\Events\Event;


class CustomerSubscribed extends Event
{
    public $details;

    public function __construct($details)
    {
        $this->details = $details;
    }
}
