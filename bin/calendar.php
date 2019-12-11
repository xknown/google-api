#!/usr/bin/env php
<?php
// Might need to be modified if we are using a phar version.
require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( php_sapi_name() !== 'cli' ) {
        exit;
}

function monitoring_show_error( $error ) {
        global $argv;
        echo $error . PHP_EOL . PHP_EOL;
        printf( "Usage:\n\tGOOGLE_APPLICATION_CREDENTIALS=/path/to/google/credentials.json %s user@automattic.com\n", $argv[0] );
        exit( 1 );
}

if ( empty( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) ) || ! is_file( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) ) ) {
        monitoring_show_error( "Empty Google credentials file" );
}
if ( empty( $argv[1] ) ) {
        monitoring_show_error( "Invalid email" );
}

$client = new SimpleGoogleClient( $argv[1] );

try {
	$calendarList = $client->get( '/calendar/v3/users/me/calendarList?minAccessRole=owner' );
	if ( empty( $calendarList->items ) ) {
		exit( 0 );
	}
	foreach ( $calendarList->items as $item ) {
		$path = sprintf( '/calendar/v3/calendars/%s/acl/default', $item->id );
		try {
			$acl = $client->get($path);
			if ( '__public_principal__@public.calendar.google.com' == $acl->scope->value ) {
				echo 'PUBLIC: ' . $item->id . PHP_EOL;
			}
		} catch (\Exception $ex) {
		}
	}
} catch( Exception $ex ) {
        fwrite( STDERR, $ex->getMessage() . PHP_EOL );
        exit( 1 );
}
