<?php

namespace Rcalicdan\Ci4Larabridge\Providers;

use Rcalicdan\Blade\Blade;

/**
 * Provides custom component tag syntax support (<h-component>) for Blade templates
 * without relying on Laravel's container or dependency injection.
 */
class ComponentTagCompilerProvider
{
    /**
     * Component view namespace (used for h- prefixed components)
     */
    protected string $componentNamespace = 'components';

    /**
     * Register this compiler with the Blade instance
     */
    public function register(Blade $blade): void
    {
        // Get the BladeCompiler instance
        $compiler = $blade->compiler();

        // Register the component processing extension
        $compiler->extend(function ($view, $compiler) {
            // Process the component tags
            return $this->processComponentTags($view);
        });
    }

    /**
     * Process all component tags in the view content
     */
    public function processComponentTags(string $content): string
    {
        preg_match_all('/<h-[^>]*>/', $content, $allTags);
        file_put_contents(
            WRITEPATH . 'logs/all_h_tags.log',
            "All h- tags: " . json_encode($allTags[0]) . "\n",
            FILE_APPEND
        );
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
     */
    protected function compileSlots(string $content): string
    {
        $pattern = '/<h-slot\s+name=(["\'])(.*?)\1\s*(?:[^>]*)>(.*?)<\/h-slot>/s';

        return preg_replace_callback($pattern, function ($matches) {
            $name = $matches[2];
            $content = $matches[3];

            return "@slot('{$name}'){$content}@endslot";
        }, $content);
    }

    /**
     * Compile self-closing component tags
     */
    protected function compileSelfClosingTags(string $content): string
    {
        // This pattern needs to be more flexible to handle different self-closing formats
        $pattern = '/<h-([a-z0-9\-:.]+)\s*([^>]*?)(\s*\/)?>|<h-([a-z0-9\-:.]+)\s*([^>]*)><\/h-\4>/i';

        return preg_replace_callback($pattern, function ($matches) {
            // If matched the first pattern (self-closing tag with possible />)
            if (!empty($matches[1])) {
                $component = $matches[1];
                $attributes = $this->parseAttributes($matches[2]);
            }
            // If matched the second pattern (empty tag with separate closing tag)
            else {
                $component = $matches[4];
                $attributes = $this->parseAttributes($matches[5]);
            }

            $componentPath = $this->resolveComponentPath($component);

            // Log for debugging
            file_put_contents(
                WRITEPATH . 'logs/component_tags.log',
                "Component: $component\nAttributes: $attributes\nPath: $componentPath\n\n",
                FILE_APPEND
            );

            return "@component('{$componentPath}', {$attributes}, true)";
        }, $content);
    }

    /**
     * Compile regular component tags with content
     */
    protected function compileStandardComponentTags(string $content): string
    {
        $pattern = '/<h-([a-z0-9\-:.]+)\s*([^>]*)>(.*?)<\/h-\1>/is';

        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $this->parseAttributes($matches[2] ?? '');
            $content = $matches[3];
            $componentPath = $this->resolveComponentPath($component);

            return "@component('{$componentPath}', {$attributes}){$content}@endcomponent";
        }, $content);
    }

    /**
     * Resolve component name to a view path
     */
    protected function resolveComponentPath(string $component): string
    {
        // Handle namespaced components (directly specified)
        if (strpos($component, '::') !== false) {
            return $component;
        }

        // Handle h- prefixed components (strip the prefix)
        if (str_starts_with($component, 'h-')) {
            return $this->componentNamespace . '::' . substr($component, 2);
        }

        // Handle dot notation paths
        if (strpos($component, '.') !== false) {
            return $component;
        }

        // Default to components namespace
        return $this->componentNamespace . '::' . $component;
    }

    /**
     * Parse component tag attributes into PHP array format
     */
    protected function parseAttributes(string $attributeString): string
    {
        $attributes = [];

        // First, escape any special characters in the attribute string that might interfere with regex
        $safeAttributeString = $attributeString;

        // Process Blade expressions first: name="{{ expression }}"
        if (preg_match_all('/\s([a-zA-Z0-9_-]+)=(["\'])\{\{\s*(.*?)\s*\}\}\2/i', $safeAttributeString, $bladeEchoMatches, PREG_SET_ORDER)) {
            foreach ($bladeEchoMatches as $match) {
                $attrName = $match[1];
                $expression = trim($match[3]); // The content inside {{ }}
                $attributes[$attrName] = $expression;
                // Remove processed attribute to prevent double processing
                $safeAttributeString = str_replace($match[0], '', $safeAttributeString);
            }
        }

        // Match bound attributes with :name="expression"
        if (preg_match_all('/\s:([a-zA-Z0-9_-]+)=(["\'])(.*?)\2/i', $safeAttributeString, $boundMatches, PREG_SET_ORDER)) {
            foreach ($boundMatches as $match) {
                $attrName = $match[1];
                $expression = $match[3];
                $attributes[$attrName] = $expression; // Already PHP code
                $safeAttributeString = str_replace($match[0], '', $safeAttributeString);
            }
        }

        // Match regular attributes with name="value"
        if (preg_match_all('/\s([a-zA-Z0-9_-]+)=(["\'])(.*?)\2/i', $safeAttributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!isset($attributes[$match[1]])) {
                    $attributes[$match[1]] = "'" . addslashes($match[3]) . "'";
                }
                $safeAttributeString = str_replace($match[0], '', $safeAttributeString);
            }
        }

        // Match boolean attributes
        $cleanedAttributeString = trim(preg_replace('/\s+/', ' ', $safeAttributeString));
        if (preg_match_all('/\b([a-zA-Z0-9_-]+)\b/i', $cleanedAttributeString, $boolMatches)) {
            foreach ($boolMatches[1] as $match) {
                if (!isset($attributes[$match])) {
                    $attributes[$match] = 'true';
                }
            }
        }

        // Convert to PHP array code with proper escaping of keys
        $result = '[';
        foreach ($attributes as $key => $value) {
            $result .= "'" . addslashes($key) . "' => " . $value . ", ";
        }
        if (!empty($attributes)) {
            $result = rtrim($result, ', ');
        }
        $result .= ']';

        // Debug log to track attribute parsing
        file_put_contents(
            WRITEPATH . 'logs/component_attributes.log',
            "Original: " . $attributeString . "\n" .
                "Parsed: " . $result . "\n",
            FILE_APPEND
        );

        return $result;
    }
}
