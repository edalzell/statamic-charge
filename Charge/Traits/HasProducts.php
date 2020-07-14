<?php

namespace Statamic\Addons\Charge\Traits;

use Stripe\Product;

trait HasProducts
{
    /**
     * The {{ charge:product }} tag.
     *
     * @return string
     */
    public function product()
    {
        $product = Product::retrieve($this->getParam('product'))->toArray();

        return $this->parse($product);
    }

    /**
     * The {{ charge:products }} tag.
     *
     * @return string
     */
    public function products()
    {
        $limit = $this->getParamInt('limit', 10);
        $active = $this->getParam('active');

        $params = [
            'limit' => $limit,
        ];

        if (! is_null($active)) {
            $params['active'] = (bool) $active;
        }

        $products = Product::all($params)->toArray();

        return $this->parseLoop($products['data']);
    }
}
