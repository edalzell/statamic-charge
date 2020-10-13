<?php

namespace Statamic\Addons\Charge\Traits;

use Stripe\Price;

trait HasPrices
{
    /**
     * The {{ charge:prices }} tag.
     *
     * @return string
     */
    public function prices()
    {
        $type = $this->getParam('type');
        $limit = $this->getParamInt('limit', 10);
        $active = $this->getParam('active');
        $product = $this->getParam('product');

        $params = [
            'limit' => $limit,
        ];

        if (! is_null($active)) {
            $params['active'] = (bool) $active;
        }

        if ($type) {
            $params['type'] = $type;
        }

        if ($product) {
            $params['product'] = $product;
        }

        $prices = Price::all($params)->toArray();

        return $this->parseLoop($prices['data']);
    }
}
