<?php

require( 'vendor/autoload.php' );
require_all( __DIR__ . '/src' );
require( 'config.php' );

$core = new \Dekode\InsightlyCli\Core();
$core->set_command( $argv[1] );
$core->set_arguments( $argv );
$core->execute();

/**
 * Loops through a given directory and includes all php-files in it.
 *
 * @param     $dir
 * @param int $depth
 */
function require_all( $dir, $depth = 0 ) {
	$scan = glob( "$dir/*" );
	foreach ( $scan as $path ) {
		if ( preg_match( '/\.php$/', $path ) ) {
			require_once $path;
		} elseif ( is_dir( $path ) ) {
			require_all( $path, $depth + 1 );
		}
	}
}