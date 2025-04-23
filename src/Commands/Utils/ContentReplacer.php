<?php 

namespace Rcalicdan\Ci4Larabridge\Commands\Utils;

/**
 * Content replacer utility class for file modifications
 */
class ContentReplacer
{
    /**
     * Replace content using search and replace arrays
     * 
     * @param string $content Original content
     * @param array $replaces [search => replace] pairs
     * @return string Modified content
     */
    public function replace(string $content, array $replaces): string
    {
        return strtr($content, $replaces);
    }

    /**
     * Add content if it doesn't already exist
     * 
     * @param string $content Original content
     * @param string $text Text to add
     * @param string $pattern Regexp search pattern
     * @param string $replace Regexp replacement including text to add
     * @return bool|string true: already updated, false: regexp error, string: modified content
     */
    public function add(string $content, string $text, string $pattern, string $replace)
    {
        $return = preg_match('/' . preg_quote($text, '/') . '/u', $content);
        if ($return === 1) {
            // It has already been updated.
            return true;
        }
        if ($return === false) {
            // Regexp error.
            return false;
        }
        return preg_replace($pattern, $replace, $content);
    }
}