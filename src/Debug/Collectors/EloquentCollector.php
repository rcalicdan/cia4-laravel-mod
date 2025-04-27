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
            $log = Capsule::connection()->getQueryLog();

            return $log;
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

        $index = 0;
        return preg_replace_callback('/\?/', function () use ($bindings, &$index) {
            $value = $bindings[$index] ?? '?';
            $index++;

            if (is_null($value)) {
                return 'NULL';
            }
            if (is_numeric($value)) {
                return $value;
            }
            if (is_bool($value)) {
                return $value ? 'TRUE' : 'FALSE';
            }

            return "'" . addslashes($value) . "'";
        }, $sql);
    }

    protected function normalizeTime($time): float
    {
        $time = (float) $time;

        // If time is already in seconds, convert to milliseconds
        if ($time > 0 && $time < 0.1) {
            $time = $time * 1000;
        }

        return $time;
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
        
        // Get request start time from CI4's performance metrics if possible
        $startTime = 0;
        $lastEndTime = $startTime;
        
        foreach ($queries as $index => $query) {
            // Get raw time value first
            $rawTime = 0;
            if (isset($query['time'])) {
                $rawTime = (float) $query['time'];
            } elseif (isset($query['duration'])) {
                $rawTime = (float) $query['duration'];
            } elseif (isset($query['elapsed'])) {
                $rawTime = (float) $query['elapsed'];
            }
            
            // Convert to milliseconds if needed
            if ($rawTime > 0 && $rawTime < 0.1) {
                $rawTime *= 1000;
            }
            
            $queryType = preg_match('/^(SELECT|INSERT|UPDATE|DELETE|SHOW|ALTER|CREATE|DROP)/i', $query['query'], $matches) 
                ? strtoupper($matches[1]) 
                : 'QUERY';
            
            // Calculate a reasonable start time 
            // This is an approximation since we don't know exact query start times
            $start = $lastEndTime - $startTime;
            $lastEndTime += $rawTime / 1000; // Convert ms back to seconds for next calculation
            
            $data[] = [
                'name'      => "#{$index} {$queryType}",
                'component' => 'Eloquent',
                'start'     => $start * 1000, // Convert to milliseconds 
                'duration'  => $rawTime,
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

        $output = '<table class="table table-striped">';
        $output .= '<thead><tr>';
        $output .= '<th>#</th>';
        $output .= '<th>Time</th>';
        $output .= '<th>Query</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($queries as $index => $query) {
            // Get execution time
            $duration = 0;
            if (isset($query['time'])) {
                $duration = $this->normalizeTime($query['time']);
            } elseif (isset($query['duration'])) {
                $duration = $this->normalizeTime($query['duration']);
            } elseif (isset($query['elapsed'])) {
                $duration = $this->normalizeTime($query['elapsed']);
            }

            // No need for additional conversion here
            $time = sprintf('%.2f ms', $duration);

            // Format the SQL query with bindings
            $formattedSql = $this->formatSql($query['query'], $query['bindings'] ?? []);

            $output .= '<tr>';
            $output .= '<td>' . ($index + 1) . '</td>';
            $output .= '<td class="text-right">' . $time . '</td>';
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
