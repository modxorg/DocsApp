<?php

namespace MODXDocs\Views;

use MODXDocs\Model\PageRequest;
use MODXDocs\Services\CacheService;
use MODXDocs\Services\VersionsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Psr\Container\ContainerInterface;

abstract class Base
{
    private static $rev = '';
    /** @var ContainerInterface */
    protected $container;

    /** @var Twig */
    protected $view;
    /** @var VersionsService */
    protected $versionsService;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->view = $this->container->get('view');
        $this->versionsService = $this->container->get(VersionsService::class);
    }

    protected function render(Request $request, Response $response, $template, array $data = []): Response
    {
        $pageRequest = PageRequest::fromRequest($request);

        $initialData = [
            'revision' => static::getRevision(),
            'canonical_base' => $_ENV['CANONICAL_BASE_URL'],
            'current_uri' => $request->getUri()->getPath(),
            'version' => $pageRequest->getVersion(),
            'version_branch' => $pageRequest->getVersionBranch(),
            'versions' => $this->versionsService->getVersions($pageRequest),
            'language' => $pageRequest->getLanguage(),
            'locale' => $pageRequest->getLocale(),
            'path' => $pageRequest->getPath(),
            'logo_link' => $pageRequest->getContextUrl() . VersionsService::getDefaultPath(),
            'is_dev' => (bool) ($_ENV['DEV'] ?? false),
            'analytics_id' => (string) ($_ENV['ANALYTICS_ID'] ?? ''),
            'lang' => $this->getLang($pageRequest->getLanguage()),
            'opencollective' => $this->getOpenCollectiveInfo(),
            'opencollective_members' => $this->getOpenCollectiveMembers(),
        ];

        $data = \array_merge(
            $initialData,
            $data
        );

        if (!array_key_exists('canonical_url', $data) || empty($data['canonical_url'])) {
            $data['canonical_url'] = $data['canonical_base'] . ltrim($data['current_uri'], '/');
        }

        return $this->view->render(
            $response,
            $template,
            $data
        );
    }

    protected function render404(Request $request, Response $response, array $data = []): Response
    {
        return $this->render(
            $request,
            $response->withStatus(404),
            'notfound.twig',
            \array_merge(
                $data
            )
        );
    }

    public static function getRevision() : string
    {
        if (empty(static::$rev)) {
            static::$rev = $_ENV['REVISION'] ?? date('YmdHis');
        }
        return static::$rev;
    }

    protected function getLang($language)
    {
        $langFile = __DIR__ . '/../../lang.json';
        if (!file_exists($langFile)) {
            return [];
        }

        $langData = json_decode(file_get_contents($langFile), true);
        if (!$langData) {
            return [];
        }

        return $langData[$language] ?? $langData['en'] ?? [];
    }

    protected function getOpenCollectiveInfo()
    {
        $cache = CacheService::getInstance();
        $key = 'opencollective_info';
        $info = $cache->get($key);

        if ($info === null) {
            $info = json_decode(file_get_contents('https://opencollective.com/modx/members/all.json'), true);
            $cache->set($key, $info, 3600);
        }

        return $info;
    }

    protected function getOpenCollectiveMembers()
    {
        $cache = CacheService::getInstance();
        $key = 'opencollective_members';
        $members = $cache->get($key);

        if ($members === null) {
            $members = json_decode(file_get_contents('https://opencollective.com/modx/members.json'), true);
            $cache->set($key, $members, 3600);
        }

        return $members;
    }

    public static function setRevision($rev = null)
    {
        static::$rev = $_ENV['REVISION'] ?? date('YmdHis');
    }
}
