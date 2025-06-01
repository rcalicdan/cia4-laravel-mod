<?php

namespace Rcalicdan\Ci4Larabridge\Traits;

use CodeIgniter\CLI\CLI;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

trait FilePublisherTrait
{
    /**
     * Publishes files from source to destination directory
     *
     * @param string $sourcePath Source directory path
     * @param string $destinationPath Destination directory path
     * @return bool Success status
     */
    protected function publishFiles(string $sourcePath, string $destinationPath): bool
    {
        if (!$this->validateSourceDirectory($sourcePath)) {
            return false;
        }

        if (!$this->ensureDestinationDirectory($destinationPath)) {
            return false;
        }

        return $this->copyFilesRecursively($sourcePath, $destinationPath);
    }

    /**
     * Validates that source directory exists
     *
     * @param string $sourcePath
     * @return bool
     */
    private function validateSourceDirectory(string $sourcePath): bool
    {
        if (is_dir($sourcePath)) {
            return true;
        }

        CLI::error("Source directory not found: {$sourcePath}");
        return false;
    }

    /**
     * Ensures destination directory exists, creates if necessary
     *
     * @param string $destinationPath
     * @return bool
     */
    private function ensureDestinationDirectory(string $destinationPath): bool
    {
        if (is_dir($destinationPath)) {
            return true;
        }

        if (mkdir($destinationPath, 0755, true)) {
            return true;
        }

        CLI::error("Failed to create destination directory: {$destinationPath}");
        return false;
    }

    /**
     * Copies files recursively using SPL iterators (more efficient and cleaner)
     *
     * @param string $source
     * @param string $destination
     * @return bool
     */
    private function copyFilesRecursively(string $source, string $destination): bool
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $relativePath = $iterator->getSubPathName();
                $destinationItem = $destination . DIRECTORY_SEPARATOR . $relativePath;

                if ($item->isDir()) {
                    $this->createDirectoryIfNotExists($destinationItem);
                } else {
                    $this->copyFile($item->getPathname(), $destinationItem, $item->getFilename());
                }
            }

            return true;
        } catch (\Exception $e) {
            CLI::error("Error during file copying: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Creates directory if it doesn't exist
     *
     * @param string $directory
     * @return void
     */
    private function createDirectoryIfNotExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Copies a single file and provides feedback
     *
     * @param string $source
     * @param string $destination
     * @param string $filename
     * @return void
     */
    private function copyFile(string $source, string $destination, string $filename): void
    {
        if (copy($source, $destination)) {
            CLI::write("Copied: {$filename}", 'yellow');
        } else {
            CLI::error("Failed to copy: {$filename}");
        }
    }
}