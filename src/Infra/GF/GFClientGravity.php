<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use GFAPI;

class GFClientGravity implements GFClientInterface
{
    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameNotCamelCaps
    public function get_form(int $formId): ?array
    {
        /** @phpstan-ignore-next-line */
        $form = GFAPI::get_form($formId);
        return $form ? (array) $form : null;
    }
}
