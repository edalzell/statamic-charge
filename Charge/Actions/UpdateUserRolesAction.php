<?php

namespace Statamic\Addons\Charge\Actions;

use Statamic\Data\Users\User;

class UpdateUserRolesAction
{
    private array $planConfig;
    private User $user;

    public function __construct(User $user, array $planConfig)
    {
        $this->user = $user;
        $this->planConfig = $planConfig;
    }

    public function execute(string $newPlan, $oldPlan)
    {
        $this->removeUserRoles($oldPlan);
        $this->addUserRoles($newPlan);
        $this->user->set('plan', $newPlan);
        $this->user->save();
    }

    public function removeUserRoles($plan)
    {
        // remove the role from the user
        // get the role associated w/ this plan
        if ($plan && $role = $this->getRole()) {
            // remove role from user
            $roles = array_filter($this->user->get('roles', []), function ($item) use ($role) {
                return $item != $role;
            });

            $this->user->set('roles', $roles);
        }
    }

    public function addUserRoles($plan)
    {
        if ($role = $this->getRole()) {
            // get the user's roles
            $roles = $this->user->get('roles', []);

            // add the role id to the roles
            $roles[] = $role;

            // set the user's roles
            $this->user->set('roles', array_unique($roles));
        }
    }

    public function getRole()
    {
        return $this->planConfig ? $this->planConfig['role'][0] : null;
    }
}