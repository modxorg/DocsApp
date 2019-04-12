<?php
namespace MODXDocs\Views;

use MODXDocs\Helpers\Redirector;
use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;


class NotFound extends Doc
{
    public function initialize(Request $request, Response $response, array $args = array())
    {
        try {
            parent::initialize($request, $response, $args);
        }
        catch (NotFoundException $e) {
            // Continue showing the not found page even if the initialize of the doc page fails
            // The reason we call the parent initialize is so we get the same handling of version, language,
            // and top nav on the 404 page
        }
        return true;
    }

    public function get(Request $request, Response $response, array $args = array())
    {
        $init = $this->initialize($request, $response, $args);
        if ($init !== true) {
            return $init;
        }
        $uri = $request->getUri()->getPath();

        // Make sure links ending in .md get redirected
        if (substr($uri, -3) === '.md') {
            $uri = substr($uri,0,-3);
            return $this->response->withRedirect($uri, 301);
        }

        if ($newUri = Redirector::findNewURI($uri)) {
            return $this->response->withRedirect($newUri, 301);
        }

        $this->response = $this->response->withStatus(404);

        $this->setVariable('page_title', 'Oops, page not found.');
        return $this->render('notfound.twig');
    }

}
