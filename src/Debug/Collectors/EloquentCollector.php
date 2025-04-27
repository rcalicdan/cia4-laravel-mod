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
            return Capsule::connection()->getQueryLog();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Format SQL with bindings for display
     */
    protected function formatSql(string $sql, array $bindings): string
    {
        if (empty($bindings)) {
            return $sql;
        }

        // For each binding, replace the placeholder with the actual value
        $index = 0;
        return preg_replace_callback('/\?/', function () use ($bindings, &$index) {
            $value = $bindings[$index] ?? '?';
            $index++;

            // Format the value based on its type
            if (is_null($value)) {
                return 'NULL';
            }
            if (is_numeric($value)) {
                return $value;
            }
            if (is_bool($value)) {
                return $value ? 'TRUE' : 'FALSE';
            }

            // Escape strings
            return "'" . addslashes($value) . "'";
        }, $sql);
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

        // Use a fixed start time if we don't have real timing info
        $startTime = 0;

        foreach ($queries as $index => $query) {
            // Use time in ms if available, or a default of 0
            $duration = isset($query['time']) ? (float) $query['time'] : 0;

            // Create a summarized query name
            $queryType = preg_match('/^(SELECT|INSERT|UPDATE|DELETE|SHOW|ALTER|CREATE|DROP)/i', $query['query'], $matches)
                ? strtoupper($matches[1])
                : 'QUERY';

            $name = "#{$index} {$queryType}";

            $data[] = [
                'name'      => $name,
                'component' => 'Eloquent',
                'start'     => $startTime,
                'duration'  => $duration,
            ];

            // Increment the start time for the next query for better visualization
            $startTime += $duration;
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

        $output = '<table class="table table-striped">';
        $output .= '<thead><tr>';
        $output .= '<th style="width: 6%;">Time</th>';
        $output .= '<th style="width: 10%;">Connection</th>';
        $output .= '<th style="width: 84%;">Query</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($queries as $index => $query) {
            $time = isset($query['time']) ? sprintf('%.2f ms', $query['time']) : 'N/A';
            $connection = 'default'; // Or get the actual connection name if you have multiple

            // Format the SQL query with bindings
            $formattedSql = $this->formatSql($query['query'], $query['bindings'] ?? []);

            $output .= '<tr>';
            $output .= '<td class="text-right">' . $time . '</td>';
            $output .= '<td>' . htmlspecialchars($connection) . '</td>';
            $output .= '<td>' . htmlspecialchars($formattedSql) . '</td>';
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
