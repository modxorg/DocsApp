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
        $preferedVersion = 'current';
        $redirects = [];

        // Start by collecting the available redirects per version
        $dir = new \DirectoryIterator($this->container->get('settings')['docs_dir']);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDir() || $fileinfo->isDot()) {
                continue;
            }

            // Filename for a directory is actually the directory name without path or quotes
            $key = $fileinfo->getFilename();

            // Check if the URI starts with the key; if it does, treat it as a relative redirect, setting the preferred
            // version accordingly, and removing the version from the uri we're looking for
            if (substr($uri, 1, \strlen($key)) === $key) {
                $preferedVersion = $key;
                $uri = substr($uri, 1 + \strlen($preferedVersion));
            }

            // Get the redirects for this version, and store it in an array
            $file = $fileinfo->getPathname() . '/redirects.json';
            if (file_exists($file) && is_readable($file)) {
                $versionRedirects = json_decode(file_get_contents($file), true);
                if (\is_array($versionRedirects)) {
                    $redirects[$key] = $versionRedirects;
                }
            }
        }

        $baseDir = $this->container->get('settings')['directory'];

        // First, check if the requested URI exists in the preferred version
        if (array_key_exists($preferedVersion, $redirects)) {
            if (array_key_exists($uri, $redirects[$preferedVersion])) {
                return $baseDir . $preferedVersion . '/' . $redirects[$preferedVersion][$uri];
            }
            unset($redirects[$preferedVersion]);
        }

        // If not in the prefered version, check the others
        foreach ($redirects as $version => $options) {
            if (array_key_exists($uri, $options)) {
                return $baseDir . $version . '/' . $options[$uri];
            }
        }

        // No clue what you're looking for!
        return false;
    }
}
