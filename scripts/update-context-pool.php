<?php
/**
 * Context Pool Updater
 * Updates ci_runs[] and sends webhook notification
 */

$options = getopt( '', array( 'run-id:', 'status:', 'gates-json:', 'webhook:' ) );

$contextPath = 'wp-content/uploads/smartalloc/artifacts/context_pool.json';
$contextDir  = dirname( $contextPath );

// Ensure directory exists
if ( ! is_dir( $contextDir ) ) {
	mkdir( $contextDir, 0755, true );
}

// Load or create context pool
if ( file_exists( $contextPath ) ) {
	$context = json_decode( file_get_contents( $contextPath ), true );
} else {
	$context = array(
		'active_context' => array(
			'owner'       => 'Reza IT',
			'repo'        => getenv( 'GITHUB_REPOSITORY' ) ?: 'smartalloc',
			'branch'      => getenv( 'GITHUB_REF_NAME' ) ?: 'main',
			'utc_started' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'ci_runs'     => array(),
		),
	);
}

// Load gates report
$gates = array();
if ( isset( $options['gates-json'] ) && file_exists( $options['gates-json'] ) ) {
	$gates = json_decode( file_get_contents( $options['gates-json'] ), true );
}

// Add new CI run
$newRun = array(
	'run_id'         => $options['run-id'] ?? uniqid( 'run_' ),
	'timestamp'      => gmdate( 'Y-m-d\TH:i:s\Z' ),
	'status'         => $options['status'] ?? 'unknown',
	'gates'          => $gates['gates'] ?? array(),
	'overall_status' => $gates['overall_status'] ?? 'PENDING',
);

$context['active_context']['ci_runs'][] = $newRun;

// Keep only last 50 runs
if ( count( $context['active_context']['ci_runs'] ) > 50 ) {
	$context['active_context']['ci_runs'] = array_slice( $context['active_context']['ci_runs'], -50 );
}

// Save updated context
file_put_contents( $contextPath, json_encode( $context, JSON_PRETTY_PRINT ) );

// Send webhook notification if URL provided
if ( isset( $options['webhook'] ) && ! empty( $options['webhook'] ) ) {
	$ch = curl_init( $options['webhook'] );
	curl_setopt( $ch, CURLOPT_POST, 1 );
	curl_setopt(
		$ch,
		CURLOPT_POSTFIELDS,
		json_encode(
			array(
				'event'   => 'ci_run_complete',
				'payload' => $newRun,
			)
		)
	);
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );

	$response = curl_exec( $ch );
	$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Webhook notification sent: HTTP $httpCode\n";
}

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Context pool updated successfully\n";
