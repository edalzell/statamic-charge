<?php

namespace Statamic\Addons\Charge\Actions;

use Statamic\API\Arr;
use Statamic\Data\Users\User;

class UpdateUserRolesAction
{
    /** @var array */
    private $plansAndRoles;

    /** @var User */
    private $user;

    public function __construct(User $user, array $plansAndRoles)
    {
        $this->user = $user;
        $this->plansAndRoles = $plansAndRoles;
    }

    public function execute(string $newPlan = null, string $oldPlan = null)
    {
        $this->removeUserRoles($oldPlan);
        $this->addUserRoles($newPlan);
        $this->user->save();
    }

    private function removeUserRoles(string $plan = null)
    {
        if ($plan && $role = $this->getRole($plan)) {
            // remove role from user
            $roles = array_filter($this->user->get('roles', []), function ($item) use ($role) {
                return $item != $role;
            });

            $this->user->set('roles', $roles);
        }
    }

    private function addUserRoles(string $plan = null)
    {
        if ($plan && $role = $this->getRole($plan)) {
            // get the user's roles
            $roles = $this->user->get('roles', []);

            // add the role id to the roles
            $roles[] = $role;

            // set the user's roles
            $this->user->set('roles', array_unique($roles));
        }
    }

    public function getRole($plan)
    {
        $config = collect($this->plansAndRoles)
            ->first(function ($ignored, $data) use ($plan) {
                return $plan == Arr::get($data, 'plan');
            });

        return $config ? $config['role'][0] : null;
    }

    // private function getPlansConfig(string $plan): array
    // {
    //     $config = app(Addons::class)->get('charge') ?: [];
    //     $plansAndRoles = Arr::get($config, 'plans_and_roles', []);

    // }
}
