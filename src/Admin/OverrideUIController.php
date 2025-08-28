<?php

declare(strict_types=1);

namespace SmartAlloc\Admin;

final class OverrideUIController {

	public function boot(): void {
		add_action( 'gform_entry_detail', array( $this, 'render' ), 10, 2 );
	}

	public function render( array $form, array $entry ): void {
		if ( ! current_user_can( SMARTALLOC_CAP ) ) {
			return;
		}
		$pluginFile = dirname( __DIR__, 2 ) . '/smart-alloc.php';
		wp_enqueue_script(
			'smartalloc-admin-override',
			plugins_url( 'assets/js/admin-override.js', $pluginFile ),
			array( 'wp-api-fetch' ),
			SMARTALLOC_VERSION,
			true
		);
		wp_localize_script(
			'smartalloc-admin-override',
			'SmartAllocOverride',
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'api'   => rest_url( 'smartalloc/v1/allocations/' . absint( $entry['id'] ) . '/override' ),
			)
		);
		include dirname( __DIR__, 2 ) . '/templates/admin/override-modal.php';
		echo '<button id="smartalloc-override-btn" class="button">' . esc_html__( 'Override Assignment', 'smartalloc' ) . '</button>';
	}
}
