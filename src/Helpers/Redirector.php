<?php

namespace MODXDocs\Helpers;

class Redirector
{
    public static function findNewURI($uri)
    {
        $uri = '/' . ltrim($uri, '/');

        $preferedVersion = 'current';
        $redirects = [];

        // Start by collecting the available redirects per version
        $dir = new \DirectoryIterator(getenv('DOCS_DIRECTORY'));
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

        $baseDir = '/';

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
