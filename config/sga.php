<?php

return [
    // Private directory outside the repository; credentials never belong in this file.
    'backup_directory' => dirname(base_path(), 2).DIRECTORY_SEPARATOR.'sga-backups',
    'mariadb_bin' => PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin' : '/usr/bin',
    'backup_timeout' => 300,
];
