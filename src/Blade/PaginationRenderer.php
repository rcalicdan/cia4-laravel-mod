<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Pagination\LengthAwarePaginator;
use Rcalicdan\Blade\Blade; // Your Blade engine class

class PaginationRenderer
{
    protected Blade $blade;
    protected int $window; // Number of links on each side of current page

    public function __construct(Blade $blade)
    {
        $this->blade = $blade;
        $paginationConfig = config('Pagination');
        // Ensure window has a sensible default if not configured
        $this->window = $paginationConfig->window ?? 3;
    }

    /**
     * Render pagination links using Blade views.
     */
    public function render(LengthAwarePaginator $paginator, ?string $theme = null): string
    {
        // Don't render if there's only one page
        if (!$paginator->hasPages()) {
            return '';
        }

        $paginationConfig = config('Pagination');
        $theme = $theme ?? $paginationConfig->theme ?? 'bootstrap';

        // Calculate the elements array manually
        $elements = $this->getElements($paginator);

        $viewName = "pagination::{$theme}";

        // Basic view existence check (adapt as needed)
        $viewExists = $this->viewExists($viewName);

        if (!$viewExists) {
             log_message('warning', "Pagination view '{$viewName}' not found. Falling back to 'pagination::bootstrap'.");
             $viewName = 'pagination::bootstrap';
             if (!$this->viewExists($viewName)) {
                 log_message('error', "Fallback pagination view 'pagination::bootstrap' not found. Cannot render pagination.");
                 return '<!-- Pagination Error: Bootstrap view missing -->';
             }
        }

        try {
            return $this->blade->render($viewName, [
                'paginator' => $paginator,
                'elements' => $elements, // Pass the manually calculated elements
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Error rendering pagination view (' . $viewName . '): ' . $e->getMessage() . $e->getTraceAsString());
            return '<!-- Pagination Rendering Error -->';
        }
    }

    /**
     * Check if a Blade view exists.
     */
    protected function viewExists(string $viewName): bool
    {
         try {
            // Option 1: If your Blade instance has an 'exists' method
            if (method_exists($this->blade, 'exists')) {
                return $this->blade->exists($viewName);
            }
            // Option 2: If your Blade instance has a 'getFinder' method
            if (method_exists($this->blade, 'getFinder') && method_exists($this->blade->getFinder(), 'find')) {
                 $this->blade->getFinder()->find($viewName); // Throws exception if not found
                 return true;
            }
            // Default assumption if no check method is found
            return true;
        } catch (\InvalidArgumentException $e) { // Catch exceptions from find()
             return false;
        } catch (\Throwable $e) { // Catch other potential errors
             log_message('error', 'Error checking view existence: ' . $e->getMessage());
             return false; // Assume not found on error
        }
    }

    /**
     * Manually calculate the pagination elements (links and ellipsis).
     * Returns an array compatible with the pagination Blade views.
     */
    protected function getElements(LengthAwarePaginator $paginator): array
    {
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $onEachSide = $this->window; // Use the configured window size

        // If there are not enough pages to necessitate truncation, display all page links.
        if ($lastPage <= ($onEachSide * 2) + 5) {
            $elements = $this->generatePageRange(1, $lastPage, $paginator);
        }
        // If the current page is near the start
        elseif ($currentPage <= $onEachSide + 2) {
            $elements = [
                $this->generatePageRange(1, min($onEachSide * 2 + 1, $lastPage), $paginator), // Show starting pages
                $this->getEllipsis(), // Ellipsis
                $this->generatePageRange($lastPage, $lastPage, $paginator), // Last page
            ];
        }
        // If the current page is near the end
        elseif ($currentPage > $lastPage - ($onEachSide + 2)) {
             $elements = [
                $this->generatePageRange(1, 1, $paginator), // First page
                $this->getEllipsis(), // Ellipsis
                $this->generatePageRange(max(1, $lastPage - ($onEachSide * 2)), $lastPage, $paginator), // Show ending pages
            ];
        }
        // If the current page is somewhere in the middle
        else {
             $elements = [
                $this->generatePageRange(1, 1, $paginator), // First page
                $this->getEllipsis(), // Ellipsis
                $this->generatePageRange($currentPage - $onEachSide, $currentPage + $onEachSide, $paginator), // Window around current
                $this->getEllipsis(), // Ellipsis
                $this->generatePageRange($lastPage, $lastPage, $paginator), // Last page
            ];
        }

        // Flatten slightly and filter out nulls/empty arrays
        $finalElements = [];
        foreach ($elements as $element) {
            if (is_array($element) && !empty($element)) {
                $finalElements[] = $element;
            } elseif (is_string($element)) {
                 // Check if the last element was also an ellipsis to avoid double "..."
                 if (empty($finalElements) || end($finalElements) !== $element) {
                     $finalElements[] = $element;
                 }
            }
        }

        return $finalElements;
    }

    /**
     * Generate an array of page numbers and URLs for a given range.
     * This is the key method that needs to be fixed.
     */
    protected function generatePageRange(int $start, int $end, LengthAwarePaginator $paginator): array
    {
        $links = [];
        for ($page = $start; $page <= $end; $page++) {
            // Use page number as key and URL as value - this is crucial for proper rendering
            $links[$page] = $paginator->url($page);
        }
        return $links;
    }

    /**
     * Get the ellipsis string marker.
     */
    protected function getEllipsis(): string
    {
        return '...';
    }

    /**
     * Helper to access the Blade instance.
     */
    public function getBladeInstance(): Blade
    {
        return $this->blade;
    }
}