<?php

namespace MODXDocs\Views;

use MODXDocs\Model\PageRequest;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

use MODXDocs\Exceptions\RedirectNotFoundException;
use MODXDocs\Helpers\Redirector;

class Error extends Base
{
    private $throwable;

    public function __construct(ContainerInterface $container, \Throwable $e)
    {
        $this->throwable = $e;
        parent::__construct($container);
    }

    public function get(Request $request, Response $response)
    {

        $pageRequest = PageRequest::fromRequest($request);

        $data = [
            'revision' => static::getRevision(),
            'is_dev' => (bool) getenv('DEV'),
            'exception_type' => get_class($this->throwable),
            'exception' => $this->throwable,
            'current_uri' => $request->getUri()->getPath(),

            'page_title' => 'Oops, an error occurred.',

            'version' => $pageRequest->getVersion(),
            'version_branch' => $pageRequest->getVersionBranch(),
            'language' => $pageRequest->getLanguage(),

            // We always disregard the path here, because we know the request is always invalid
            'path' => null,
        ];

        return $this->view->render(
            $response->withStatus(503),
            'error.twig',
            $data
        );
    }
}
