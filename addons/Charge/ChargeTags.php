<?php

namespace Statamic\Addons\Charge;

use Statamic\API\Crypt;
use Statamic\Extend\Tags;

class ChargeTags extends Tags
{
    /**
     * The {{ charge }} tag
     *
     * @return string|array
     */
    public function index()
    {
        //
    }

    /**
     * The {{ charge:example }} tag
     *
     * @return string|array
     */
    public function form()
    {
        $data = [];
        $params = [];

        $html = $this->formOpen('process');

        if ($this->flash->exists('success'))
        {
            $data['success'] = true;
            $data['details'] = $this->flash->get('details');
        }

        if ($redirect = $this->get('redirect')) {
            $params['redirect'] = $redirect;
        }

        $html .= '<input type="hidden" name="_params" value="'. Crypt::encrypt($params) .'" />';

        $html .= $this->parse($data);

        $html .= '</form>';

        return $html;
    }
}
