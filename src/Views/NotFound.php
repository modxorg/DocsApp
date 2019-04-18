<?php

namespace MODXDocs\Views;

use MODXDocs\Model\PageRequest;
use Slim\Http\Request;
use Slim\Http\Response;

use MODXDocs\Exceptions\RedirectNotFoundException;
use MODXDocs\Helpers\Redirector;

class NotFound extends Base
{
    private const MARKDOWN_SUFFIX = '.md';

    public function get(Request $request, Response $response)
    {
        $currentUri = $request->getUri()->getPath();

        // Make sure links ending in .md get redirected
        if (substr($currentUri, -strlen(static::MARKDOWN_SUFFIX)) === static::MARKDOWN_SUFFIX) {
            $uri = substr($currentUri, 0, -strlen(static::MARKDOWN_SUFFIX));
            return $response->withRedirect($uri, 301);
        }

        try {
            $redirectUri = Redirector::findNewURI($currentUri);

            return $response->withRedirect($redirectUri, 301);
        } catch (RedirectNotFoundException $e) {
            $pageRequest = PageRequest::fromRequest($request);
            return $this->render404($request, $response, [
                'req_url' => urlencode($currentUri),
                'page_title' => 'Oops, page not found.',

                'version' => $pageRequest->getVersion(),
                'version_branch' => $pageRequest->getVersionBranch(),
                'language' => $pageRequest->getLanguage(),

                // We always disregard the path here, because we know the request is always invalid
                'path' => null,
            ]);
        }
    }
}
