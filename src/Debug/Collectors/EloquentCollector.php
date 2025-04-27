<?php

namespace Rcalicdan\Ci4Larabridge\Debug\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;
use Illuminate\Database\Capsule\Manager as Capsule;

class EloquentCollector extends BaseCollector
{
    /**
     * Whether this collector has data that can be displayed in the Timeline.
     *
     * @var boolean
     */
    protected $hasTimeline = true;

    /**
     * Whether this collector needs to display content in a tab or not.
     *
     * @var boolean
     */
    protected $hasTabContent = true;

    /**
     * The 'title' of this Collector.
     * Used to name things in the toolbar HTML.
     *
     * @var string
     */
    protected $title = 'Eloquent';

    /**
     * Get database query log
     */
    protected function getQueryLog(): array
    {
        try {
            // We can't call getInstance() because it doesn't exist
            // Instead, access the connection directly through the global manager
            return Capsule::connection()->getQueryLog();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Returns timeline data formatted for the toolbar.
     *
     * @return array The formatted data or an empty array.
     */
    protected function formatTimelineData(): array
    {
        $data = [];

        $queries = $this->getQueryLog();
        
        foreach ($queries as $index => $query) {
            // Calculate query time in ms
            $time = isset($query['time']) ? $query['time'] : 0;
            
            // Create a simplified SQL with bindings for display
            $sql = $query['query'];
            if (!empty($query['bindings'])) {
                foreach ($query['bindings'] as $binding) {
                    $value = is_numeric($binding) ? $binding : "'{$binding}'";
                    $sql = preg_replace('/\?/', $value, $sql, 1);
                }
            }
            
            $data[] = [
                'name'      => 'Query ' . ($index + 1),
                'component' => 'Eloquent',
                'start'     => 0, // We don't have exact start time
                'duration'  => $time,
            ];
        }

        return $data;
    }

    /**
     * Returns the data of this collector to be formatted in the toolbar
     */
    public function display(): string
    {
        $queries = $this->getQueryLog();
        
        if (empty($queries)) {
            return '<p>No Eloquent queries were recorded.</p>';
        }

        $output = '<table><thead><tr>';
        $output .= '<th>Query</th><th>Bindings</th><th>Time (ms)</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($queries as $query) {
            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars($query['query']) . '</td>';
            $output .= '<td>' . htmlspecialchars(json_encode($query['bindings'])) . '</td>';
            $output .= '<td>' . ($query['time'] ?? 'N/A') . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        return $output;
    }

    /**
     * Gets the "badge" value for the button.
     */
    public function getBadgeValue(): int
    {
        return count($this->getQueryLog());
    }

    /**
     * Display the icon.
     *
     * Icon from https://icons.getbootstrap.com
     */
    public function icon(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAADMSURBVEhL5ZSxDYJAEEWpwDK4lidWYAeWQh1cB1aAJZCcJRgJBYzGxO/GetOdD5b1xxcGbm9vdvZM0fM8L4RhyEghhBaQUt40Ta2hKIqGgoBZDEqpGiZ5ni8xJklSEzCIwRw+DMOGgEEMjuM8GIbhASHfjWkM7vueNU3zQgi5syzbjW3CGIYB8/0Mz2MzguFcw2GzGdkRDMEYLqU8fgJGS9AXUBWwEPiHBK1BXyA1sJNQBwFdQQxsCdQVzBBwEojNmSGQxMB2TAkuY/ANrR3SCFpRemsAAAAASUVORK5CYII=';
    }
}