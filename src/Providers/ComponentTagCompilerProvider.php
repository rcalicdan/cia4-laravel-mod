<?php

namespace Rcalicdan\Ci4Larabridge\Providers;

use Rcalicdan\Blade\Blade;
use DOMDocument;
use DOMXPath;
use DOMNode;
use DOMElement;

/**
 * Provides custom component tag syntax support (<h-component>) for Blade templates
 * using DOMDocument for robust parsing with HTML5 compatibility.
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
     * Process all component tags in the template
     */
    public function processComponentTags(string $content): string
    {
        // Save original document for comparison
        $originalContent = $content;
        
        // Prepare content by protecting Blade syntax
        $wrappedContent = $this->prepareContentForDOM($content);
        
        // Suppress libxml errors temporarily
        $useInternalErrors = libxml_use_internal_errors(true);
        
        try {
            // Load the document using HTML parsing for HTML5 compatibility
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->loadHTML($wrappedContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            // Create XPath object to query for components
            $xpath = new DOMXPath($dom);
            
            // Process slots first
            $this->processSlotTags($xpath);
            
            // Process both self-closing and standard component tags
            $this->processComponentElements($xpath);
            
            // Extract content from wrapper
            $processedContent = $this->extractContentFromDOM($dom);
            
            // If no component tags were found or processing failed, return original content
            if ($processedContent === $wrappedContent || !$this->containsComponentTags($originalContent)) {
                return $originalContent;
            }
            
            return $processedContent;
        } finally {
            // Restore previous libxml error handling setting
            libxml_use_internal_errors($useInternalErrors);
        }
    }
    
    /**
     * Check if content contains any component tags to process
     */
    protected function containsComponentTags(string $content): bool
    {
        return preg_match('/<h-[a-z0-9\-:.]+/i', $content) === 1;
    }
    
    /**
     * Prepare template content for DOM parsing by protecting Blade syntax
     */
    protected function prepareContentForDOM(string $content): string
    {
        // Replace Blade directives with placeholders
        $content = preg_replace_callback('/@([\w]+)(\s*\([^)]*\)|\s+|$)/m', function($matches) {
            return '<!--BLADE_DIRECTIVE:' . base64_encode($matches[0]) . '-->';
        }, $content);
        
        // Replace Blade expressions with placeholders
        $content = preg_replace_callback('/\{\{(.*?)\}\}/s', function($matches) {
            return '<!--BLADE_EXPR:' . base64_encode($matches[0]) . '-->';
        }, $content);
        
        // Replace Blade raw expressions with placeholders
        $content = preg_replace_callback('/\{!!(.*?)!!\}/s', function($matches) {
            return '<!--BLADE_RAW:' . base64_encode($matches[0]) . '-->';
        }, $content);
        
        // Ensure UTF-8 encoding for proper parsing
        $content = '<!DOCTYPE html><html><meta charset="UTF-8"><body>' . $content . '</body></html>';
        
        return $content;
    }
    
    /**
     * Extract processed content from DOM and restore Blade syntax
     */
    protected function extractContentFromDOM(DOMDocument $dom): string
    {
        // Get body content
        $bodyNode = $dom->getElementsByTagName('body')->item(0);
        $content = '';
        
        if ($bodyNode) {
            // Extract children of body
            foreach ($bodyNode->childNodes as $node) {
                $content .= $dom->saveHTML($node);
            }
        } else {
            // Fallback if body not found
            $content = $dom->saveHTML();
        }
        
        // Restore Blade directives
        $content = preg_replace_callback('/<!--BLADE_DIRECTIVE:(.*?)-->/', function($matches) {
            return base64_decode($matches[1]);
        }, $content);
        
        // Restore Blade expressions
        $content = preg_replace_callback('/<!--BLADE_EXPR:(.*?)-->/', function($matches) {
            return base64_decode($matches[1]);
        }, $content);
        
        // Restore Blade raw expressions
        $content = preg_replace_callback('/<!--BLADE_RAW:(.*?)-->/', function($matches) {
            return base64_decode($matches[1]);
        }, $content);
        
        return $content;
    }
    
    /**
     * Process slot tags within components
     */
    protected function processSlotTags(DOMXPath $xpath): void
    {
        $slotNodes = $xpath->query('//h-slot');
        
        // Process in reverse to avoid issues with changing DOM structure
        for ($i = $slotNodes->length - 1; $i >= 0; $i--) {
            $slotNode = $slotNodes->item($i);
            if (!$slotNode instanceof DOMElement) continue;
            
            $name = $slotNode->getAttribute('name');
            if (empty($name)) $name = 'default';
            
            // Create slot directive nodes
            $startSlot = $slotNode->ownerDocument->createTextNode("@slot('{$name}')");
            $endSlot = $slotNode->ownerDocument->createTextNode('@endslot');
            
            // Replace slot tag with directives and its content
            $parent = $slotNode->parentNode;
            
            // Insert start directive
            $parent->insertBefore($startSlot, $slotNode);
            
            // Move all child nodes after the start directive
            while ($slotNode->firstChild) {
                $parent->insertBefore($slotNode->firstChild, $slotNode);
            }
            
            // Insert end directive
            $parent->insertBefore($endSlot, $slotNode);
            
            // Remove original slot tag
            $parent->removeChild($slotNode);
        }
    }
    
    /**
     * Process component elements - handles both self-closing and standard tags
     */
    protected function processComponentElements(DOMXPath $xpath): void
    {
        // Find all h- prefixed elements 
        $componentNodes = $xpath->query('//*[starts-with(local-name(), "h-")]');
        
        for ($i = $componentNodes->length - 1; $i >= 0; $i--) {
            $componentNode = $componentNodes->item($i);
            if (!$componentNode instanceof DOMElement) continue;
            
            $componentName = $componentNode->nodeName;
            $attributes = $this->extractAttributes($componentNode);
            $componentPath = $this->resolveComponentPath($componentName);
            
            // Check if tag is self-closing (has no meaningful children)
            $hasContent = false;
            foreach ($componentNode->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE ||
                    ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) !== '')) {
                    $hasContent = true;
                    break;
                }
            }
            
            // Get parent node for insertion
            $parent = $componentNode->parentNode;
            
            if (!$hasContent) {
                // Self-closing component
                $directive = "@component('{$componentPath}', {$attributes}, true)";
                $newNode = $componentNode->ownerDocument->createTextNode($directive);
                $parent->replaceChild($newNode, $componentNode);
            } else {
                // Standard component with content
                $startDirective = $componentNode->ownerDocument->createTextNode("@component('{$componentPath}', {$attributes})");
                $parent->insertBefore($startDirective, $componentNode);
                
                // Move component's children after the start directive
                while ($componentNode->firstChild) {
                    $parent->insertBefore($componentNode->firstChild, $componentNode);
                }
                
                // Create end directive
                $endDirective = $componentNode->ownerDocument->createTextNode('@endcomponent');
                $parent->insertBefore($endDirective, $componentNode);
                
                // Remove original component tag
                $parent->removeChild($componentNode);
            }
        }
    }
    
    /**
     * Extract all attributes from a component DOM element with HTML5 compatibility
     */
    protected function extractAttributes(DOMElement $element): string
    {
        $attributes = [];
        
        // Process all attributes on the element
        foreach ($element->attributes as $attr) {
            $name = $attr->nodeName;
            $value = $attr->nodeValue;
            
            // Handle different attribute types
            if (strpos($name, ':') === 0) {
                // Bound attribute (:prop="expression")
                $name = substr($name, 1);
                $attributes[$name] = $value; // Already PHP expression
            } elseif (preg_match('/^\{\{(.*)\}\}$/', $value, $matches)) {
                // Blade expression (prop="{{ expression }}")
                $attributes[$name] = trim($matches[1]);
            } elseif ($value === '' && !$attr->specified) {
                // HTML5 boolean attribute (present without value)
                $attributes[$name] = 'true';
            } else {
                // Regular string attribute
                $attributes[$name] = "'" . addslashes($value) . "'";
            }
        }
        
        // Build attributes array string
        $result = '[';
        foreach ($attributes as $key => $value) {
            $result .= "'" . addslashes($key) . "' => " . $value . ", ";
        }
        if (!empty($attributes)) {
            $result = rtrim($result, ', ');
        }
        $result .= ']';
        
        return $result;
    }
    
    /**
     * Resolve component name to a view path
     */
    protected function resolveComponentPath(string $component): string
    {
        // Strip "h-" prefix
        if (str_starts_with($component, 'h-')) {
            $component = substr($component, 2);
        }
        
        // Handle namespaced components (directly specified)
        if (strpos($component, '::') !== false) {
            return $component;
        }

        // Handle dot notation paths
        if (strpos($component, '.') !== false) {
            return $component;
        }

        // Default to components namespace
        return $this->componentNamespace . '::' . $component;
    }
}