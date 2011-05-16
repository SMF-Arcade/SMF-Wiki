<?php
/**
 *
 *
 * @package LibMadjoki
 * @subpackage Install
 */

/**
 *
 */
class Wiki_Install_Database extends Madjoki_Install_Database
{
	/**
	 *
	 */
	protected $tables = array(
		// Namespaces
		'namespace' => array(
			'name' => 'namespace',
			'columns' => array(
				array(
					'name' => 'namespace',
					'type' => 'varchar',
					'size' => '30',
					'default' => '',
				),
				array(
					'name' => 'ns_prefix',
					'type' => 'varchar',
					'size' => '45',
					'default' => '',
				),
				array(
					'name' => 'default_page',
					'type' => 'varchar',
					'size' => '255',
					'default' => 'Index',
				),
				array(
					'name' => 'namespace_type',
					'type' => 'int',
					'unsigned' => true,
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('namespace')
				),
			)
		),
		// Page names
		'pages' => array(
			'name' => 'pages',
			'columns' => array(
				array(
					'name' => 'id_page',
					'type' => 'int',
					'auto' => true,
					'unsigned' => true,
				),
				array(
					'name' => 'title',
					'type' => 'varchar',
					'size' => '255',
					'default' => '',
				),
				array(
					'name' => 'namespace',
					'type' => 'varchar',
					'size' => '30',
					'default' => '',
				),
				array(
					'name' => 'display_title',
					'type' => 'varchar',
					'size' => '255',
					'default' => '',
				),
				array(
					'name' => 'is_locked',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'is_deleted',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'id_revision_current',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'id_member',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'id_topic',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'id_file',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_page')
				),
			)
		),
		// Page Content
		'content' => array(
			'name' => 'content',
			'columns' => array(
				array(
					'name' => 'id_revision',
					'type' => 'int',
					'auto' => true,
					'unsigned' => true,
				),
				array(
					'name' => 'id_page',
					'type' => 'int',
					'unsigned' => true,
				),
				array(
					'name' => 'id_author',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'id_file',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'timestamp',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'display_title',
					'type' => 'varchar',
					'size' => '255',
					'default' => '',
				),
				array(
					'name' => 'content',
					'type' => 'text',
				),
				array(
					'name' => 'comment',
					'type' => 'varchar',
					'size' => '255',
					'default' => '',
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_revision')
				),
				array(
					'name' => 'id_page',
					'type' => 'index',
					'columns' => array('id_page')
				),
			)
		),
		// Wiki Category
		'category' => array(
			'name' => 'category',
			'columns' => array(
				array(
					'name' => 'id_page',
					'type' => 'int',
					'unsigned' => true,
				),
				array(
					'name' => 'category',
					'type' => 'varchar',
					'size' => 255,
				),		
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_page', 'category'),
				),
				array(
					'name' => 'id_page',
					'type' => 'index',
					'columns' => array('id_page')
				),
				array(
					'name' => 'category',
					'type' => 'index',
					'columns' => array('category')
				),
			)
		),	
		// Files
		'files' => array(
			'name' => 'files',
			'columns' => array(
				array(
					'name' => 'id_file',
					'type' => 'int',
					'auto' => true,
					'unsigned' => true,
				),
				array(
					'name' => 'id_page',
					'type' => 'int',
					'unsigned' => true,
				),
				array(
					'name' => 'localname',
					'type' => 'varchar',
					'size' => '255',
					'default' => '',
				),
				array(
					'name' => 'mime_type',
					'type' => 'varchar',
					'size' => '35',
					'default' => '',
				),
				array(
					'name' => 'file_ext',
					'type' => 'varchar',
					'size' => '10',
					'default' => '',
				),
				array(
					'name' => 'is_current',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'id_member',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'timestamp',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'filesize',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'img_width',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
				array(
					'name' => 'img_height',
					'type' => 'int',
					'default' => 0,
					'unsigned' => true,
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_file')
				),
				array(
					'name' => 'id_page_is_current',
					'type' => 'index',
					'columns' => array('id_page', 'is_current')
				),
			)
		),
	);
	
	/**
	 *
	 */
	public function  __construct($prefix = '{db_prefix}')
	{
		$this->prefix = $prefix;
	}
}

?>