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
        $pattern = '/<h-([a-z0-9\-:.]+)\s*([^>]*)(?:\/?>)/i';

        // Debug logging - log the content too
        file_put_contents(
            WRITEPATH . 'logs/component_debug.log',
            "Content sample: " . substr($content, 0, 500) . "\n" .
                "Pattern: " . $pattern . "\n",
            FILE_APPEND
        );

        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $this->parseAttributes($matches[2] ?? '');
            $componentPath = $this->resolveComponentPath($component);

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

        // ---> ADD THIS SECTION <---
        // Match attributes with Blade echo syntax: name="{{ expression }}"
        // Important: Do this *before* matching regular attributes
        if (preg_match_all('/\s([a-zA-Z0-9_-]+)=["\']\{\{\s*(.*?)\s*\}\}["\']/i', $attributeString, $bladeEchoMatches, PREG_SET_ORDER)) {
            foreach ($bladeEchoMatches as $match) {
                $attrName = $match[1];
                $expression = trim($match[2]); // The content inside {{ }}
                // Ensure it's treated as a PHP expression, not a string
                $attributes[$attrName] = $expression;
                // Remove processed attribute to prevent double processing
                $attributeString = str_replace($match[0], '', $attributeString);
            }
        }
        // ---> END ADDED SECTION <---


        // Match bound attributes with :name="expression"
        if (preg_match_all('/\s:([a-zA-Z0-9_-]+)=(["\'])(.*?)\2/i', $attributeString, $boundMatches, PREG_SET_ORDER)) {
            // ... (rest of the existing bound attribute logic) ...
            foreach ($boundMatches as $match) {
                $attrName = $match[1];
                $expression = $match[3];
                $attributes[$attrName] = $expression; // Already PHP code
                $attributeString = str_replace($match[0], '', $attributeString);
            }
        }


        // Match regular attributes with name="value" (ensure it doesn't re-match processed ones)
        if (preg_match_all('/\s([a-zA-Z0-9_-]+)=(["\'])(.*?)\2/i', $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Only add if not already processed as bound or echo
                if (!isset($attributes[$match[1]])) {
                    $attributes[$match[1]] = "'" . addslashes($match[3]) . "'";
                }
                // Still remove from string to prepare for boolean check
                $attributeString = str_replace($match[0], '', $attributeString);
            }
        }

        // Match boolean attributes (clean up remaining string first)
        $cleanedAttributeString = trim(preg_replace('/\s+/', ' ', $attributeString)); // Normalize spaces
        if (preg_match_all('/([a-zA-Z0-9_-]+)/i', $cleanedAttributeString, $boolMatches)) {
            foreach ($boolMatches[1] as $match) {
                if (! isset($attributes[$match])) { // Check if already set by previous regex
                    $attributes[$match] = 'true';
                }
            }
        }


        // Convert to PHP array code
        $result = '[';
        foreach ($attributes as $key => $value) {
            // Use Str::studly or similar for camelCase conversion if needed for props
            // $propName = \Illuminate\Support\Str::camel($key);
            $propName = $key; // Keep original key for now
            $result .= "'$propName' => $value, ";
        }
        // Remove trailing comma and space if attributes exist
        if (!empty($attributes)) {
            $result = rtrim($result, ', ');
        }
        $result .= ']';

        return $result;
    }
}
