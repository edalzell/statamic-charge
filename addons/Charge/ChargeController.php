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
            // the amount, description and redirect have been encrypted from the tag
            $params = $this->charge->decryptParams();
            $data = request()->except(['_token', '_params']);

            // process the payment
            $charge = $this->charge->processPayment(array_merge($params, $data));

            $this->flash->put('success', true);
            $this->flash->put('details', $charge);

            $redirect = array_get($params, 'redirect');

            return ($redirect) ? redirect($redirect) : back();
        }
        catch (\Stripe\Error\Base $e)
        {
            return back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function refund($id = null)
    {
        $this->charge->refund($id);

        // redirect back to main page
        return response()->redirectToRoute('charge');
    }

}
