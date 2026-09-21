<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
try {
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo "Boot OK\n";
} catch (\Throwable $e) {
    echo get_class($e).': '.$e->getMessage()."\n\n";
    foreach ($e->getTrace() as $i => $f) {
        printf("#%-2d %s(%s): %s%s%s()\n",
            $i,
            $f['file'] ?? '[internal]',
            $f['line'] ?? '0',
            $f['class'] ?? '',
            $f['type'] ?? '',
            $f['function'] ?? ''
        );
    }
}
