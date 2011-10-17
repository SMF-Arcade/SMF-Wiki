<?php
/**
 * 
 *
 * @package core
 * @version 0.3
 * @license http://download.smfproject.net/license.php New-BSD
 * @since 0.3
 */

/**
 *
 */
class Wiki_Install
{
	/**
	 *
	 */
	static protected $adminFeature = 'wiki';
	
	/**
	 *
	 */
	static protected $settings = array(
		'wikiEnabled' => array(1, false),
		'wikiAttachmentsDir' => array('', false),
		'wikiStandalone' => array(0, false),
		'wikiStandaloneUrl' => array('', false),
		'wikiTalkBoard' => array(0, false),
		'wikiVersion' => array('0.3', true),
		'wikiDatabaseVersion' => array('0.3 pre', true),
	);
	
	/**
	 *
	 */
	static protected $permissions = array(
		'wiki_access' => array(-1, 0, 2),
	);

	/**
	 *
	 */
	static protected $hooks = array(
		'integrate_pre_include' => '$sourcedir/Wiki/Hooks.php',
		'integrate_pre_load' => 'Wiki_Hooks::pre_load',
		'integrate_actions' => 'Wiki_Hooks::actions',
		'integrate_admin_areas' => 'Wiki_Hooks::admin_areas',
		'integrate_core_features' => 'Wiki_Hooks::core_features',
		'integrate_menu_buttons' => 'Wiki_Hooks::menu_buttons',
	);
	
	/**
	 *
	 */
	static public function install($prefix = '{db_prefix}wiki_')
	{
		global $modSettings;
		
		$upgradeFrom = isset($modSettings['wikiDatabaseVersion']) ? $modSettings['wikiDatabaseVersion'] : false;
		
		if (!$upgradeFrom)
			Madjoki_Install_Helper::updateAdminFeatures(self::$adminFeature, true);
			
		$db = new Wiki_Install_Database($prefix);
		$db->DoTables();
			
		Madjoki_Install_Helper::doSettings(self::$settings);
		Madjoki_Install_Helper::doPermission(self::$permissions);
		
		self::installDefaultData($prefix);
		
		foreach (self::$hooks as $hook => $func)
			add_integration_function($hook, $func);
	}
	
