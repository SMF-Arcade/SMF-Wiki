<?php
/**
 * SMF Wiki
 *
 * @package SMF Arcade
 * @version 0.2
 * @license http://download.smfarcade.info/license.php New-BSD
 */

global $txt, $smcFunc, $db_prefix, $modSettings;
global $addSettings, $permissions, $tables, $sourcedir;

if (!defined('SMF'))
	require '../SSI.php';
	
remove_integration_function('integrate_pre_include', '$sourcedir/WikiHooks.php');
remove_integration_function('integrate_actions', 'Wiki_actions');
remove_integration_function('integrate_core_features', 'Wiki_core_features');
remove_integration_function('integrate_load_permissions', 'Wiki_load_permissions');
remove_integration_function('integrate_menu_buttons', 'Wiki_menu_buttons');
remove_integration_function('integrate_admin_areas', 'Wiki_admin_areas');

?>