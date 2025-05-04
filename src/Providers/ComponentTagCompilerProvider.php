<?php

namespace Rcalicdan\Ci4Larabridge\Providers;

use Rcalicdan\Blade\Blade;

/**
 * Provides component tag syntax support (<x-component>) for Blade templates
 * without relying on Laravel's container or dependency injection.
 *
 * This implementation processes component tags at compile time and 
 * transforms them into regular Blade view rendering calls.
 */
class ComponentTagCompilerProvider
{
    /**
     * Component view namespace (used for x- prefixed components)
     *
     * @var string
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
        $blade->precompiler([$this, 'compile']);
    }
    
    /**
     * Compile all component tags in the given template string
     *
     * @param string $template
     * @return string
     */
    public function compile(string $template): string
    {
        // First compile <x-slot> tags within components
        $template = $this->compileSlots($template);
        
        // Then compile self-closing component tags
        $template = $this->compileSelfClosingTags($template);
        
        // Finally compile normal component tags
        $template = $this->compileComponentTags($template);
        
        return $template;
    }
    
    /**
     * Compile slot tags within components
     *
     * @param string $template
     * @return string
     */
    protected function compileSlots(string $template): string
    {
        $pattern = '/<x-slot\s+name=(["\'])(.*?)\1\s*(:[^>]*)?>(.*?)<\/x-slot>/s';
        
        return preg_replace_callback($pattern, function ($matches) {
            $name = $matches[2];
            $content = $matches[4];
            
            return "<?php \$__currentSlot = '{$name}'; ob_start(); ?>" .
                   $content .
                   "<?php \$__componentData['{$name}'] = ob_get_clean(); unset(\$__currentSlot); ?>";
        }, $template);
    }
    
    /**
     * Compile self-closing component tags
     *
     * @param string $template
     * @return string
     */
    protected function compileSelfClosingTags(string $template): string
    {
        $pattern = '/<x-([a-z0-9\-:.]+)\s*([^>]*)\/>/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $this->parseAttributes($matches[2] ?? '');
            $componentPath = $this->resolveComponentPath($component);
            
            return $this->compileSelfClosingComponent($componentPath, $attributes);
        }, $template);
    }
    
    /**
     * Compile normal component tags with content
     *
     * @param string $template
     * @return string
     */
    protected function compileComponentTags(string $template): string
    {
        $pattern = '/<x-([a-z0-9\-:.]+)\s*([^>]*)>(.*?)<\/x-\1>/is';
        
        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $this->parseAttributes($matches[2] ?? '');
            $content = $matches[3];
            $componentPath = $this->resolveComponentPath($component);
            
            return $this->compileComponent($componentPath, $attributes, $content);
        }, $template);
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
    
    /**
     * Resolve component name to a view path
     *
     * @param string $component
     * @return string
     */
    protected function resolveComponentPath(string $component): string
    {
        // Handle namespaced components (directly specified)
        if (strpos($component, '::') !== false) {
            return $component;
        }
        
        // Handle x- prefixed components
        if (str_starts_with($component, 'x-')) {
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
     * Generate PHP code for a self-closing component
     *
     * @param string $componentPath
     * @param string $attributes
     * @return string
     */
    protected function compileSelfClosingComponent(string $componentPath, string $attributes): string
    {
        return "<?php 
            \$__componentPath = '{$componentPath}';
            \$__componentAttributes = {$attributes};
            \$__componentData = array_merge(get_defined_vars(), \$__componentAttributes);
            \$__internalVars = ['__env', '__data', '__componentPath', '__componentAttributes', '__componentData'];
            \$__componentData = array_filter(\$__componentData, function(\$key) use (\$__internalVars) {
                return !in_array(\$key, \$__internalVars) && !str_starts_with(\$key, '__');
            }, ARRAY_FILTER_USE_KEY);
            \$__componentData['slot'] = '';
            echo blade_view(\$__componentPath, \$__componentData, true);
        ?>";
    }
    
    /**
     * Generate PHP code for a component with content
     *
     * @param string $componentPath
     * @param string $attributes
     * @param string $content
     * @return string
     */
    protected function compileComponent(string $componentPath, string $attributes, string $content): string
    {
        return "<?php 
            \$__componentPath = '{$componentPath}';
            \$__componentAttributes = {$attributes};
            \$__componentData = array_merge(get_defined_vars(), \$__componentAttributes);
            \$__internalVars = ['__env', '__data', '__componentPath', '__componentAttributes', '__componentData'];
            \$__componentData = array_filter(\$__componentData, function(\$key) use (\$__internalVars) {
                return !in_array(\$key, \$__internalVars) && !str_starts_with(\$key, '__');
            }, ARRAY_FILTER_USE_KEY);
            ob_start();
        ?>{$content}<?php
            \$__componentData['slot'] = ob_get_clean();
            echo blade_view(\$__componentPath, \$__componentData, true);
        ?>";
    }
}