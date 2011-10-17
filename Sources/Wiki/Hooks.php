<?php
/**
 * Hooks for SMF Wiki
 *
 * @package core
 * @version 0.3
 * @since 0.3
 */

/**
 *
 */
class Wiki_Hooks
{
	/**
	 * Autoload function
	 * 
	 * @param string $class_name Class Name
	 */
	static public function autoload($class_name)
	{
		global $sourcedir;
		
		if ($class_name == 'Wiki')
			require_once($sourcedir . '/Wiki/Wiki.php');	
		elseif (substr($class_name, 0, 4) == 'Wiki')
		{
			$class_file = str_replace('_', '/', $class_name);
			
			if (file_exists($sourcedir . '/' . $class_file) && is_dir($sourcedir . '/' . $class_file))
			{				
				$class_file .= substr($class_file, strrpos($class_file, '/'));
				require_once($sourcedir . '/' . $class_file . '.php');
			}
			elseif (file_exists($sourcedir . '/' . $class_file . '.php'))
				require_once($sourcedir . '/' . $class_file . '.php');
			else
				return false;
				
			return true;
		}
		
		return false;
	}
	
	/**
	 * Registers autoload function
	 */
	static public function registerAutoload()
	{
		spl_autoload_register(array(__CLASS__, 'autoload'));
	}
	
	/**
	 * Inserts array in array after key
	 *
	 * @param array $input Input array
	 * @param string $key Key to search
	 * @param array $insert Array of values to insert after
	 * @param string $where Relation to insert to key: 'before' or 'after'.
	 * @param bool $strict Strict parameter for array_search.
	 */
	public static function array_insert(&$input, $key, $insert, $where = 'after', $strict = false)
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
	
	/**
	 * SMF Hook integrate_pre_load
	 */
	public static function pre_load()
	{
		global $modSettings;
		
		if (empty($modSettings['wikiEnabled']))
			return;
		
		self::registerAutoload();
	}
	
	/**
	 * SMF Hook integrate_actions
	 *
	 * Adds actions in $actionArray of index.php
	 */
	public static function actions(&$actionArray)
	{
		global $modSettings;
		
		if (empty($modSettings['wikiEnabled']))
			return;
		
		$actionArray['wiki'] = array('Wiki.php', 'Wiki');
	}
	
	/**
	 * SMF Hook integrate_menu_buttons
	 */
	public static function menu_buttons(&$menu_buttons)
	{
		global $modSettings, $context, $txt, $scripturl;
		
		$context['allow_wiki'] = !empty($modSettings['wikiEnabled']) && allowedTo('wiki_access');
		
		if (empty($modSettings['wikiEnabled']))
			return;
		
		self::array_insert($menu_buttons, 'search', array(
			'wiki' => array(
				'title' => $txt['wiki'],
				'href' => $scripturl . '?action=wiki',
				'show' => $context['allow_wiki'] && empty($modSettings['wikiStandalone']),
				'sub_buttons' => array(),
			)), 'after'
		);
	}
	
	/**
	 * SMF Hook integrate_admin_areas
	 *
	 * Adds Wiki to admin.
	 */
	public static function admin_areas(&$admin_areas)
	{
		global $txt, $modSettings;
		
		if (empty($modSettings['wikiEnabled']))
			return;
		
		self::array_insert($admin_areas['config']['areas'], 'current_theme',
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
	
	public static function load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
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
	
	/**
	 *
	 */
	public static function core_features(&$core_features)
	{
		$core_features['wiki'] = array(
			'url' => 'action=admin;area=wiki',
			'settings' => array(
				'wikiEnabled' => 1,
			),
		);
	}
}

?>