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
     *
     * @var string
     */
    protected $title = 'Eloquent';

    /**
     * Toggle to display only duplicate queries
     * 
     * @var boolean
     */
    protected $showOnlyDuplicates = false;

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

        // Track duplicate queries
        $duplicates = $this->findDuplicateQueries($queries);
        $duplicateCount = array_sum(array_map(function($item) { return $item['count'] - 1; }, $duplicates));

        // Toggle button for duplicates only
        $toggleButton = $this->buildToggleButton($duplicateCount);
        
        $tableHeader = $this->buildTableHeader();
        $tableRows = $this->buildTableRows($queries, $duplicates);

        return $toggleButton . $tableHeader . $tableRows . '</tbody></table>';
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
            
            if (!isset($normalized[$formattedSql])) {
                $normalized[$formattedSql] = [
                    'indices' => [$index],
                    'count' => (int) 1,
                    'total_time' => $this->calculateDuration($query)
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

    private function buildToggleButton(int $duplicateCount): string
    {
        $showDuplicatesText = $this->showOnlyDuplicates ? 'Show All Queries' : 'Show Only Duplicates';
        $buttonDisabled = $duplicateCount > 0 ? '' : 'disabled';
        $currentState = $this->showOnlyDuplicates ? '1' : '0';
        $buttonColor = $duplicateCount > 0 ? '#ffc107' : '#6c757d';
        $textColor = $duplicateCount > 0 ? '#000' : '#fff';
        
        $div = '<div style="margin-bottom: 15px;">';
        $div .= '<span style="display: inline-block; background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; margin-right: 10px;">' . $duplicateCount . ' Duplicate Queries</span>';
        $div .= '<button style="padding: 4px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; background-color: ' . $buttonColor . '; color: ' . $textColor . '; border: none;" ';
        $div .= 'id="toggle-duplicates" onclick="toggleDuplicateQueries(' . $currentState . ')" ' . $buttonDisabled . '>' . $showDuplicatesText . '</button>';
        $div .= '<script>';
        $div .= 'function toggleDuplicateQueries(currentState) {';
        $div .= '  var url = new URL(window.location.href);';
        $div .= '  var newState = currentState === 1 ? "0" : "1";';
        $div .= '  url.searchParams.set("show_duplicates", newState);';
        $div .= '  window.location.href = url.toString();';
        $div .= '}';
        $div .= '</script>';
        $div .= '</div>';
        
        return $div;
    }

    private function buildTableHeader(): string
    {
        return <<<HTML
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: left;">#</th>
                    <th style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: right;">Time</th>
                    <th style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: left;">Query</th>
                    <th style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: center;">Count</th>
                </tr>
            </thead>
            <tbody>
        HTML;
    }

    private function buildTableRows(array $queries, array $duplicates): string
    {
        // Check URL parameter to toggle display mode
        $this->showOnlyDuplicates = isset($_GET['show_duplicates']) && $_GET['show_duplicates'] === '1';
        
        $output = '';
        $index = 0;
        
        if ($this->showOnlyDuplicates) {
            // Show only duplicate queries
            foreach ($duplicates as $sql => $info) {
                $time = sprintf('%.2f ms', $info['total_time']);
                
                $output .= sprintf(
                    '<tr style="background-color: #fff8e1;"><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">%d</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: right;">%s</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">%s</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: center;"><span style="background-color: #dc3545; color: white; padding: 2px 6px; border-radius: 10px; font-size: 12px;">%d</span></td></tr>',
                    ++$index,
                    $time,
                    htmlspecialchars($sql),
                    $info['count']
                );
            }
        } else {
            // Show all queries with duplicate highlighting
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
                    '<tr style="%s"><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">%d</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: right;">%s</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">%s</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: center;">%s</td></tr>',
                    $rowStyle,
                    ++$index,
                    $time,
                    htmlspecialchars($formattedSql),
                    $countDisplay
                );
            }
        }
        
        return $output;
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