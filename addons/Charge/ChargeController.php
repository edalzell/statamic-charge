<?php

namespace Statamic\Addons\Charge;

use Statamic\Extend\Controller;

class ChargeController extends Controller
{
    /** @var  \Statamic\Addons\Charge\Charge */
    private $charge;

    public function init()
    {
        $this->charge = new Charge;
    }

    /**
     * Maps to your route definition in routes.yaml
     *
     * @return Illuminate\Http\Response
     */
    public function index()
    {
        return $this->view('charge', ['charges' => $this->charge->getCharges()]);
    }

    public function postProcess()
    {
        try
        {
            $this->charge->process(request()->except('_token'));
        }
        catch (\Stripe\Error\Base $e)
        {
            // what to do here????

        }
    }
}
