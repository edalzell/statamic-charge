<?php

namespace Statamic\Addons\Charge\Events;

use Statamic\API\File;
use Statamic\API\Path;
use Statamic\Events\Event;
use Statamic\Permissions\File\Role;
use Statamic\Filesystem\FileAccessor;
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
        $pathPrefix = File::disk('storage')->filesystem()->getAdapter()->getPathPrefix();

        return [Path::assemble($pathPrefix, 'addons', 'Charge', $this->email . '.yaml')];
    }
}
