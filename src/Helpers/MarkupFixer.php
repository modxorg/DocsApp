<?php

namespace MODXDocs\Helpers;

use DOMElement;
use Masterminds\HTML5;
use RuntimeException;
use Cocur\Slugify\SlugifyInterface;
use TOC\UniqueSlugify;

/**
 * TOC Markup Fixer adds `id` attributes to all H1...H6 tags where they do not
 * already exist
 *
 * @author Casey McLaughlin <caseyamcl@gmail.com>
 */
class MarkupFixer extends \TOC\MarkupFixer
{
    /**
     * @var HTML5
     */
    private $htmlParser;

    /**
     * @var SlugifyInterface
     */
    private $sluggifier;

    /**
     * Constructor
     *
     * @param HTML5|null $htmlParser
     * @param SlugifyInterface|null $slugify
     */
    public function __construct(?HTML5 $htmlParser = null, ?SlugifyInterface $slugify = null)
    {
        $this->htmlParser = $htmlParser ?? new HTML5();
        $this->sluggifier = $slugify ?? new UniqueSlugify();
        parent::__construct($this->htmlParser, $this->sluggifier);
    }

    /**
     * Fix markup
     *
     * @param string $markup
     * @param int    $topLevel
     * @param int    $depth
     * @return string Markup with added IDs
     * @throws RuntimeException
     */
    public function fix(string $markup, int $topLevel = 1, int $depth = 6): string
    {
        if (! $this->isFullHtmlDocument($markup)) {
            $partialID = uniqid('toc_generator_');
            $markup = sprintf("<body id='%s'>%s</body>", $partialID, $markup);
        }

        $domDocument = $this->htmlParser->loadHTML($markup);
        $domDocument->preserveWhiteSpace = true; // do not clobber whitespace

        // If using the default slugifier, ensure that a unique instance of the class
        $slugger = $this->sluggifier instanceof UniqueSlugify ? new UniqueSlugify() : $this->sluggifier;

        /** @var DOMElement $node */
        foreach ($this->traverseHeaderTags($domDocument, $topLevel, $depth) as $node) {
            if ($node->getAttribute('id')) {
                continue;
            }

            $node->setAttribute('id', $slugger->slugify($node->getAttribute('title') ?: $node->textContent));
            $node->setAttribute('title', $node->textContent);

            // Add the heading link
            $link = $domDocument->createElement('a');
            $link->setAttribute('href', '#' . $node->getAttribute('id'));
            $link->setAttribute('class', 'heading-link');
            $link->setAttribute('data-nosnippet', 'true');
            $link->textContent = '¶';
            $node->appendChild($link);
        }

        return $this->htmlParser->saveHTML(
            (isset($partialID)) ? $domDocument->getElementById($partialID)->childNodes : $domDocument
        );
    }
}
