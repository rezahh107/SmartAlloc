<?php
declare(strict_types=1);

namespace SmartAlloc\Config;

use SmartAlloc\Security\CapManager;

/**
 * Define public constants for external use.
 */
if (!defined('SMARTALLOC_CAP_MANAGE')) {
    define('SMARTALLOC_CAP_MANAGE', CapManager::NEW_CAP);
}

// NEW: feature flag for RuleEngine capacity check
if (!defined('SMARTALLOC_RULE_CAP_CHECK')) {
    define('SMARTALLOC_RULE_CAP_CHECK', filter_var(
        getenv('SMARTALLOC_RULE_CAP_CHECK') ?: 'false',
        FILTER_VALIDATE_BOOLEAN
    ));
}

