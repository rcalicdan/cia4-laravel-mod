<?php

namespace Rcalicdan\Ci4Larabridge\Providers;

use Rcalicdan\Blade\Blade;
use DOMDocument;
use DOMXPath;
use DOMNode;
use DOMElement;

/**
 * Provides custom component tag syntax support (<h-component>) for Blade templates
 * using DOMDocument for robust parsing.
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
        // Create a valid XML document by wrapping content in a root element
        // and adding CDATA sections around blade directives to prevent parsing errors
        $wrappedContent = $this->prepareContentForDOM($content);

        // Load the document
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($wrappedContent, LIBXML_NOERROR | LIBXML_HTML_NODEFDTD);

        // Create XPath object to query for components
        $xpath = new DOMXPath($dom);

        // Process slots first
        $this->processSlotTags($xpath);

        // Process self-closing components
        $this->processSelfClosingTags($xpath);

        // Process standard component tags
        $this->processStandardComponentTags($xpath);

        // Extract content from wrapper
        $processedContent = $this->extractContentFromDOM($dom);

        return $processedContent;
    }

    /**
     * Prepare template content for DOM parsing by protecting Blade syntax
     */
    protected function prepareContentForDOM(string $content): string
    {
        // Replace Blade directives with placeholders
        $content = preg_replace_callback('/@(.*?)(?:\s|\(|$)/m', function ($matches) {
            return '<!--BLADE_DIRECTIVE:' . base64_encode($matches[0]) . '-->';
        }, $content);

        // Replace Blade expressions with placeholders
        $content = preg_replace_callback('/\{\{(.*?)\}\}/s', function ($matches) {
            return '<!--BLADE_EXPR:' . base64_encode($matches[0]) . '-->';
        }, $content);

        // Replace Blade raw expressions with placeholders
        $content = preg_replace_callback('/\{!!(.*?)!!\}/s', function ($matches) {
            return '<!--BLADE_RAW:' . base64_encode($matches[0]) . '-->';
        }, $content);

        // Wrap in a root element for proper XML parsing
        return '<root>' . $content . '</root>';
    }

    /**
     * Extract processed content from DOM and restore Blade syntax
     */
    protected function extractContentFromDOM(DOMDocument $dom): string
    {
        // Get inner content of root
        $content = '';
        foreach ($dom->documentElement->childNodes as $node) {
            $content .= $dom->saveXML($node);
        }

        // Restore Blade directives
        $content = preg_replace_callback('/<!--BLADE_DIRECTIVE:(.*?)-->/', function ($matches) {
            return base64_decode($matches[1]);
        }, $content);

        // Restore Blade expressions
        $content = preg_replace_callback('/<!--BLADE_EXPR:(.*?)-->/', function ($matches) {
            return base64_decode($matches[1]);
        }, $content);

        // Restore Blade raw expressions
        $content = preg_replace_callback('/<!--BLADE_RAW:(.*?)-->/', function ($matches) {
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
     * Process self-closing component tags
     */
    protected function processSelfClosingTags(DOMXPath $xpath): void
    {
        // Find all h- prefixed elements that are self-closing (no children)
        $componentNodes = $xpath->query('//*[starts-with(local-name(), "h-") and not(*) and not(text()[normalize-space()])]');

        for ($i = $componentNodes->length - 1; $i >= 0; $i--) {
            $componentNode = $componentNodes->item($i);
            if (!$componentNode instanceof DOMElement) continue;

            $componentName = substr($componentNode->nodeName, 2); // Remove 'h-' prefix
            $attributes = $this->extractAttributes($componentNode);
            $componentPath = $this->resolveComponentPath($componentName);

            // Create component directive
            $directive = "@component('{$componentPath}', {$attributes}, true)";
            $newNode = $componentNode->ownerDocument->createTextNode($directive);

            // Replace component tag with directive
            $componentNode->parentNode->replaceChild($newNode, $componentNode);
        }
    }

    /**
     * Process standard component tags with content
     */
    protected function processStandardComponentTags(DOMXPath $xpath): void
    {
        // Find all remaining h- prefixed elements (ones with content)
        $componentNodes = $xpath->query('//*[starts-with(local-name(), "h-")]');

        for ($i = $componentNodes->length - 1; $i >= 0; $i--) {
            $componentNode = $componentNodes->item($i);
            if (!$componentNode instanceof DOMElement) continue;

            $componentName = substr($componentNode->nodeName, 2); // Remove 'h-' prefix
            $attributes = $this->extractAttributes($componentNode);
            $componentPath = $this->resolveComponentPath($componentName);

            // Get parent node for insertion
            $parent = $componentNode->parentNode;

            // Create start directive
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

    /**
     * Extract all attributes from a component DOM element
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
}
