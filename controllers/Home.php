<?php

namespace MODXDocs\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class Home extends Base
{
    public function get(Request $request, Response $response, array $args = array())
    {
        $init = $this->initialize($request, $response, $args);
        if ($init !== true) {
            return $init;
        }
        return $this->render('home.twig');
    }
}
