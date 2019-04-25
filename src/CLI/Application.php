<?php

namespace MODXDocs\CLI;


use MODXDocs\CLI\Commands\CacheNavigation;
use MODXDocs\CLI\Commands\CacheRefresh;
use MODXDocs\CLI\Commands\Index\Init;
use MODXDocs\CLI\Commands\Index\Search;
use MODXDocs\CLI\Commands\Index\Translations;
use MODXDocs\CLI\Commands\SourcesInit;
use MODXDocs\CLI\Commands\SourcesUpdate;
use MODXDocs\DocsApp;

class Application extends \Symfony\Component\Console\Application {

    protected $app;
    protected $container;

    public function __construct(DocsApp $docsApp)
    {
        parent::__construct('modxdocs', '1.0.0');
        $this->app = $docsApp;
        $this->container = $docsApp->getContainer();
    }

    /**
     * @return DocsApp
     */
    public function getDocsApp(): DocsApp
    {
        return $this->app;
    }

    /**
     * @return \Psr\Container\ContainerInterface
     */
    public function getContainer(): \Psr\Container\ContainerInterface
    {
        return $this->container;
    }

    protected function getDefaultCommands()
    {
        $cmds = parent::getDefaultCommands();
        $cmds[] = new SourcesInit();
        $cmds[] = new SourcesUpdate();
        $cmds[] = new CacheRefresh();
        $cmds[] = new CacheNavigation();
        $cmds[] = new Init();
        $cmds[] = new Translations();
        $cmds[] = new Search();
        return $cmds;
    }
}
