<?php

declare(strict_types=1);

namespace SmartAlloc\Core;

/**
 * Main Plugin Class
 */
class Plugin
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        add_action('init', array( $this, 'loadTextDomain' ));
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'smartalloc',
            false,
            dirname(plugin_basename(__DIR__ . '/../smart-alloc.php')) . '/languages'
        );
    }
}
