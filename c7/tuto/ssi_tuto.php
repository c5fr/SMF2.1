<?php
/**
 * Created by C7.
 * User: Li
 * Date: 12/08/2015
 * Time: 21:10
 */

require( "../../SSI.php" );
if ( $context['user']['is_guest'] ) {
	echo
	'<h5>xxx Access Denied</h5>
   We are sorry guest, it seems you dont have permission to view these downloads.';
	ssi_login();
} else {
	ssi_welcome();
	echo
	'<h5>Salut ', $context['user']['name'], ' !</h5>';
}
echo '<hr>';
if ($context['allow_admin'])
{
	$MemberGroup = "Admin";
}
elseif ($context['user']['is_guest'])
{
	$MemberGroup = "User";
}
else
{
	$MemberGroup = "Guest";
}

// Echo's the membergroup of the person.
echo '<center><h5>Welcome '.$MemberGroup.' !</h5></center>';

echo '<hr>oOo 1234567<br>';
//require_once '../../Sources/Memberlist.php';
//$lm=Memberlist();
//var_dump($lm);


$srv = 'Oki';
$srv = $_SERVER['SERVER_NAME'];
echo '<hr>' . $srv;