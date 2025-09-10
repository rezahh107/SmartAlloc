<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

interface GFClientInterface
{
    /**
     * Retrieves a form structure.
     *
     * @param int $formId The ID of the form to retrieve.
     * @return array|null The form array, or null if not found.
     */
    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameNotCamelCaps
    public function get_form(int $formId): ?array;
}
