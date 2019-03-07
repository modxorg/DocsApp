<?php
namespace MODXDocs\Views;

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

        if ($newUri = $this->findNewUri($uri)) {
            return $this->response->withRedirect($newUri, 301);
        }

        $this->response = $this->response->withStatus(404);

        $uri = urlencode($uri);
        $this->setVariable('req_url', $uri);
        $this->setVariable('page_title', 'Oops, page not found.');
        return $this->render('notfound.twig');
    }

//    public function getTopNavigation()
//    {
//        $topNav = $this->getNavigationForParent($this->basePath, 1, 1);
//        $this->setVariable('topnav', $topNav);
//    }

    private function findNewUri($uri)
    {
        $currentRedirects = $this->container->get('settings')['docs_dir'] . '/current/redirects.json';
        if (file_exists($currentRedirects) && is_readable($currentRedirects)) {
            $redirects = json_decode(file_get_contents($currentRedirects), true);

            if (\is_array($redirects) && array_key_exists($uri, $redirects)) {
                return $this->container->get('settings')['directory'] . 'current/' . $redirects[$uri];
            }
        }

        $dir = new \DirectoryIterator($this->container->get('settings')['docs_dir']);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDir() || $fileinfo->isDot()) {
                continue;
            }

            $file = $fileinfo->getPathname() . '/redirects.json';
            if (file_exists($file) && is_readable($file)) {
                $redirects = json_decode(file_get_contents($file), true);

                if (\is_array($redirects) && array_key_exists($uri, $redirects)) {
                    return $this->container->get('settings')['directory'] . $dir->getFilename() . '/' . $redirects[$uri];
                }
            }
        }
        return false;
    }
}
