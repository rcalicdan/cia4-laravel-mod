<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;

class ToolbarHandler extends SetupHandler
{
    private const string COLLECTOR_CLASS = 'Rcalicdan\\Ci4Larabridge\\Debug\\Collectors\\EloquentCollector::class';

    /**
     * Update Toolbar.php to add EloquentCollector
     */
    public function setupToolbar(): void
    {
        $file = 'Config/Toolbar.php';
        $path = $this->distPath . $file;
        $cleanPath = clean_path($path);

        if (! $this->validateToolbarFile($path)) {
            return;
        }

        $content = file_get_contents($path);

        if ($this->isCollectorAlreadyAdded($content)) {
            return;
        }

        $newContent = $this->addCollectorToContent($content);

        $this->updateToolbarFile($path, $content, $newContent, $cleanPath);
    }

    private function validateToolbarFile(string $path): bool
    {
        if (! file_exists($path)) {
            $this->error('  Toolbar file not found. Make sure you have a Config/Toolbar.php file.');
            return false;
        }
        return true;
    }

    private function isCollectorAlreadyAdded(string $content): bool
    {
        if (strpos($content, self::COLLECTOR_CLASS) !== false) {
            $this->write(CLI::color('  Toolbar Setup: ', 'green') . 'EloquentCollector already added.');
            return true;
        }
        return false;
    }

    private function addCollectorToContent(string $content): string
    {
        $pattern = '/(public\s+array\s+\$collectors\s*=\s*\[)([^\]]*?)(\];)/s';
        $collectorCode = <<<'EOD'
$1$2
        Rcalicdan\Ci4Larabridge\Debug\Collectors\EloquentCollector::class,
$3
EOD;

        return preg_replace($pattern, $collectorCode, $content);
    }

    private function updateToolbarFile(string $path, string $content, string $newContent, string $cleanPath): void
    {
        if ($newContent !== $content && write_file($path, $newContent)) {
            $this->write(CLI::color('  Updated: ', 'green') . $cleanPath);
        } else {
            $this->error('  Error updating Toolbar file.');
        }
    }
}
