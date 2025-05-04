<?php

namespace Rcalicdan\Ci4Larabridge\Providers;

use Rcalicdan\Blade\Blade;

/**
 * Provides component tag syntax support (<x-component>) for Blade templates
 * without relying on Laravel's container or dependency injection.
 */
class ComponentTagCompilerProvider
{
    /**
     * Component view namespace (used for x- prefixed components)
     */
    protected string $componentNamespace = 'components';

    /**
     * Register this compiler with the Blade instance
     *
     * @param Blade $blade
     * @return void
     */
    public function register(Blade $blade): void
    {
        // Get the BladeCompiler instance
        $compiler = $blade->compiler();

        // Register a custom directive to process component tags
        $compiler->directive('renderComponentTags', [$this, 'compileDirective']);

        // Register the component processing extension
        $compiler->extend(function ($view, $compiler) {
            // Process the component tags first
            $processed = $this->processComponentTags($view);

            // Then insert the @renderComponentTags directive at the beginning
            return '@renderComponentTags' . PHP_EOL . $processed;
        });
    }

    /**
     * Empty directive handler - the actual processing is done in the extend callback
     *
     * @return string
     */
    public function compileDirective(): string
    {
        return '<?php /* Component tags processed */ ?>';
    }

    /**
     * Process all component tags in the view content
     *
     * @param string $content
     * @return string
     */
    public function processComponentTags(string $content): string
    {
        // Process slots first
        $content = $this->compileSlots($content);

        // Process self-closing tags
        $content = $this->compileSelfClosingTags($content);

        // Process standard component tags
        $content = $this->compileStandardComponentTags($content);

        return $content;
    }

    /**
     * Compile slot tags within components
     *
     * @param string $content
     * @return string
     */
    protected function compileSlots(string $content): string
    {
        $pattern = '/<x-slot\s+name=(["\'])(.*?)\1\s*(?:[^>]*)>(.*?)<\/x-slot>/s';

        return preg_replace_callback($pattern, function ($matches) {
            $name = $matches[2];
            $content = $matches[3];

            return "@slot('{$name}'){$content}@endslot";
        }, $content);
    }

    /**
     * Compile self-closing component tags
     *
     * @param string $content
     * @return string
     */
    protected function compileSelfClosingTags(string $content): string
    {
        $pattern = '/<x-([a-z0-9\-:.]+)\s*([^>]*)\/>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $this->parseAttributes($matches[2] ?? '');

            return "@component('{$component}', {$attributes}, true)";
        }, $content);
    }

    /**
     * Compile regular component tags with content
     *
     * @param string $content
     * @return string
     */
    protected function compileStandardComponentTags(string $content): string
    {
        $pattern = '/<x-([a-z0-9\-:.]+)\s*([^>]*)>(.*?)<\/x-\1>/is';

        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $this->parseAttributes($matches[2] ?? '');
            $content = $matches[3];

            return "@component('{$component}', {$attributes}){$content}@endcomponent";
        }, $content);
    }

    /**
     * Parse component tag attributes into PHP array format
     *
     * @param string $attributeString
     * @return string
     */
    protected function parseAttributes(string $attributeString): string
    {
        $attributes = [];

        // Match bound attributes with :name="expression"
        if (preg_match_all('/\s:([a-zA-Z0-9_-]+)=(["\'])(.*?)\2/i', $attributeString, $boundMatches, PREG_SET_ORDER)) {
            foreach ($boundMatches as $match) {
                $attributes[$match[1]] = $match[3]; // Raw PHP expression

                // Remove processed bound attributes
                $attributeString = str_replace($match[0], '', $attributeString);
            }
        }

        // Match regular attributes with name="value"
        if (preg_match_all('/\s([a-zA-Z0-9_-]+)=(["\'])(.*?)\2/i', $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = "'" . addslashes($match[3]) . "'";
            }
        }

        // Match boolean attributes (without values)
        if (preg_match_all('/\s([a-zA-Z0-9_-]+)(?=\s|$)/i', $attributeString, $boolMatches, PREG_SET_ORDER)) {
            foreach ($boolMatches as $match) {
                if (!isset($attributes[$match[1]])) {
                    $attributes[$match[1]] = 'true';
                }
            }
        }

        // Convert to PHP array code
        $result = '[';
        foreach ($attributes as $key => $value) {
            $result .= "'$key' => $value, ";
        }
        $result .= ']';

        return $result;
    }
}
