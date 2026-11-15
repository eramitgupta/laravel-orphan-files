<?php

namespace Erag\OrphanStorage\References\Extractors;

use Erag\OrphanStorage\Contracts\ReferenceExtractor;
use Erag\OrphanStorage\References\Source;

/**
 * Rich text (HTML or Markdown). Reads src, href, poster, data-src,
 * srcset and CSS url() values, Markdown links and bare http(s) URLs.
 */
class HtmlExtractor implements ReferenceExtractor
{
    protected const ATTRIBUTES = '/(?<![\w-])(?:src|href|poster|data-src|data-href|data-url|data-original|xlink:href)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i';

    protected const SRCSET = '/(?<![\w-])(?:srcset|data-srcset)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';

    protected const CSS_URL = '/url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\')]+))\s*\)/i';

    protected const MARKDOWN = '/!?\[[^\]]*\]\(\s*(?:<([^>]+)>|((?:[^()\s]|\([^()\s]*\))+))(?:\s+"[^"]*")?\s*\)/';

    protected const BARE_URL = '#https?://[^\s"\'<>\[\]]+#i';

    public function extract(mixed $value, Source $source): iterable
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $references = [];

        // Attribute values are matched on the raw HTML (an encoded &quot; cannot end them) and decoded afterwards.
        preg_match_all(self::ATTRIBUTES, $value, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $references[] = $this->decode($this->firstGroup($match));
        }

        preg_match_all(self::SRCSET, $value, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            foreach (explode(',', $this->decode($this->firstGroup($match))) as $candidate) {
                $references[] = (string) strtok(trim($candidate), " \t\n");
            }
        }

        // Inline styles, Markdown and plain text are matched after decoding, so url(&quot;a.jpg&quot;) reads as url("a.jpg").
        $text = $this->decode($value);

        preg_match_all(self::CSS_URL, $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $references[] = $this->firstGroup($match);
        }

        preg_match_all(self::MARKDOWN, $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $references[] = $this->firstGroup($match);
        }

        preg_match_all(self::BARE_URL, $text, $matches);

        foreach ($matches[0] as $url) {
            $references[] = $this->trimUrl($url);
        }

        $references = array_map(fn (string $reference): string => trim($reference, " \t\n\r\0\x0B\"'"), $references);

        return array_values(array_unique(array_filter(
            $references,
            fn (string $reference): bool => $reference !== '' && ! str_starts_with($reference, '#'),
        )));
    }

    /**
     * The first non-empty capture group of a match.
     *
     * @param  array<int, string>  $match
     */
    protected function firstGroup(array $match): string
    {
        foreach (array_slice($match, 1) as $group) {
            if ($group !== '') {
                return $group;
            }
        }

        return '';
    }

    /**
     * Drop sentence punctuation and unbalanced closing parentheses after a bare URL.
     */
    protected function trimUrl(string $url): string
    {
        $url = rtrim($url, '.,;:!?');

        while (str_ends_with($url, ')') && substr_count($url, ')') > substr_count($url, '(')) {
            $url = rtrim(substr($url, 0, -1), '.,;:!?');
        }

        return $url;
    }

    protected function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
