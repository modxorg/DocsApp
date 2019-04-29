<?php

namespace MODXDocs\Views\Meta;

use MODXDocs\Navigation\Tree;
use MODXDocs\Model\PageRequest;
use MODXDocs\Services\SearchService;
use MODXDocs\Services\VersionsService;
use MODXDocs\Views\Base;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Router;

class Notes extends Base
{
    /** @var Router */
    private $router;
    /** @var VersionsService */
    private $versionsService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->router = $this->container->get('router');
        $this->versionsService = $this->container->get(VersionsService::class);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function get(Request $request, Response $response)
    {
        $pageRequest = PageRequest::fromRequest($request);

        $crumbs = [];
        $crumbs[] = [
            'title' => 'Meta', // @todo i18n
            'href' => $this->router->pathFor('meta', ['version' => $pageRequest->getVersion()])
        ];
        $crumbs[] = [
            'title' => 'Notes',
            'href' => $this->router->pathFor('meta/notes', ['version' => $pageRequest->getVersion()])
        ];

        $tree = Tree::get($pageRequest->getVersion(), $pageRequest->getLanguage());

        $notes = [];
        $items = $tree->getAllItems();
        foreach ($items as $item) {
            if (array_key_exists('note', $item)) {
                $notes[] = $item;
            }
            elseif (array_key_exists('suggest_delete', $item)) {
                $notes[] = $item;
             }
        }

        $title = 'Notes';
        return $this->render($request, $response, 'meta/notes.twig', [
            'page_title' => $title,
            'crumbs' => $crumbs,
            'items' => $notes,
            'canonical_url' => '',
            'versions' => $this->versionsService->getVersions($pageRequest),
            'nav' => $tree->renderTree($this->view),
        ]);
    }
}
