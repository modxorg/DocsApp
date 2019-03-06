<?php
namespace MODXDocs\Helpers;

use Knp\Menu\ItemInterface;
use Knp\Menu\Matcher\MatcherInterface;

/**
 * Renders MenuItem tree as unordered list
 */
class TocRenderer extends \Knp\Menu\Renderer\ListRenderer {
    protected $prefix;

    public function __construct(MatcherInterface $matcher, array $defaultOptions = array(), $prefix, $charset = null)
    {
        $this->prefix = $prefix;
        parent::__construct($matcher, $defaultOptions, $charset);
    }

    protected function renderLink(ItemInterface $item, array $options = array())
    {
        $newUri = $this->prefix . $item->getUri();
        $item->setUri($newUri);
        return parent::renderLink($item, $options);
    }
}