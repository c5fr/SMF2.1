<?

// Version en réel


// A nice menu button
define( 'SMF_INTEGRATION_SETTINGS', serialize( [
	                                               'integrate_menu_buttons' => 'a_menu_button',
                                               ] ) );
// Let's use the default theme
$ssi_theme     = 1;
$forum_version = 'SMF 2.1 Beta 2';
// If SSI.php is in the same place as this file, this is being run standalone.
if ( file_exists( dirname( __FILE__ ) . '../../SSI.php' ) ) {
	require_once( dirname( __FILE__ ) . '../../SSI.php' );
} elseif ( file_exists( '../../SSI.php' ) ) {
	require_once( '../../SSI.php' );
} // Hmm... no SSI.php?
else {
	die( '<b>Error:</b> Cannot find SSI - please verify you put this in the same place as SMF\'s index.php.' );
}

/**
 * Change false to true if you want to avoid the admin check.
 */
//$context['override_security'] = FALSE;
//
//echo 'oki';
//echo '<center><h5>Welcome '.$MemberGroup.' !</h5></center>';

/**
 *
 * Do you want to add a new language?
 * Copy the following function,
 * change 'english' to the language you want
 * and translate it. ;D
 *
 */
function ac7_french_utf8() {
	global $txt;
	$txt['ac7menu']       = 'aC7 FR';
	$txt['log_ac7_title'] = 'Titre de la Page';
}

function ac7_english() {
	global $txt;
	$txt['ac7menu']       = 'aC7 EN';
	$txt['log_ac7_title'] = 'Page Title';
}

ac7_main();
// Let's start the main job
obExit( null, null, TRUE );

// and then let's throw out the template! :P
//
function a_menu_button( &$buttons ) {
	global $txt, $context;
	ac7_loadLanguage();
	$context['current_action'] = 'ac7';

	$buttons['ac7'] = [
		'title'         => $txt['ac7menu'],
		'show'          => allowedTo( 'admin_forum' ),
		'href'          => '/c7/a.php',
		'active_button' => TRUE,
		'sub_buttons'   => [
		],
	];
}

function ac7_loadLanguage() {
	global $user_info;

	ac7_english();
	$flang = 'ac7_english';
	if ( ! empty( $user_info['language'] ) ) {
		$flang = 'ac7_' . preg_replace( '[-]', '_', $user_info['language'] );
		//var_dump( $user_info['language'] );
		//echo '<h1>flang: ' . $flang . '</h1>';
		if ( function_exists( $flang ) && $flang != 'ac7_english' ) {
			return $flang();
		}
	}

}

function ac7_main() {
	global $txt, $sourcedir, $boarddir, $boardurl, $context, $user_info, $smcFunc;

	loadLanguage( 'Admin' );
	loadLanguage( 'Packages' );
	loadTemplate( 'Admin' );
	ac7_loadLanguage();


	$context['sub_template'] = 'admin';

	// Sorry, only logged in admins...unless you want so.
	if ( empty( $context['override_security'] ) ) {
		isAllowedTo( 'admin_forum' );
	}

	$context['sub_template'] = 'admin';
	$context['page_title']   = 'oOo';
	//$context['install']      = isset( $_GET['uninstall'] ) ? 0 : 1;

	if ( ! empty( $_POST['remove'] ) && is_array( $_POST['remove'] ) ) {
		checkSession();
	}
	/*
		echo 'Oki';



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
	*/
	$context['sub_template'] = 'admin';
	$context['default_list'] = 'log_ac7';

	// Create the request list.
	//createList( $listOptions );
}
//TODOLI Finir Affichage de cette page
