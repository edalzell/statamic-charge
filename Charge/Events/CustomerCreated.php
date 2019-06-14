<?php

namespace Statamic\Addons\Charge\Events;

use Statamic\Events\Event;
use Statamic\Permissions\File\Role;
use Statamic\Contracts\Data\DataEvent;
use Statamic\Extend\Contextual\ContextualStorage;


class CustomerCreated extends Event implements DataEvent
{
    /**
     * @var ContextualStorage
     */
    public $storage;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $email;

    /**
     * @param Role $role
     */
    public function __construct(ContextualStorage $storage, $id, $email)
    {
        $this->storage = $storage;
        $this->id = $id;
        $this->email = $email;
    }

    /**
     * Get contextual data related to event.
     *
     * @return array
     */
    public function contextualData()
    {
        return [
            'email' => $this->email,
            'id' => $this->id
        ];
    }

    /**
     * Get paths affected by event.
     *
     * @return array
     */
    public function affectedPaths()
    {
        //        return [$this->storage->];
    }
}
