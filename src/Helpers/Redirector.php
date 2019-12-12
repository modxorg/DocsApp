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
        $preferredVersionUrl = VersionsService::getCurrentVersion();
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
                $preferredVersionUrl = $preferredVersion === VersionsService::getCurrentVersionBranch()
                    ? VersionsService::getCurrentVersion()
                    : $key;
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

        // The uri may be stored in different formats in the redirects file, so we account for some different options
        $decodedUri = urldecode($uri);
        $possibilities = [
            $uri,
            rtrim($uri, '/') . '/',
            rtrim($uri, '/'),
            $decodedUri,
            rtrim($decodedUri, '/') . '/',
            rtrim($decodedUri, '/'),
        ];
        $possibilities = array_unique($possibilities);

        // First, check if the requested URI exists in the preferred version
        if (array_key_exists($preferredVersion, $redirects)) {
            foreach ($possibilities as $possibility) {
                if (array_key_exists($possibility, $redirects[$preferredVersion])) {
                    return $baseDir . $preferredVersionUrl . '/' . $redirects[$preferredVersion][$uri];
                }
            }
            unset($redirects[$preferredVersion]);
        }

        // If not in the preferred version, check the others
        foreach ($redirects as $version => $options) {
            foreach ($possibilities as $possibility) {
                if (array_key_exists($possibility, $options)) {
                    return $baseDir . $version . '/' . $options[$possibility];
                }
            }
        }

        // No clue what you're looking for!
        throw new RedirectNotFoundException();
    }

    private static function cleanRequestUri($uri): string
    {
        $uri = strtolower($uri);
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
