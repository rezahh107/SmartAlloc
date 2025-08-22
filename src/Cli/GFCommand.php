<?php

declare(strict_types=1);

namespace SmartAlloc\Cli;

use SmartAlloc\Infra\GF\GFFormGenerator;

final class GFCommand
{
    public function generate($args, $assoc_args): void
    {
        $output = $assoc_args['output'] ?? '';
        if ($output === '') {
            \WP_CLI::error('Please specify --output=path');
            return;
        }
        $json = GFFormGenerator::buildJson();
        file_put_contents($output, $json);
        \WP_CLI::success('Form template written to ' . $output);
    }
}
