<?php

/**
 * This, as you have probably guessed, is the crux on which SMF functions.
 * Everything should start here, so all the setup and security is done
 * properly.  The most interesting part of this file is the action array in
 * the smf_main() function.  It is formatted as so:
 *  'action-in-url' => array('Source-File.php', 'FunctionToCall'),
 *
 * Then, you can access the FunctionToCall() function from Source-File.php
 * with the URL index.php?action=action-in-url.  Relatively simple, no?
 *
 * Simple Machines Forum (SMF)
 *
 * @package   SMF
 * @author    Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license   http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version   2.1 Beta 2
 */

$forum_version = 'SMF 2.1 Beta 2';
$software_year = '2015';

// Get everything started up...
define( 'SMF', 1 );
if ( function_exists( 'set_magic_quotes_runtime' ) ) {
	@set_magic_quotes_runtime( 0 );
}
error_reporting( defined( 'E_STRICT' ) ? E_ALL | E_STRICT : E_ALL );
$time_start = microtime();

// This makes it so headers can be sent!
ob_start();

// Do some cleaning, just in case.
foreach ( [ 'db_character_set', 'cachedir' ] as $variable ) {
	if ( isset( $GLOBALS[ $variable ] ) ) {
		unset( $GLOBALS[ $variable ], $GLOBALS[ $variable ] );
	}
}

// Load the settings...
require_once( dirname( __FILE__ ) . '/c7/Settings.php' );

// Make absolutely sure the cache directory is defined.
if ( ( empty( $cachedir ) || ! file_exists( $cachedir ) ) && file_exists( $boarddir . '/cache' ) ) {
	$cachedir = $boarddir . '/cache';
}

// Without those we can't go anywhere
require_once( $sourcedir . '/QueryString.php' );
require_once( $sourcedir . '/Subs.php' );
require_once( $sourcedir . '/Subs-Auth.php' );
require_once( $sourcedir . '/Errors.php' );
require_once( $sourcedir . '/Load.php' );

// If $maintenance is set specifically to 2, then we're upgrading or something.
if ( ! empty( $maintenance ) && $maintenance == 2 ) {
	display_maintenance_message();
}

// Create a variable to store some SMF specific functions in.
$smcFunc = [ ];

// Initiate the database connection and define some database functions to use.
loadDatabase();

// Load the settings from the settings table, and perform operations like optimizing.
$context = [ ];
reloadSettings();
// Clean the request variables, add slashes, etc.
cleanRequest();

// Seed the random generator.
if ( empty( $modSettings['rand_seed'] ) || mt_rand( 1, 250 ) == 69 ) {
	smf_seed_generator();
}

// Before we get carried away, are we doing a scheduled task? If so save CPU cycles by jumping out!
if ( isset( $_GET['scheduled'] ) ) {
	require_once( $sourcedir . '/ScheduledTasks.php' );
	AutoTask();
} // Displaying attached avatars, legacy.
elseif ( isset( $_GET['action'] ) && $_GET['action'] == 'dlattach' && isset( $_GET['type'] ) && $_GET['type'] == 'avatar' ) {
	require_once( $sourcedir . '/Avatar.php' );
	showAvatar();
}

// And important includes.
require_once( $sourcedir . '/Session.php' );
require_once( $sourcedir . '/Errors.php' );
require_once( $sourcedir . '/Logging.php' );
require_once( $sourcedir . '/Security.php' );
require_once( $sourcedir . '/Class-BrowserDetect.php' );

// Check if compressed output is enabled, supported, and not already being done.
if ( ! empty( $modSettings['enableCompressedOutput'] ) && ! headers_sent() ) {
	// If zlib is being used, turn off output compression.
	if ( ini_get( 'zlib.output_compression' ) >= 1 || ini_get( 'output_handler' ) == 'ob_gzhandler' ) {
		$modSettings['enableCompressedOutput'] = '0';
	} else {
		ob_end_clean();
		ob_start( 'ob_gzhandler' );
	}
}

// Register an error handler.
set_error_handler( 'error_handler' );

// Start the session. (assuming it hasn't already been.)
loadSession();

// What function shall we execute? (done like this for memory's sake.)
call_user_func( smf_main() );

