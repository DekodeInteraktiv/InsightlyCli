<?php

define( 'APP_VERSION', '2.6.0' );

require( 'vendor/autoload.php' );
require( 'config.php' );
require( __DIR__ . '/includes/class-core.php' );

// Services
require( __DIR__ . '/includes/services/class-operating-system-service.php' );
require( __DIR__ . '/includes/services/class-dekodemon-service.php' );

// Operating systems
require( __DIR__ . '/includes/operating-systems/class-operating-system.php' );
require( __DIR__ . '/includes/operating-systems/class-linux.php' );
require( __DIR__ . '/includes/operating-systems/class-mac.php' );

// Commands
require( __DIR__ . '/includes/commands/class-command.php' );
require( __DIR__ . '/includes/commands/class-access-db.php' );
require( __DIR__ . '/includes/commands/class-browse.php' );
require( __DIR__ . '/includes/commands/class-dekodemon-activate.php' );
require( __DIR__ . '/includes/commands/class-dekodemon-sanity-check.php' );
require( __DIR__ . '/includes/commands/class-dump-db.php' );
require( __DIR__ . '/includes/commands/class-find.php' );
require( __DIR__ . '/includes/commands/class-guess.php' );
require( __DIR__ . '/includes/commands/class-info.php' );
require( __DIR__ . '/includes/commands/class-ls.php' );
require( __DIR__ . '/includes/commands/class-check-load.php' );
require( __DIR__ . '/includes/commands/class-rebuild-cache.php' );
require( __DIR__ . '/includes/commands/class-sanity-check.php' );
require( __DIR__ . '/includes/commands/class-ssh.php' );
require( __DIR__ . '/includes/commands/class-update.php' );
require( __DIR__ . '/includes/commands/class-users.php' );
require( __DIR__ . '/includes/commands/class-list-db-servers.php' );


$core = new \Dekode\InsightlyCli\Core( [
	new \Dekode\InsightlyCli\Commands\Info(),
	new \Dekode\InsightlyCli\Commands\Find(),
	new \Dekode\InsightlyCli\Commands\SSH(),
	new \Dekode\InsightlyCli\Commands\ClearCache(),
	new \Dekode\InsightlyCli\Commands\Update(),
	new \Dekode\InsightlyCli\Commands\Browse(),
	new \Dekode\InsightlyCli\Commands\Guess(),
	new \Dekode\InsightlyCli\Commands\DumpDB(),
	new \Dekode\InsightlyCli\Commands\AccessDb(),
	new \Dekode\InsightlyCli\Commands\SanityCheck(),
	//new \Dekode\InsightlyCli\Commands\DekodemonActivate(),
	new \Dekode\InsightlyCli\Commands\DekodemonSanityCheck(),
	new \Dekode\InsightlyCli\Commands\Users(),
	new \Dekode\InsightlyCli\Commands\CheckLoad(),
	new \Dekode\InsightlyCli\Commands\Ls(),
	new \Dekode\InsightlyCli\Commands\ListDbServers()
] );

if ( $argv[0] == 'php' ) {
	array_shift( $argv );
}

if ( isset( $argv[1] ) ) {
	$core->set_command( $argv[1] );
}

$core->set_arguments( $argv );
$core->execute();

