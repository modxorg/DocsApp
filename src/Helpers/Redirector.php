<?php

namespace MODXDocs\Helpers;

use MODXDocs\Exceptions\RedirectNotFoundException;
use MODXDocs\Services\VersionsService;

class Redirector
{
    public static function findNewURI($uri)
    {
        $uri = static::cleanRequestUri($uri);

        $preferredVersion = VersionsService::getCurrentVersionBranch();
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
                $preferredVersion = $key;
                $uri = substr($uri, 1 + \strlen($preferredVersion));
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

        $uriWithSlash = rtrim($uri, '/') . '/';
        $uriWithoutSlash = rtrim($uri, '/');

        // First, check if the requested URI exists in the preferred version
        if (array_key_exists($preferredVersion, $redirects)) {
            if (array_key_exists($uri, $redirects[$preferredVersion])) {
                return $baseDir . VersionsService::getCurrentVersion() . '/' . $redirects[$preferredVersion][$uri];
            }
            if (array_key_exists($uriWithSlash, $redirects[$preferredVersion])) {
                return $baseDir . VersionsService::getCurrentVersion() . '/' . $redirects[$preferredVersion][$uriWithSlash];
            }
            if (array_key_exists($uriWithoutSlash, $redirects[$preferredVersion])) {
                return $baseDir . VersionsService::getCurrentVersion() . '/' . $redirects[$preferredVersion][$uriWithoutSlash];
            }
            unset($redirects[$preferredVersion]);
        }

        // If not in the prefered version, check the others
        foreach ($redirects as $version => $options) {
            if (array_key_exists($uri, $options)) {
                return $baseDir . $version . '/' . $options[$uri];
            }
            if (array_key_exists($uriWithSlash, $options)) {
                return $baseDir . $version . '/' . $options[$uriWithSlash];
            }
            if (array_key_exists($uriWithoutSlash, $options)) {
                return $baseDir . $version . '/' . $options[$uriWithoutSlash];
            }
        }

        // No clue what you're looking for!
        throw new RedirectNotFoundException();
    }

    private static function cleanRequestUri($uri): string
    {
        $uri = urldecode(strtolower($uri));
        $uri = '/' . ltrim($uri, '/');
        $currentBranchString = '/' . VersionsService::getCurrentVersion() . '/';

        if (strpos($uri, $currentBranchString) !== 0) {
            return $uri;
        }

        return '/'
            . VersionsService::getCurrentVersion()
            . '/'
            . substr($uri, strlen($currentBranchString));
    }
}
