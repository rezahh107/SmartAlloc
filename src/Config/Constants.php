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

