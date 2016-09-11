<?php

namespace Statamic\Addons\Charge;

use Statamic\API\Crypt;
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
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->view('charge', ['charges' => $this->charge->getCharges()]);
    }

    public function postProcess()
    {
        try
        {
            $charge = $this->charge->processPayment(request()->except(['_token', '_params']));

            $this->flash->put('success', true);
            $this->flash->put('details', $charge);

            $redirect = array_get(Crypt::decrypt(request()->input('_params')), 'redirect');

            return ($redirect) ? redirect($redirect) : back();
        }
        catch (\Stripe\Error\Base $e)
        {
            return back()->withInput()->withErrors($e->getMessage());
        }
    }
}
