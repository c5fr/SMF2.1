<?php
require_once( '../SSI.php' );
$context['page_title_html_safe'] = 'Page Externe';
$context['show_load_time']       = ! empty( $modSettings['timeLoadPageEnable'] );
//$scripturl = '/c7';
template_html_above();
template_body_above();
echo 'URL: '.$scripturl;
echo '<hr />Voici mon texte<hr />123';
/*
if ( $context['user']['is_guest'] ) {
	$context['txt'] = '<h5>xxx Access Denied</h5>We are sorry guest, it seems you dont have permission to view these downloads.';
	$context['txt'] = ssi_login();
} else {
	$context['txt'] = ssi_welcome();
	$context['txt'] = '<h5>Salut '. $context['user']['name']. ' !</h5>';
}
$context['txt'] = '<hr>';
if ( $context['allow_admin'] ) {
	$MemberGroup = "Admin";
} elseif ( $context['user']['is_guest'] ) {
	$MemberGroup = "User";
} else {
	$MemberGroup = "Guest";
}

// Echo's the membergroup of the person.
$context['txt'] = '<center><h5>Welcome ' . $MemberGroup . ' !</h5></center>';
$context['txt'] = '<hr>oOo 1234567<br>';
//require_once '../../Sources/Memberlist.php';
//$lm=Memberlist();
//var_dump($lm);
/**
 * Récupère ShowLoadTime
 */
/*
$srv = 'Oki';
$srv = $_SERVER['SERVER_NAME'];

$context['txt'] = '<hr>' . $srv;
*/
//$context['load_time'] = comma_format(round(array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)), 3));
//getShowLoadTime();
template_body_below();
template_html_below();

function getShowLoadTime() {
	global $context, $modSettings, $time_start, $db_count, $boardurl;
	$txt       = 'Oki';
	$scripturl = $boardurl;
	//var_dump($context, $txt, $scripturl, $modSettings);

	$context['show_load_time'] = ! empty( $modSettings['timeLoadPageEnable'] );
	$context['load_time']      = comma_format( round( array_sum( explode( ' ', microtime() ) ) - array_sum( explode( ' ',
	                                                                                                                 $time_start ) ),
	                                                  3 ) );
	$context['load_queries']   = $db_count;

	foreach ( array_reverse( $context['template_layers'] ) as $layer ) {
		loadSubTemplate( $layer . '_below', TRUE );
	}
}
