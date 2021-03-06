<?php
/**
 * Fix Packages (fixp)
 *
 * @package   fixp
 * @author    emanuele
 * @copyright 2011 emanuele, Simple Machines
 * @license   http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version   0.1
 */
// A nice menu button
define( 'SMF_INTEGRATION_SETTINGS', serialize( [
	                                               'integrate_menu_buttons' => 'c7_menu_button',
                                               ] ) );
// Let's use the default theme
$ssi_theme     = 1;
$forum_version = 'c7 Page 0.1';
// If SSI.php is in the same place as this file, this is being run standalone.
if ( file_exists( dirname( __FILE__ ) . '/SSI.php' ) ) {
	require_once( dirname( __FILE__ ) . '/SSI.php' );
} elseif ( file_exists( '../SSI.php' ) ) {
	require_once( '../SSI.php' );
} // Hmm... no SSI.php?
else {
	die( '<b>Error:</b> Cannot find SSI - please verify you put this in the same place as SMF\'s index.php.' );
}

/**
 * Change false to true if you want to avoid the admin check.
 */
$context['override_security'] = FALSE;


/**
 *
 * Do you want to add a new language?
 * Copy the following function,
 * change 'english' to the language you want
 * and translate it. ;D
 *
 */
function c7_english() {
	global $txt;
	$txt['c7menu']                       = 'c7';
	$txt['log_packages_title_installed'] = 'Packages installés';
	$txt['log_packages_title_removed']   = 'Uninstalled packages';
	$txt['pack_button_remove']           = 'Mark uninstalled selected';
	$txt['pack_button_install']          = 'Mark installed selected';
	$txt['remove_hooks']                 = 'Remove all hooks';
	$txt['uninstall']                    = 'Show uninstalled';
	$txt['install']                      = 'Show installed';
	$txt['mod_installed']                = 'Install date';
	$txt['mod_removed']                  = 'Uninstall date';
}


// Do not change anything below this line
// ------------------------------------------------------------------------------------------------

// Let's start the main job
c7_main();
// and then let's throw out the template! :P
obExit( null, null, TRUE );

function c7_menu_button( &$buttons ) {
	global $txt, $context;
	c7_loadLanguage();
	$context['current_action'] = 'c7';

	$buttons['c7'] = [
		'title'         => $txt['c7menu'],
		'show'          => allowedTo( 'admin_forum' ),
		'href'          => '/c7/a.php',
		'active_button' => TRUE,
		'sub_buttons'   => [
		],
	];
}

function c7_loadLanguage() {
	global $user_info;

	c7_english();
	$flang = 'c7_' . ( ! empty( $user_info['language'] ) ? $user_info['language'] : '' );
	if ( function_exists( $flang ) && $flang != 'c7_english' ) {
		return $flang();
	}
}

