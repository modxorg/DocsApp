<?php

namespace MODXDocs\Helpers;

use DOMDocument;
use DOMElement;
use DOMNode;
use Masterminds\HTML5;

/**
 * Turns GitHub-style markdown alerts into existing .c-callout markup.
 *
 * Supported (optional spaces inside the marker):
 *   > [!NOTE]
 *   > [!TIP]
 *   > [!IMPORTANT]
 *   > [!WARNING]
 *   > [!CAUTION]
 *
 * @see https://github.com/modxorg/Docs/issues/40
 * @see https://github.com/modxorg/DocsApp/issues/353
 */
class AlertCalloutFixer
{
    private const TYPES = [
        'NOTE' => [
            'class' => 'c-callout--info',
            'title' => 'Note',
        ],
        'TIP' => [
            'class' => 'c-callout--success',
            'title' => 'Tip',
        ],
        'IMPORTANT' => [
            'class' => 'c-callout--warning',
            'title' => 'Important',
        ],
        'WARNING' => [
            'class' => 'c-callout--alert',
            'title' => 'Warning',
        ],
        'CAUTION' => [
            'class' => 'c-callout--alert',
            'title' => 'Caution',
        ],
    ];

    /** @var HTML5 */
    private $htmlParser;

    public function __construct(?HTML5 $htmlParser = null)
    {
        $this->htmlParser = $htmlParser ?? new HTML5();
    }

    public function fix(string $markup): string
    {
        if ($markup === '' || stripos($markup, '[!') === false) {
            return $markup;
        }

        $partialID = uniqid('alert_fixer_', false);
        $wrapped = sprintf("<body id='%s'>%s</body>", $partialID, $markup);
        $domDocument = $this->htmlParser->loadHTML($wrapped);
        $body = $domDocument->getElementById($partialID);
        if (!$body instanceof DOMElement) {
            return $markup;
        }

        $blockquotes = [];
        foreach ($body->getElementsByTagName('blockquote') as $node) {
            $blockquotes[] = $node;
        }

        foreach ($blockquotes as $blockquote) {
            if (!$blockquote instanceof DOMElement || !$blockquote->parentNode) {
                continue;
            }

            $type = $this->detectType($blockquote);
            if ($type === null) {
                continue;
            }

            $this->transformBlockquote($domDocument, $blockquote, $type);
        }

        return $this->htmlParser->saveHTML($body->childNodes);
    }

    private function detectType(DOMElement $blockquote): ?string
    {
        $text = ltrim($blockquote->textContent);
        if (!preg_match('/^\[\s*!\s*(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\s*\]/i', $text, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    private function transformBlockquote(DOMDocument $dom, DOMElement $blockquote, string $type): void
    {
        $config = self::TYPES[$type];

        $callout = $dom->createElement('div');
        $callout->setAttribute('class', 'c-callout ' . $config['class']);
        $callout->setAttribute('role', 'note');

        $title = $dom->createElement('strong');
        $title->setAttribute('class', 'c-callout__title');
        $title->textContent = $config['title'];
        $callout->appendChild($title);

        $this->stripMarkerFromChildren($blockquote, $type);

        while ($blockquote->firstChild) {
            $child = $blockquote->firstChild;
            $blockquote->removeChild($child);
            if ($this->isEmptyParagraph($child)) {
                continue;
            }
            $callout->appendChild($child);
        }

        $blockquote->parentNode->replaceChild($callout, $blockquote);
    }

    private function stripMarkerFromChildren(DOMElement $blockquote, string $type): void
    {
        $pattern = '/^\[\s*!\s*' . preg_quote($type, '/') . '\s*\]\s*/i';

        foreach ($blockquote->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if (strtolower($child->tagName) !== 'p') {
                continue;
            }

            // Prefer editing the first text node so nested HTML stays intact.
            foreach ($child->childNodes as $inline) {
                if ($inline->nodeType === XML_TEXT_NODE) {
                    $inline->nodeValue = preg_replace($pattern, '', (string) $inline->nodeValue, 1);
                    break;
                }
            }

            // Marker-only paragraph: leave empty for later skip.
            if (preg_match($pattern, ltrim($child->textContent))) {
                $child->nodeValue = '';
            }

            break;
        }
    }

    private function isEmptyParagraph(DOMNode $node): bool
    {
        if (!$node instanceof DOMElement || strtolower($node->tagName) !== 'p') {
            return false;
        }

        return trim($node->textContent) === '';
    }
}
