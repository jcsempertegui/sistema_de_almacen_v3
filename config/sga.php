<?php

return [
    // Private directory outside the repository; credentials never belong in this file.
    'backup_directory' => env('SGA_BACKUP_DIRECTORY', dirname(base_path(), 2).DIRECTORY_SEPARATOR.'sga-backups'),
    // Optional process variable; null discovers XAMPP on PHP's drive, then PATH.
    'mariadb_bin' => env('SGA_MARIADB_BIN'),
    'mariadb_version' => env('SGA_MARIADB_VERSION', '10.4'),
    'backup_timeout' => 300,
];
