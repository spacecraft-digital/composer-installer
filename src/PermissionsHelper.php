<?php

namespace Jadu\Composer;

class PermissionsHelper
{

    protected $permissionsFilePath;

    /**
     * @param string $permissionsFilePath /path/to/config/permissions/custom
     */
    public function __construct($permissionsFilePath)
    {
        $this->permissionsFilePath = $permissionsFilePath;
    }

    protected function mergePermissions($p1, $p2)
    {
        $permissions = array_merge(str_split($p1), str_split($p2));
        $permissions = array_unique($permissions);
        sort($permissions);
        return implode('', $permissions);
    }

    /**
     * Adds each of the file paths in the given array's keys to the permissions file,
     * with the permissions specified in the array member value.
     *
     * File paths should be relative to the project root.
     *
     * If a file is already listed in the permissions file, the permission will be merged.
     *
     * array(
     *   'public_html/jadu/custom' => 'rR',
     *   'vendor' => 'x',
     *   'vendor/jadu' => 'x',
     *   'vendor/jadu/widget-factory' => 'x',
     * )
     *
     * @param array[string]string $newFilePermissions
     */
    public function addFiles($newFilePermissions)
    {
        $addedCount = 0;
        $filePermissions = $this->read();
        if ($filePermissions === false) {
            throw new RuntimeException("Error loading existing permissions file", 1);
        }
        foreach ($newFilePermissions as $file => $permissions) {
            $addedCount++;
            foreach ($filePermissions as $existingFile => &$existingPermissions) {
                if ($existingFile == $file) {
                    if ($existingPermissions != $permissions) {
                        // if the file is already there, merge the permissions
                        $existingPermissions = $this->mergePermissions($existingPermissions, $permissions);
                    }
                    continue 2;
                }
            }
            $filePermissions[$file] = $permissions;
        }

        $this->write($filePermissions);

        return $addedCount;
    }

    protected function read()
    {
        $lines = array();
        if (file_exists($this->permissionsFilePath)) {
            foreach (file($this->permissionsFilePath, \FILE_IGNORE_NEW_LINES) as $line) {
                if (preg_match('/^(.+?)[ \t]+\[([rwxR]+)\][ \t]*$/', $line, $m)) {
                    list(,$file,$permissions) = $m;
                    if (!empty($file)) {
                        if (isset($lines[$file])) {
                            $lines[$file] = $this->mergePermissions($lines[$file], $permissions);
                        } else {
                            $lines[$file] = $permissions;
                        }
                    }
                }
            }
        }
        return $lines;
    }

    protected function write($lines)
    {
        // ensure the folder exists
        $permissionsFolder = dirname($this->permissionsFilePath);
        if (!is_dir($permissionsFolder)) {
            mkdir($permissionsFolder, 0755, true);
        }

        if (empty($lines)) {
            return;
        }

        $file = fopen($this->permissionsFilePath, 'w');
        foreach ($lines as $filename => $permissions) {
            $line = sprintf(
                '%s [%s]',
                $filename,
                $permissions
            );
            fputs($file, $line . \PHP_EOL);
        }
        // ensure trailing newline
        fputs($file, \PHP_EOL);
        fclose($file);
    }
}
