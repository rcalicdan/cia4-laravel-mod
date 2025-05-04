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

    public function processComponentTags(string $content): string
    {
        // Debug the content to make sure we have the complete HTML
        file_put_contents(WRITEPATH . 'logs/content_debug.log', $content, FILE_APPEND);

        // First, properly identify all h-tags (just for logging)
        preg_match_all('/<h-[^>]*(?:>|\/>)/s', $content, $allTags);
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

    protected function compileSelfClosingTags(string $content): string
    {
        // Updated pattern to more reliably match self-closing tags
        $pattern = '/<h-([a-z0-9\-:.]+)\s*(.*?)(\/)?>/s';

        return preg_replace_callback($pattern, function ($matches) use ($content) {
            $component = $matches[1];
            $attributesString = $matches[2];
            $isSelfClosing = !empty($matches[3]) || strpos($content, "</h-{$component}>") === false;

            // Debug capture
            file_put_contents(
                WRITEPATH . 'logs/tag_debug.log',
                "Component: $component\nAttributes: $attributesString\nSelf-closing: " .
                    ($isSelfClosing ? 'true' : 'false') . "\n\n",
                FILE_APPEND
            );

            $attributes = $this->parseAttributes($attributesString);
            $componentPath = $this->resolveComponentPath($component);

            file_put_contents(
                WRITEPATH . 'logs/component_tags.log',
                "Component: $component\nAttributes: $attributes\nPath: $componentPath\n\n",
                FILE_APPEND
            );

            return "@component('{$componentPath}', {$attributes}, true)";
        }, $content);
    }

    protected function compileStandardComponentTags(string $content): string
    {
        // Updated pattern to capture component content properly
        $pattern = '/<h-([a-z0-9\-:.]+)\s*(.*?)>(.*?)<\/h-\1>/s';

        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $this->parseAttributes($matches[2]);
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

    protected function parseAttributes(string $attributeString): string
    {
        $attributes = [];

        // Log the raw attribute string for debugging
        file_put_contents(
            WRITEPATH . 'logs/attr_raw.log',
            "Raw: " . $attributeString . "\n",
            FILE_APPEND
        );

        // Normalize spaces
        $attributeString = trim(preg_replace('/\s+/', ' ', $attributeString));

        // Match Blade expressions: name="{{ expression }}"
        if (preg_match_all('/([a-zA-Z0-9_-]+)=(["\'])\{\{(.*?)\}\}\2/s', $attributeString, $bladeMatches, PREG_SET_ORDER)) {
            foreach ($bladeMatches as $match) {
                $attrName = $match[1];
                $expression = trim($match[3]);
                $attributes[$attrName] = $expression; // PHP expression
                // Remove from string for next stages
                $attributeString = str_replace($match[0], '', $attributeString);
            }
        }

        // Match bound attributes: :name="expression"
        if (preg_match_all('/:([a-zA-Z0-9_-]+)=(["\'])(.*?)\2/s', $attributeString, $boundMatches, PREG_SET_ORDER)) {
            foreach ($boundMatches as $match) {
                $attrName = $match[1];
                $expression = $match[3];
                $attributes[$attrName] = $expression; // Already PHP code
                // Remove from string for next stages
                $attributeString = str_replace($match[0], '', $attributeString);
            }
        }

        // Match regular attributes: name="value"
        if (preg_match_all('/([a-zA-Z0-9_-]+)=(["\'])(.*?)\2/s', $attributeString, $regularMatches, PREG_SET_ORDER)) {
            foreach ($regularMatches as $match) {
                if (!isset($attributes[$match[1]])) {
                    $attributes[$match[1]] = "'" . addslashes($match[3]) . "'";
                }
                // Remove from string for next stages
                $attributeString = str_replace($match[0], '', $attributeString);
            }
        }

        // Match boolean attributes (what's left)
        $attributeString = trim($attributeString);
        if (!empty($attributeString)) {
            $booleanAttrs = preg_split('/\s+/', $attributeString);
            foreach ($booleanAttrs as $attr) {
                if (!empty($attr) && !isset($attributes[$attr])) {
                    $attributes[$attr] = 'true';
                }
            }
        }

        // Build the attributes array string
        $result = '[';
        foreach ($attributes as $key => $value) {
            $result .= "'" . addslashes($key) . "' => " . $value . ", ";
        }
        if (!empty($attributes)) {
            $result = rtrim($result, ', ');
        }
        $result .= ']';

        // Debug the parsed result
        file_put_contents(
            WRITEPATH . 'logs/component_attributes.log',
            "Original: " . $attributeString . "\n" .
                "Parsed: " . $result . "\n",
            FILE_APPEND
        );

        return $result;
    }
}
