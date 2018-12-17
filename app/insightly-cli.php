<?php

require( 'vendor/autoload.php' );
require( 'config.php' );
require( __DIR__ . '/includes/class-core.php' );

// Models
require( __DIR__ . '/includes/models/class-project.php' );
require( __DIR__ . '/includes/models/class-server.php' );
require( __DIR__ . '/includes/models/class-digital-ocean-droplet.php' );
require( __DIR__ . '/includes/models/class-rackspace-server.php' );
require( __DIR__ . '/includes/models/class-rackspace-load-balancer.php' );

// Services
require( __DIR__ . '/includes/services/class-insightly-service.php' );
require( __DIR__ . '/includes/services/class-digital-ocean-service.php' );
require( __DIR__ . '/includes/services/class-rackspace-service.php' );
require( __DIR__ . '/includes/services/class-operating-system-service.php' );

// Operating systems
require( __DIR__ . '/includes/operating-systems/class-operating-system.php' );
require( __DIR__ . '/includes/operating-systems/class-linux.php' );

// Commands
require( __DIR__ . '/includes/commands/class-command.php' );
require( __DIR__ . '/includes/commands/class-find.php' );
require( __DIR__ . '/includes/commands/class-ssh.php' );
require( __DIR__ . '/includes/commands/class-rebuild-cache.php' );
require( __DIR__ . '/includes/commands/class-update.php' );
require( __DIR__ . '/includes/commands/class-browse.php' );
require( __DIR__ . '/includes/commands/class-sync.php' );


$core = new \Dekode\InsightlyCli\Core( [
	new \Dekode\InsightlyCli\Commands\Find(),
	new \Dekode\InsightlyCli\Commands\SSH(),
	new \Dekode\InsightlyCli\Commands\ClearCache(),
	new \Dekode\InsightlyCli\Commands\Update(),
	new \Dekode\InsightlyCli\Commands\Browse(),
	new \Dekode\InsightlyCli\Commands\Sync()
] );

if ( $argv[0] == 'php' ) {
	array_shift( $argv );
}

if ( isset( $argv[1] ) ) {
	$core->set_command( $argv[1] );
}

$core->set_arguments( $argv );
$core->execute();

