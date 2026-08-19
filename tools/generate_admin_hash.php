<?php
/**
 * Generate the admin password hash.
 *
 * Run from the project root:
 *
 * php tools/generate_admin_hash.php 'YourStrongPassword'
 *
 * Copy the output into .env:
 *
 * APP_ADMIN_PASSWORD_HASH=...
 *
 * Delete this file from a production server after use.
 */
$password = $argv[1] ?? null;

if (!$password) {
    fwrite(STDERR, "Usage: php tools/generate_admin_hash.php 'YourStrongPassword'\n");
    exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
