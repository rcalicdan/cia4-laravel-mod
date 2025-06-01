<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;

class SystemHandler extends SetupHandler
{
    /**
     * Update Events.php to add Eloquent and authorization services initialization
     */
    public function setupEvents(): void
    {
        $file = 'Config/Events.php';
        $path = $this->distPath.$file;
        $cleanPath = clean_path($path);

        if (! file_exists($path)) {
            $this->error('  Events file not found. Make sure you have a Config/Events.php file.');

            return;
        }

        $content = file_get_contents($path);

        if (strpos($content, "service('eloquent')") !== false) {
            $this->write(CLI::color('  Events Setup: ', 'green').'Eloquent already initialized in events.');

            return;
        }

        if (
            ! $this->skipConfirmations &&
            $this->prompt("  Ready to update '{$cleanPath}' to initialize Eloquent and auth services. Continue?", ['y', 'n']) === 'n'
        ) {
            $this->error("  Skipped updating {$cleanPath}.");

            return;
        }

        $pattern = '/(Events::on\(\'pre_system\',)/';
        $eloquentCode = <<<'EOD'
Events::on('pre_system', static function (): void {
    // Load the Eloquent configuration
    service('eloquent');
    // Load the authentication configuration
    service('authorization');
});

$1
EOD;

        $newContent = preg_replace($pattern, $eloquentCode, $content);

        if ($newContent !== $content && write_file($path, $newContent)) {
            $this->write(CLI::color('  Updated: ', 'green').$cleanPath);
        } else {
            $this->error('  Error updating Events file.');
        }
    }

    /**
     * Update Filters.php to add auth and guest filter aliases
     */
    public function setupFilters(): void
    {
        $file = 'Config/Filters.php';
        $path = $this->distPath.$file;
        $cleanPath = clean_path($path);

        if (! file_exists($path)) {
            $this->error('  Filters file not found. Make sure you have a Config/Filters.php file.');

            return;
        }

        $content = file_get_contents($path);

        // Check if the code is already there
        if (strpos($content, '\\Rcalicdan\\Ci4Larabridge\\Filters\\AuthFilter::class') !== false) {
            $this->write(CLI::color('  Filters Setup: ', 'green').'Auth filters already added.');

            return;
        }

        // Find the aliases array
        $pattern = '/(public\s+array\s+\$aliases\s*=\s*\[)([^\]]*?)(\];)/s';
        $filterAliases = <<<'EOD'
$1$2
        'auth'     => \Rcalicdan\Ci4Larabridge\Filters\AuthFilter::class,
        'guest'    => \Rcalicdan\Ci4Larabridge\Filters\GuestFilter::class,
        'throttle' => \Rcalicdan\Ci4Larabridge\Filters\ThrottleFilter::class,
        'email_verified' => \Rcalicdan\Ci4Larabridge\Filters\EmailVerifiedFilter::class,
$3
EOD;

        $newContent = preg_replace($pattern, $filterAliases, $content);

        if ($newContent !== $content && write_file($path, $newContent)) {
            $this->write(CLI::color('  Updated: ', 'green').$cleanPath);
        } else {
            $this->error('  Error updating Filters file.');
        }
    }
}
