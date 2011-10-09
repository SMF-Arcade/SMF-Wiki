<?php
/**
 * SMF Wiki
 *
 * @package SMF Wiki
 * @version 0.2
 * @license http://download.smfarcade.info/license.php New-BSD
 */

if (!defined('SMF'))
	die('Hacking attempt...');
	
function wiki_array_insert(&$input, $key, $insert, $where = 'before', $strict = false)
{
	$position = array_search($key, array_keys($input), $strict);
	
	// Key not found -> insert as last
	if ($position === false)
	{
		$input = array_merge($input, $insert);
		return;
	}
	
	if ($where === 'after')
		$position += 1;

	// Insert as first
	if ($position === 0)
		$input = array_merge($insert, $input);
	else
		$input = array_merge(
			array_slice($input, 0, $position, true),
			$insert,
			array_slice($input, $position, null, true)
		);
}
	
function Wiki_actions(&$actionArray)
{
	global $modSettings;
	
	if (empty($modSettings['wikiEnabled']))
		return;
	
	$actionArray['wiki'] = array('Wiki.php', 'Wiki');
}

function Wiki_core_features(&$core_features)
{
	$core_features['wiki'] = array(
		'url' => 'action=admin;area=wiki',
		'settings' => array(
			'wikiEnabled' => 1,
		),
	);
}

function Wiki_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	global $context;
	
	$permissionList['membergroup'] += array(
		'wiki_access' => array(false, 'wiki', 'wiki'),
		'wiki_edit' => array(false, 'wiki', 'wiki'),
		'wiki_upload' => array(false, 'wiki', 'wiki'),
		'wiki_delete' => array(false, 'wiki', 'administrate'),
		'wiki_admin' => array(false, 'wiki', 'administrate'),
	);
}

function Wiki_menu_buttons(&$menu_buttons)
{
	global $context, $modSettings, $scripturl, $txt;
	
	$context['allow_wiki'] = !empty($modSettings['wikiEnabled']) && allowedTo('wiki_access');
	
	wiki_array_insert($menu_buttons, 'search',
		array(
			'wiki' => array(
				'title' => $txt['wiki'],
				'href' => $scripturl . '?action=wiki',
				'show' => $context['allow_wiki'] && empty($modSettings['wikiStandalone']),
				'sub_buttons' => array(),
			),
		)
	);
}

function Wiki_admin_areas(&$admin_areas)
{
	global $context, $modSettings, $scripturl, $txt;
	
	wiki_array_insert($admin_areas['config']['areas'], 'modsettings',
		array(
			'wiki' => array(
				'label' => $txt['admin_wiki'],
				'file' => 'WikiAdmin.php',
				'function' => 'WikiAdmin',
				'enabled' => !empty($modSettings['wikiEnabled']),
				'subsections' => array(
					'main' => array($txt['admin_wiki_information']),
					'settings' => array($txt['admin_wiki_settings']),
				),
			),
		)
	);
}

?>