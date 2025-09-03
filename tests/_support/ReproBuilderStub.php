<?php
declare(strict_types=1);
namespace SmartAlloc\Debug;
class ReproBuilder {
    public function capture(): array { return []; }
    public function buildBundle(string $fingerprint): string {
        $path = tempnam(sys_get_temp_dir(), 'sa_repro');
        file_put_contents($path, 'zip');
        return $path;
    }
}
