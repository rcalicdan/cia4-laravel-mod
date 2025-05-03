<?php

namespace Rcalicdan\Ci4Larabridge\Debug\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;
use Illuminate\Database\Capsule\Manager as Capsule;

class EloquentCollector extends BaseCollector
{
    /**
     * Whether this collector has data that can be displayed in the Timeline.
     *
     * @var bool
     */
    protected $hasTimeline = false;

    /**
     * Whether this collector needs to display content in a tab or not.
     *
     * @var bool
     */
    protected $hasTabContent = true;

    /**
     * The 'title' of this Collector.
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
            if (ENVIRONMENT !== 'production') {
                $log = Capsule::connection()->getQueryLog();
                return $log;
            }
            return [];
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
                default => "'".addslashes($value)."'"
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

        $duplicates = $this->findDuplicateQueries($queries);
        $duplicateCount = array_sum(array_map(function ($item) {
            return $item['count'] - 1;
        }, $duplicates));

        $output = $this->buildSummarySection($duplicateCount);
        $output .= $this->buildTableHeader();
        $output .= $this->buildTableRows($queries, $duplicates);
        $output .= '</tbody></table>';

        return $output;
    }

    /**
     * Find duplicate queries and count them
     */
    protected function findDuplicateQueries(array $queries): array
    {
        $normalized = [];
        $duplicates = [];

        foreach ($queries as $index => $query) {
            $formattedSql = $this->formatSql($query['query'], $query['bindings'] ?? []);

            if (! isset($normalized[$formattedSql])) {
                $normalized[$formattedSql] = [
                    'indices' => [$index],
                    'count' => (int) 1,
                    'total_time' => $this->calculateDuration($query),
                ];
            } else {
                $normalized[$formattedSql]['indices'][] = $index;
                $normalized[$formattedSql]['count'] = (int) ($normalized[$formattedSql]['count'] + 1);
                $normalized[$formattedSql]['total_time'] += $this->calculateDuration($query);
            }
        }

        foreach ($normalized as $sql => $info) {
            if ($info['count'] > 1) {
                $duplicates[$sql] = $info;
            }
        }

        return $duplicates;
    }

    /**
     * Build the summary section showing duplicate count
     */
    private function buildSummarySection(int $duplicateCount): string
    {
        $summaryStyle = 'margin-bottom: 15px;';
        $badgeStyle = 'display: inline-block; background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; margin-right: 10px;';
        
        return "<div style=\"{$summaryStyle}\">
                    <span style=\"{$badgeStyle}\">{$duplicateCount} Duplicate Queries</span>
                </div>";
    }

    /**
     * Build the table header
     */
    private function buildTableHeader(): string
    {
        return <<<'HTML'
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="padding: 8px; border-bottom: 1px solid #000000; color:black; text-align: left;">#</th>
                    <th style="padding: 8px; border-bottom: 1px solid #000000; color:black; text-align: right;">Time</th>
                    <th style="padding: 8px; border-bottom: 1px solid #000000; color:black; text-align: left;">Query</th>
                    <th style="padding: 8px; border-bottom: 1px solid #000000; color:black; text-align: center;">Count</th>
                </tr>
            </thead>
            <tbody>
        HTML;
    }

    /**
     * Build table rows showing all queries with their counts
     */
    private function buildTableRows(array $queries, array $duplicates): string
    {
        $output = '';
        $index = 0;
        
        // Process all queries and highlight duplicates
        foreach ($queries as $i => $query) {
            $formattedSql = $this->formatSql($query['query'], $query['bindings'] ?? []);
            $duration = $this->calculateDuration($query);
            $time = sprintf('%.2f ms', $duration);

            $isDuplicate = isset($duplicates[$formattedSql]);
            $count = $isDuplicate ? $duplicates[$formattedSql]['count'] : 1;
            $rowStyle = $isDuplicate ? 'background-color: #fff8e1;' : ($i % 2 === 0 ? 'background-color: #f8f9fa;' : 'background-color: #ffffff;');
            $countDisplay = $isDuplicate
                ? sprintf('<span style="background-color: #dc3545; color: white; padding: 2px 6px; border-radius: 10px; font-size: 12px;">%d</span>', $count)
                : '1';

            $output .= sprintf(
                '<tr style="%s"><td style="padding: 8px; border-bottom: 1px solid #000000; color:black;">%d</td><td style="padding: 8px; border-bottom: 1px solid #000000; color:black; text-align: right;">%s</td><td style="padding: 8px; border-bottom: 1px solid #000000; color:black;">%s</td><td style="padding: 8px; border-bottom: 1px solid #000000; color:black; text-align: center;">%s</td></tr>',
                $rowStyle,
                ++$index,
                $time,
                htmlspecialchars($formattedSql),
                $countDisplay
            );
        }

        return $output;
    }

    /**
     * Calculate query duration in milliseconds
     */
    private function calculateDuration(array $query): float
    {
        $duration = match (true) {
            isset($query['time']) => (float) $query['time'],
            isset($query['duration']) => (float) $query['duration'],
            isset($query['elapsed']) => (float) $query['elapsed'],
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