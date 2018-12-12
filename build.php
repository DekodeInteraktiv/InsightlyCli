<?php

$phar_file = 'insightly-cli.phar';

// clean up
if ( file_exists( $phar_file ) ) {
	unlink( $phar_file );
}

if ( file_exists( $phar_file . '.gz' ) ) {
	unlink( $phar_file . '.gz' );
}

// create phar
$p = new Phar( $phar_file );
$p->setStub( '#!/usr/bin/env php' . "\n\n" . $p->createDefaultStub( 'insightly-cli.php' ) );


// creating our library using whole directory
$p->buildFromDirectory( 'app/' );

// plus - compressing it into gzip
$p->compress( Phar::GZ );

echo "$phar_file successfully created";