	/**
	 *
	 */
	static public function installDefaultData($prefix = '{db_prefix}wiki_')
	{
		global $smcFunc, $sourcedir;
		
		require_once($sourcedir . '/Subs-Post.php');

		// Step 6: Install default namespace
		$smcFunc['db_insert']('ignore',
			$prefix . 'namespace',
			array(
				'namespace' => 'string',
				'ns_prefix' => 'string',
				'default_page' => 'string',
				'namespace_type' => 'int',
			),
			array(
				array(
					'',
					'',
					'Main_Page',
					0,
				),
				array(
					'Template',
					'Templates',
					'Index',
					0,
				),
			),
			array('namespace')
		);
		
		// Step 7: Install other namespaces
		$specialNamespaces = array(
			1 => array('name' => 'Special', 'default_page' => 'List'),
			2 => array('name' => 'File', 'default_page' => 'List'),
			3 => array('name' => 'Image', 'default_page' => 'List'),
			4 => array('name' => 'SMFWiki', 'default_page' => 'List'),
			5 => array('name' => 'Category', 'default_page' => 'List'),
		);
		
		foreach ($specialNamespaces as $type => $data)
		{
			$request = $smcFunc['db_query']('', '
				SELECT namespace, ns_prefix, default_page, namespace_type
				FROM ' . $prefix . 'namespace
				WHERE namespace_type = {int:type}',
				array(
					'type' => $type,
				)
			);
		
			$row = $smcFunc['db_fetch_assoc']($request);
		
			if (!$row)
				$smcFunc['db_insert']('replace',
					$prefix . 'namespace',
					array(
						'namespace' => 'string',
						'ns_prefix' => 'string',
						'default_page' => 'string',
						'namespace_type' => 'int',
					),
					array(
						array(
							$data['name'],
							'',
							$data['default_page'],
							$type,
						),
					),
					array('namespace')
				);
		
			$smcFunc['db_free_result']($request);
		}
		
		// Step 8: Create and update default pages (incase they are not edited before)
		$defaultPages = array(
			array(
				'namespace' => '',
				'name' => 'Main_Page',
				'body' => 'SMF Wiki {{wikiversion}} installed!',
				'locked' => true,
			),
			array(
				'namespace' => 'SMFWiki',
				'name' => 'Sidebar',
				'body' => '* __navigation__' . "\n" . '** [[Main_Page|__main_page__]]',
				'locked' => true,
			),
		);
		
		foreach ($defaultPages as $page)
		{
			$page['body'] = $smcFunc['htmlspecialchars']($page['body'], ENT_QUOTES);
			preparsecode($page['body']);
		
			self::createPage($page['namespace'], $page['name'], $page['body'], $page['locked'], $prefix);
		}
		//
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}package_servers
			WHERE name = {string:name}',
			array(
				'name' => 'SMF Wiki Package Server',
			)
		);
		
		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		
		if ($count == 0)
			$smcFunc['db_insert']('insert',
				'{db_prefix}package_servers',
				array(
					'name' => 'string',
					'url' => 'string',
				),
				array(
					'SMF Wiki Package Server',
					'http://download.smfwiki.net',
				),
				array()
			);
	}
	
	/**
	 *
	 */
	static public function uninstall()
	{
		// Remove hooks
		foreach (self::$hooks as $hook => $func)
			remove_integration_function($hook, $func);
	}
	
	/**
	 *
	 */
	static public function uninstallDatabase()
	{
		global $smcFunc;
		
		// 
		Madjoki_Install_Helper::updateAdminFeatures(self::$adminFeature, false);
		
		// Remove settings
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN({array_string:settings})',
			array(
				'settings' => array_keys(self::$settings),
			)
		);
		
		// Remove permissions
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}permissions
			WHERE permission IN({array_string:permissions})',
			array(
				'permissions' => array_keys(self::$permissions),
			)
		);
	}
	
	/**
	 *
	 */
	static private function createPage($namespace, $name, $body, $locked = true, $exists = 'ignore', $prefix = '{db_prefix}wiki_')
	{
		global $smcFunc, $wiki_version, $user_info, $modSettings;
	
		$comment = 'SMF Wiki default page';
	
		$request = $smcFunc['db_query']('', '
			SELECT info.id_page, con.content, con.comment
			FROM ' . $prefix . 'pages AS info
				INNER JOIN ' . $prefix . 'content AS con ON (con.id_revision = info.id_revision_current
					AND con.id_page = info.id_page)
			WHERE info.title = {string:page}
				AND info.namespace = {string:namespace}',
			array(
				'page' => $name,
				'namespace' => $namespace,
				'revision' => !empty($revision) ? $revision : 'info.id_revision_current',
			)
		);
	
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			list ($id_page, $content, $comment2) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
	
			if ($comment2 != $comment && $exists != 'update')
				return false;
	
			if ($content == $body)
				return true;
		}
		else
		{
			$smcFunc['db_insert']('insert',
				$prefix . 'pages',
				array(
					'title' => 'string-255',
					'namespace' => 'string-255',
					'is_locked' => 'int',
				),
				array(
					$name,
					$namespace,
					$locked ? 1 : 0,
				),
				array('id_page')
			);
	
			$id_page = $smcFunc['db_insert_id']($prefix . 'pages', 'id_page');
		}
	
		$smcFunc['db_insert']('insert',
			$prefix . 'content',
			array(
				'id_page' => 'int',
				'id_author' => 'int',
				'timestamp' => 'int',
				'content' => 'string',
				'comment' => 'string-255',
			),
			array(
				$id_page,
				$user_info['id'],
				time(),
				$body,
				$comment,
			),
			array('id_revision')
		);
	
		$id_revision = $smcFunc['db_insert_id']($prefix . 'content', 'id_revision');
	
		$smcFunc['db_query']('' ,'
			UPDATE ' . $prefix . 'pages
			SET id_revision_current = {int:revision}
			WHERE id_page = {int:page}',
			array(
				'page' => $id_page,
				'revision' => $id_revision,
			)
		);
	
		return true;
	}
}

?>