<?php

namespace MODXDocs\Helpers;

use Knp\Menu\ItemInterface;
use Knp\Menu\Matcher\MatcherInterface;
use Knp\Menu\Renderer\ListRenderer;

class TocRenderer extends ListRenderer
{

    private $prefix;

    public function __construct(MatcherInterface $matcher, $prefix, array $defaultOptions = [])
    {
        $this->prefix = $prefix;

        parent::__construct($matcher, $defaultOptions, null);
    }

    protected function renderLink(ItemInterface $item, array $options = array())
    {
        $newUri = $this->prefix . $item->getUri();
        $item->setUri($newUri);

        return parent::renderLink($item, $options);
    }
}