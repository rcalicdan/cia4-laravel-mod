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
    protected $hasTimeline = false;

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

            return match (true) {
                is_null($value) => 'NULL',
                is_numeric($value) => $value,
                is_bool($value) => $value ? 'TRUE' : 'FALSE',
                default => "'" . addslashes($value) . "'"
            };
        }, $sql);
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

        $tableHeader = $this->buildTableHeader();
        $tableRows = $this->buildTableRows($queries);

        return $tableHeader . $tableRows . '</tbody></table>';
    }

    private function buildTableHeader(): string
    {
        return <<<HTML
            <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Time</th>
                    <th>Query</th>
                </tr>
            </thead>
            <tbody>
        HTML;
    }

    private function buildTableRows(array $queries): string
    {
        $index = 0;
        return array_reduce($queries, function ($output, $query) use (&$index) {
            $duration = $this->calculateDuration($query);
            $time = sprintf('%.2f ms', $duration);
            $formattedSql = $this->formatSql($query['query'], $query['bindings'] ?? []);

            return $output . sprintf(
                '<tr><td>%d</td><td class="text-right">%s</td><td>%s</td></tr>',
                ++$index,
                $time,
                htmlspecialchars($formattedSql)
            );
        }, '');
    }

    private function calculateDuration(array $query): float
    {
        $duration = match (true) {
            isset($query['time']) => (float)$query['time'],
            isset($query['duration']) => (float)$query['duration'],
            isset($query['elapsed']) => (float)$query['elapsed'],
            default => 0
        };

        return ($duration > 0 && $duration < 0.1) ? $duration * 1000 : $duration;
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
