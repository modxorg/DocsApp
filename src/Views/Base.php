<?php

namespace MODXDocs\Views;

use MODXDocs\Model\PageRequest;
use MODXDocs\Services\CacheService;
use MODXDocs\Services\VersionsService;
use Slim\Http\Request;
use Slim\Http\Response;
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

    protected function render(Request $request, Response $response, $template, array $data = []): \Psr\Http\Message\ResponseInterface
    {
        $pageRequest = PageRequest::fromRequest($request);

        $initialData = [
            'revision' => static::getRevision(),
            'canonical_base' => getenv('CANONICAL_BASE_URL'),
            'current_uri' => $request->getUri()->getPath(),
            'version' => $pageRequest->getVersion(),
            'version_branch' => $pageRequest->getVersionBranch(),
            'versions' => $this->versionsService->getVersions($pageRequest),
            'language' => $pageRequest->getLanguage(),
            'locale' => $pageRequest->getLocale(),
            'path' => $pageRequest->getPath(),
            'logo_link' => $pageRequest->getContextUrl() . VersionsService::getDefaultPath(),
            'is_dev' => (bool) getenv('DEV'),
            'analytics_id' => (string) getenv('ANALYTICS_ID'),
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

    protected function render404(Request $request, Response $response, array $data = []): \Psr\Http\Message\ResponseInterface
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
        if (!empty(self::$rev)) {
            return self::$rev;
        }
        $revision = 'dev';

        $projectDir = getenv('BASE_DIRECTORY');
        if (file_exists($projectDir . '.revision')) {
            $revision = trim((string)file_get_contents($projectDir . '.revision'));
        }

        self::$rev = $revision;

        return $revision;
    }

    protected function getLang(string $language): array
    {
        $lang = json_decode(file_get_contents($_ENV['BASE_DIRECTORY'] . 'lang.json'), true);
        if (!is_array($lang)) {
            return [];
        }
        if (array_key_exists($language, $lang)) {
            $lang = array_merge($lang['en'], $lang[$language]);
        }
        else {
            $lang = $lang['en'];
        }
        return $lang;
    }

    private function getOpenCollectiveInfo(): array
    {
        $cache = CacheService::getInstance();
        $cacheKey = 'opencollective_fc';
        $info = $cache->get($cacheKey);
        if (!is_array($info)) {
            $data = @file_get_contents('https://opencollective.com/modx.json');
            $data = json_decode($data, true);
            if (!empty($data['slug']) && $data['slug'] === 'modx') {
                $data['fetched'] = time();
                $cache->set($cacheKey, $data, strtotime('+2 hours'));
                $info = $data;
            }
        }

        return $info ?: [];
    }

    private function getOpenCollectiveMembers(): array
    {
        $cache = CacheService::getInstance();
        $cacheKey = 'opencollective_members_fc';
        $info = $cache->get($cacheKey);
        if (!is_array($info)) {
            $data = @file_get_contents('https://opencollective.com/modx/members.json?limit=50&isActive=1');
            $data = json_decode($data, true);
            if (is_array($data) && count($data) > 0) {

                $merged = [];
                foreach ($data as $i => $member) {
                    // filter out non-backers (OC itself, admin)
                    if ($member['role'] !== 'BACKER') {
                        continue;
                    }

                    // Sometimes, users appear multiple times because of having a subscription but also
                    // standalone donations. Merging those profiles here makes sure they appear just once.
                    if (!isset($merged[$member['profile']])) {
                        $merged[$member['profile']] = $member;
                    }
                }

                // Sort by total amount donated
                uasort($merged, static function ($a, $b) {
                    return $a['totalAmountDonated'] < $b['totalAmountDonated'] ? 1 : -1;
                });

                $cache->set($cacheKey, $merged, strtotime('+2 hours'));
                $info = $merged;
            }
        }

        return $info ?: [];
    }
}
