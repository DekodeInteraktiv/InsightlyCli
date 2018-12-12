<?php

require( 'vendor/autoload.php' );
require( 'config.php' );
require( __DIR__ . '/includes/class-core.php' );
require( __DIR__ . '/includes/models/class-project.php' );
require( __DIR__ . '/includes/services/class-insightly-service.php' );

// Commands
require( __DIR__ . '/includes/commands/class-command.php' );
require( __DIR__ . '/includes/commands/class-find.php' );
require( __DIR__ . '/includes/commands/class-ssh.php' );
require( __DIR__ . '/includes/commands/class-clear-cache.php' );


$core = new \Dekode\InsightlyCli\Core( [
	new \Dekode\InsightlyCli\Commands\Find(),
	new \Dekode\InsightlyCli\Commands\SSH(),
	new \Dekode\InsightlyCli\Commands\ClearCache()
] );

if ( $argv[0] == 'php' ) {
	array_shift( $argv );
}

$core->set_command( $argv[1] );
$core->set_arguments( $argv );
$core->execute();