function c7_main() {
	global $txt, $sourcedir, $boarddir, $boardurl, $context, $user_info, $smcFunc;

	loadLanguage( 'Admin' );
	loadLanguage( 'Packages' );
	loadTemplate( 'Admin' );
	c7_loadLanguage();

	// Sorry, only logged in admins...unless you want so.
	if ( empty( $context['override_security'] ) ) {
		isAllowedTo( 'admin_forum' );
	}

	$context['install'] = isset( $_GET['uninstall'] ) ? 0 : 1;

	if ( ! empty( $_POST['remove'] ) && is_array( $_POST['remove'] ) ) {
		checkSession();

		foreach ( $_POST['remove'] as $id ) {
			if ( isset( $id ) && is_numeric( $id ) ) {
				if ( ! empty( $context['install'] ) ) {
					$smcFunc['db_query']( '', '
						UPDATE {db_prefix}log_packages
						SET
							id_member_removed = {int:id_member},
							member_removed = {string:member_name},
							time_removed = {int:time_removed},
							install_state = 0
						WHERE id_install = {int:inst_package_id}',
					                      [
						                      'id_member'       => $user_info['id'],
						                      'member_name'     => $user_info['name'],
						                      'time_removed'    => time(),
						                      'inst_package_id' => $id,
					                      ] );
				} else {
					$smcFunc['db_query']( '', '
						UPDATE {db_prefix}log_packages
						SET
							id_member_removed = 0,
							member_removed = 0,
							time_removed = 0,
							install_state = 1
						WHERE id_install = {int:inst_package_id}',
					                      [
						                      'inst_package_id' => $id,
					                      ] );
				}
			}
		}

		require_once( $sourcedir . '/Subs-Package.php' );
		package_put_contents( $boarddir . '/Packages/installed.list', time() );
	}
	if ( isset( $_POST['remove_hooks'] ) ) {
		remove_hooks();
	}

	$context['sub_template'] = 'admin';
	$context['page_title']   = $txt[ 'log_packages_title_' . ( ! empty( $context['install'] ) ? 'installed' : 'removed' ) ];
	// Making a list is not hard with this beauty.
	require_once( $sourcedir . '/Subs-List.php' );

	// Use the standard templates for showing this.
	$listOptions = [
		'id'              => 'log_packages',
		'title'           => $context['page_title'],
		'get_items'       => [
			'function' => 'list_getPacks',
		],
		'get_count'       => [
			'function' => 'list_getNumPacks',
		],
		'columns'         => [
			'name'         => [
				'header' => [
					'value' => $txt['mod_name'],
				],
				'data'   => [
					'db' => 'name',
				],
			],
			'version'      => [
				'header' => [
					'value' => $txt['mod_version'],
				],
				'data'   => [
					'db' => 'version',
				],
			],
			'install_date' => [
				'header' => [
					'value' => $txt[ 'mod_' . ( ! empty( $context['install'] ) ? 'installed' : 'removed' ) ],
				],
				'data'   => [
					'function' => create_function( '&$data', '
						return timeformat($data[\'time_' . ( ! empty( $context['install'] ) ? 'installed' : 'removed' ) . '\']);
					' ),
				],
			],
			'check'        => [
				'header' => [
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
				],
				'data'   => [
					'function' => create_function( '$data', '
						return \'<input type="checkbox" name="remove[]" value="\' . $data[\'id_install\'] . \'"  class="input_check" />\';
					' ),
					'class'    => 'centertext',
				],
			],
		],
		'form'            => [
			'href' => $boardurl . '/fix_packages.php?' . $context['session_var'] . '=' . $context['session_id'] . ( ! empty( $context['install'] ) ? '' : ';uninstall' ),
		],
		'additional_rows' => [
			[
				'position' => 'below_table_data',
				'value'    => '
				<a href="' . $boardurl . '/fix_packages.php' . ( ! empty( $context['install'] ) ? '?uninstall' : '' ) . '">[ ' . ( ! empty( $context['install'] ) ? $txt['uninstall'] : $txt['install'] ) . ' ]</a>
				<input type="submit" name="remove_packages" value="' . $txt[ 'pack_button_' . ( ! empty( $context['install'] ) ? 'remove' : 'install' ) ] . '" class="button_submit" />
				<input type="submit" name="remove_hooks" value="' . $txt['remove_hooks'] . '" class="button_submit" />',
				'class'    => 'righttext',
			],
		],
	];

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'log_packages';

	// Create the request list.
	createList( $listOptions );
}

function list_getPacks() {
	global $smcFunc, $context;

	$request   = $smcFunc['db_query']( '', '
		SELECT id_install, name, version, time_installed, time_removed
		FROM {db_prefix}log_packages
		WHERE install_state = {int:inst_state}
		ORDER BY id_install',
	                                   [
		                                   'inst_state' => $context['install'],
	                                   ] );
	$installed = [ ];
	while ( $row = $smcFunc['db_fetch_assoc']( $request ) ) {
		$installed[] = $row;
	}

	$smcFunc['db_free_result']( $request );

	return $installed;
}

function list_getNumPacks() {
	global $smcFunc, $context;

	$request = $smcFunc['db_query']( '', '
		SELECT COUNT(*)
		FROM {db_prefix}log_packages
		WHERE install_state = {int:inst_state}',
	                                 [
		                                 'inst_state' => $context['install'],
	                                 ] );
	list ( $numPacks ) = $smcFunc['db_fetch_row']( $request );
	$smcFunc['db_free_result']( $request );

	return $numPacks;
}

function remove_hooks() {
	global $smcFunc;

	$smcFunc['db_query']( '', '
		DELETE FROM {db_prefix}settings
		WHERE variable LIKE {string:variable}',
	                      [
		                      'variable' => 'integrate_%'
	                      ]
	);

	// Now fixing the cache...
	cache_put_data( 'modsettings', null, 0 );
}