// Call obExit specially; we're coming from the main area ;).
obExit( null, null, TRUE );

/**
 * The main dispatcher.
 * This delegates to each area.
 * @return array|string|void An array containing the file to include and name of function to call, the name of a
 *                           function to call or dies with a fatal_lang_error if we couldn't find anything to do.
 */
function smf_main() {
	global $modSettings, $settings, $user_info, $board, $topic;
	global $board_info, $maintenance, $sourcedir;

	// Special case: session keep-alive, output a transparent pixel.
	if ( isset( $_GET['action'] ) && $_GET['action'] == 'keepalive' ) {
		header( 'Content-Type: image/gif' );
		die( "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B" );
	}

	// We should set our security headers now.
	frameOptionsHeader();

	// Load the user's cookie (or set as guest) and load their settings.
	loadUserSettings();

	// Load the current board's information.
	loadBoard();

	// Load the current user's permissions.
	loadPermissions();

	// Attachments don't require the entire theme to be loaded.
	if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'dlattach' ) {
		detectBrowser();
	} // Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
	else {
		loadTheme();
	}

	// Check if the user should be disallowed access.
	is_not_banned();

	// If we are in a topic and don't have permission to approve it then duck out now.
	if ( ! empty( $topic ) && empty( $board_info['cur_topic_approved'] ) && ! allowedTo( 'approve_posts' ) && ( $user_info['id'] != $board_info['cur_topic_starter'] || $user_info['is_guest'] ) ) {
		fatal_lang_error( 'not_a_topic', FALSE );
	}

	$no_stat_actions = [
		'clock',
		'dlattach',
		'findmember',
		'jsoption',
		'likes',
		'loadeditorlocale',
		'modifycat',
		'requestmembers',
		'smstats',
		'suggest',
		'about:unknown',
		'.xml',
		'xmlhttp',
		'verificationcode',
		'viewquery',
		'viewsmfile'
	];
	call_integration_hook( 'integrate_pre_log_stats', [ &$no_stat_actions ] );
	// Do some logging, unless this is an attachment, avatar, toggle of editor buttons, theme option, XML feed etc.
	if ( empty( $_REQUEST['action'] ) || ! in_array( $_REQUEST['action'], $no_stat_actions ) ) {
		// Log this user as online.
		writeLog();

		// Track forum statistics and hits...?
		if ( ! empty( $modSettings['hitStats'] ) ) {
			trackStats( [ 'hits' => '+' ] );
		}
	}
	unset( $no_stat_actions );

	// Is the forum in maintenance mode? (doesn't apply to administrators.)
	if ( ! empty( $maintenance ) && ! allowedTo( 'admin_forum' ) ) {
		// You can only login.... otherwise, you're getting the "maintenance mode" display.
		if ( isset( $_REQUEST['action'] ) && ( $_REQUEST['action'] == 'login2' || $_REQUEST['action'] == 'logout' ) ) {
			require_once( $sourcedir . '/LogInOut.php' );

			return $_REQUEST['action'] == 'login2' ? 'Login2' : 'Logout';
		} // Don't even try it, sonny.
		else {
			return 'InMaintenance';
		}
	} // If guest access is off, a guest can only do one of the very few following actions.
	elseif ( empty( $modSettings['allow_guestAccess'] ) && $user_info['is_guest'] && ( ! isset( $_REQUEST['action'] ) || ! in_array( $_REQUEST['action'],
	                                                                                                                                 [
		                                                                                                                                 'coppa',
		                                                                                                                                 'login',
		                                                                                                                                 'login2',
		                                                                                                                                 'reminder',
		                                                                                                                                 'activate',
		                                                                                                                                 'help',
		                                                                                                                                 'helpadmin',
		                                                                                                                                 'smstats',
		                                                                                                                                 'verificationcode',
		                                                                                                                                 'signup',
		                                                                                                                                 'signup2'
	                                                                                                                                 ] ) )
	) {
		return 'KickGuest';
	} elseif ( empty( $_REQUEST['action'] ) ) {
		// Action and board are both empty... BoardIndex! Unless someone else wants to do something different.
		if ( empty( $board ) && empty( $topic ) ) {
			$defaultAction = FALSE;

			if ( ! empty( $modSettings['integrate_default_action'] ) ) {
				$defaultAction = explode( ',', $modSettings['integrate_default_action'] );

				// Sorry, only one default action is needed.
				$defaultAction = $defaultAction[0];

				$call = call_helper( $defaultAction, TRUE );

				if ( ! empty( $call ) ) {
					return $call;
				}
			} // No default action huh? then go to our good old BoardIndex.
			else {
				require_once( $sourcedir . '/BoardIndex.php' );

				return 'BoardIndex';
			}
		} // Topic is empty, and action is empty.... MessageIndex!
		elseif ( empty( $topic ) ) {
			require_once( $sourcedir . '/MessageIndex.php' );

			return 'MessageIndex';
		} // Board is not empty... topic is not empty... action is empty.. Display!
		else {
			require_once( $sourcedir . '/Display.php' );

			return 'Display';
		}
	}

	// Here's the monstrous $_REQUEST['action'] array - $_REQUEST['action'] => array($file, $function).
	$actionArray = [
		'activate'         => [ 'Register.php', 'Activate' ],
		'admin'            => [ 'Admin.php', 'AdminMain' ],
		'announce'         => [ 'Post.php', 'AnnounceTopic' ],
		'attachapprove'    => [ 'ManageAttachments.php', 'ApproveAttach' ],
		'buddy'            => [ 'Subs-Members.php', 'BuddyListToggle' ],
		'calendar'         => [ 'Calendar.php', 'CalendarMain' ],
		'clock'            => [ 'Calendar.php', 'clock' ],
		'coppa'            => [ 'Register.php', 'CoppaForm' ],
		'credits'          => [ 'Who.php', 'Credits' ],
		'deletemsg'        => [ 'RemoveTopic.php', 'DeleteMessage' ],
		'dlattach'         => [ 'Display.php', 'Download' ],
		'editpoll'         => [ 'Poll.php', 'EditPoll' ],
		'editpoll2'        => [ 'Poll.php', 'EditPoll2' ],
		'findmember'       => [ 'Subs-Auth.php', 'JSMembers' ],
		'groups'           => [ 'Groups.php', 'Groups' ],
		'help'             => [ 'Help.php', 'ShowHelp' ],
		'helpadmin'        => [ 'Help.php', 'ShowAdminHelp' ],
		'jsmodify'         => [ 'Post.php', 'JavaScriptModify' ],
		'jsoption'         => [ 'Themes.php', 'SetJavaScript' ],
		'likes'            => [ 'Likes.php', 'Likes::call#' ],
		'loadeditorlocale' => [ 'Subs-Editor.php', 'loadLocale' ],
		'lock'             => [ 'Topic.php', 'LockTopic' ],
		'lockvoting'       => [ 'Poll.php', 'LockVoting' ],
		'login'            => [ 'LogInOut.php', 'Login' ],
		'login2'           => [ 'LogInOut.php', 'Login2' ],
		'logintfa'         => [ 'LogInOut.php', 'LoginTFA' ],
		'logout'           => [ 'LogInOut.php', 'Logout' ],
		'markasread'       => [ 'Subs-Boards.php', 'MarkRead' ],
		'mergetopics'      => [ 'SplitTopics.php', 'MergeTopics' ],
		'mlist'            => [ 'Memberlist.php', 'Memberlist' ],
		'moderate'         => [ 'ModerationCenter.php', 'ModerationMain' ],
		'modifycat'        => [ 'ManageBoards.php', 'ModifyCat' ],
		'movetopic'        => [ 'MoveTopic.php', 'MoveTopic' ],
		'movetopic2'       => [ 'MoveTopic.php', 'MoveTopic2' ],
		'notify'           => [ 'Notify.php', 'Notify' ],
		'notifyboard'      => [ 'Notify.php', 'BoardNotify' ],
		'notifytopic'      => [ 'Notify.php', 'TopicNotify' ],
		'pm'               => [ 'PersonalMessage.php', 'MessageMain' ],
		'post'             => [ 'Post.php', 'Post' ],
		'post2'            => [ 'Post.php', 'Post2' ],
		'printpage'        => [ 'Printpage.php', 'PrintTopic' ],
		'profile'          => [ 'Profile.php', 'ModifyProfile' ],
		'quotefast'        => [ 'Post.php', 'QuoteFast' ],
		'quickmod'         => [ 'MessageIndex.php', 'QuickModeration' ],
		'quickmod2'        => [ 'Display.php', 'QuickInTopicModeration' ],
		'recent'           => [ 'Recent.php', 'RecentPosts' ],
		'reminder'         => [ 'Reminder.php', 'RemindMe' ],
		'removepoll'       => [ 'Poll.php', 'RemovePoll' ],
		'removetopic2'     => [ 'RemoveTopic.php', 'RemoveTopic2' ],
		'reporttm'         => [ 'ReportToMod.php', 'ReportToModerator' ],
		'requestmembers'   => [ 'Subs-Auth.php', 'RequestMembers' ],
		'restoretopic'     => [ 'RemoveTopic.php', 'RestoreTopic' ],
		'search'           => [ 'Search.php', 'PlushSearch1' ],
		'search2'          => [ 'Search.php', 'PlushSearch2' ],
		'sendactivation'   => [ 'Register.php', 'SendActivation' ],
		'signup'           => [ 'Register.php', 'Register' ],
		'signup2'          => [ 'Register.php', 'Register2' ],
		'smstats'          => [ 'Stats.php', 'SMStats' ],
		'suggest'          => [ 'Subs-Editor.php', 'AutoSuggestHandler' ],
		'spellcheck'       => [ 'Subs-Post.php', 'SpellCheck' ],
		'splittopics'      => [ 'SplitTopics.php', 'SplitTopics' ],
		'stats'            => [ 'Stats.php', 'DisplayStats' ],
		'sticky'           => [ 'Topic.php', 'Sticky' ],
		'theme'            => [ 'Themes.php', 'ThemesMain' ],
		'trackip'          => [ 'Profile-View.php', 'trackIP' ],
		'about:unknown'    => [ 'Likes.php', 'BookOfUnknown' ],
		'unread'           => [ 'Recent.php', 'UnreadTopics' ],
		'unreadreplies'    => [ 'Recent.php', 'UnreadTopics' ],
		'verificationcode' => [ 'Register.php', 'VerificationCode' ],
		'viewprofile'      => [ 'Profile.php', 'ModifyProfile' ],
		'vote'             => [ 'Poll.php', 'Vote' ],
		'viewquery'        => [ 'ViewQuery.php', 'ViewQuery' ],
		'viewsmfile'       => [ 'Admin.php', 'DisplayAdminFile' ],
		'who'              => [ 'Who.php', 'Who' ],
		'.xml'             => [ 'News.php', 'ShowXmlFeed' ],
		'xmlhttp'          => [ 'Xml.php', 'XMLhttpMain' ],
	];

	// Allow modifying $actionArray easily.
	call_integration_hook( 'integrate_actions', [ &$actionArray ] );

	// Get the function and file to include - if it's not there, do the board index.
	if ( ! isset( $_REQUEST['action'] ) || ! isset( $actionArray[ $_REQUEST['action'] ] ) ) {
		// Catch the action with the theme?
		if ( ! empty( $settings['catch_action'] ) ) {
			require_once( $sourcedir . '/Themes.php' );

			return 'WrapAction';
		}

		if ( ! empty( $modSettings['integrate_fallback_action'] ) ) {
			$fallbackAction = explode( ',', $modSettings['integrate_fallback_action'] );

			// Sorry, only one fallback action is needed.
			$fallbackAction = $fallbackAction[0];

			$call = call_helper( $fallbackAction, TRUE );

			if ( ! empty( $call ) ) {
				return $call;
			}
		} // No fallback action, huh?
		else {
			fatal_lang_error( 'not_found', FALSE, [ ], 404 );
		}
	}

	// Otherwise, it was set - so let's go to that action.
	require_once( $sourcedir . '/' . $actionArray[ $_REQUEST['action'] ][0] );

	// Do the right thing.
	return call_helper( $actionArray[ $_REQUEST['action'] ][1], TRUE );
}

?>