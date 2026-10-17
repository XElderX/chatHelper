<?php

// Preserve existing encryption keys when setup is rerun on an installed workspace.
$env = file_get_contents(__DIR__.'/../.env');
if (preg_match('/^APP_KEY=(.+)$/m', $env, $match) && trim($match[1], " \"'\r\n") !== '') {
    echo "Existing APP_KEY preserved.\n";
    exit(0);
}
passthru(escapeshellarg(PHP_BINARY).' artisan key:generate', $status);
exit($status);
