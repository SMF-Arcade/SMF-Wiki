<?php
/**********************************************************************************
* Subs-Install.php                                                                *
***********************************************************************************
* SMF Wiki                                                                        *
* =============================================================================== *
* Software Version:           SMF Wiki 0.1                                        *
* Software by:                Niko Pahajoki (http://www.madjoki.com)              *
* Copyright 2008-2009 by:     Niko Pahajoki (http://www.madjoki.com)              *
* Support, News, Updates at:  http://www.smfarcade.info                           *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

function doTables($tables, $columnRename = array())
{
	global $smcFunc, $db_prefix, $db_type;

	$log = array();
	$existingTables = $smcFunc['db_list_tables']();

	foreach ($tables as $table)
	{
		$table_name = $table['name'];

		$tableExists = in_array($db_prefix . $table_name, $existingTables);

		// Create table
		if (!$tableExists && empty($table['smf']))
			$smcFunc['db_create_table']($table_name, $table['columns'], $table['indexes']);
		// Update table
		else
		{
			$currentTable = $smcFunc['db_table_structure']($table_name);

			// Renames in this table?
			if (!empty($table['rename']))
			{
				foreach ($currentTable['columns'] as $column)
				{
					if (isset($table['rename'][$column['name']]))
					{
						$old_name = $column['name'];
						$column['name'] = $table['rename'][$column['name']];

						$smcFunc['db_change_column']($table_name, $old_name, $column);
					}
				}
			}

			// Global renames? (should be avoided)
			if (!empty($columnRename))
			{
				foreach ($currentTable['columns'] as $column)
				{
					if (isset($columnRename[$column['name']]))
					{
						$old_name = $column['name'];
						$column['name'] = $columnRename[$column['name']];
						$smcFunc['db_change_column']($table_name, $old_name, $column);
					}
				}
			}

			// Check that all columns are in
			foreach ($table['columns'] as $id => $col)
			{
				$exists = false;

				// TODO: Check that definition is correct
				foreach ($currentTable['columns'] as $col2)
				{
					if ($col['name'] === $col2['name'])
					{
						$exists = true;
						break;
					}
				}

				// Add missing columns
				if (!$exists)
					$smcFunc['db_add_column']($table_name, $col);

				// TEMPORARY until SMF package functions works with this
				if (isset($column['unsigned']) && $db_type == 'mysql')
				{
					$column['size'] = isset($column['size']) ? $column['size'] : null;

					list ($type, $size) = $smcFunc['db_calculate_type']($column['type'], $column['size']);
					if ($size !== null)
						$type = $type . '(' . $size . ')';

					$smcFunc['db_query']('', "
						ALTER TABLE {db_prefix}$table_name
						CHANGE COLUMN $column[name] $column[name] $type UNSIGNED " . (empty($column['null']) ? 'NOT NULL' : '') . ' ' .
							(empty($column['default']) ? '' : "default '$column[default]'") . ' ' .
							(empty($column['auto']) ? '' : 'auto_increment') . ' ',
						'security_override'
					);
				}
			}

			// Remove any unnecassary columns
			foreach ($currentTable['columns'] as $col)
			{
				$exists = false;

				foreach ($table['columns'] as $col2)
				{
					if ($col['name'] === $col2['name'])
					{
						$exists = true;
						break;
					}
				}

				if (!$exists && isset($table['upgrade']['columns'][$col['name']]))
				{
					if ($table['upgrade']['columns'][$col['name']] == 'drop')
						$smcFunc['db_remove_column']($table_name, $col['name']);
				}
				elseif (!$exists && empty($table['smf']))
					$log[] = sprintf('Table %s has non-required column %s', $table_name, $col['name']);
			}

			// Check that all indexes are in and correct
			foreach ($table['indexes'] as $id => $index)
			{
				$exists = false;

				foreach ($currentTable['indexes'] as $index2)
				{
					// Primary is special case
					if ($index['type'] == 'primary' && $index2['type'] == 'primary')
					{
						$exists = true;

						if ($index['columns'] !== $index2['columns'])
						{
							$smcFunc['db_remove_index']($table_name, 'primary');
							$smcFunc['db_add_index']($table_name, $index);
						}

						break;
					}
					// Make sure index is correct
					elseif (isset($index['name']) && isset($index2['name']) && $index['name'] == $index2['name'])
					{
						$exists = true;

						// Need to be changed?
						if ($index['type'] != $index2['type'] || $index['columns'] !== $index2['columns'])
						{
							$smcFunc['db_remove_index']($table_name, $index['name']);
							$smcFunc['db_add_index']($table_name, $index);
						}

						break;
					}
				}

				if (!$exists)
					$smcFunc['db_add_index']($table_name, $index);
			}

			// Remove unnecassary indexes
			foreach ($currentTable['indexes'] as $index)
			{
				$exists = false;

				foreach ($table['indexes'] as $index2)
				{
					// Primary is special case
					if ($index['type'] == 'primary' && $index2['type'] == 'primary')
						$exists = true;
					// Make sure index is correct
					elseif (isset($index['name']) && isset($index2['name']) && $index['name'] == $index2['name'])
						$exists = true;
				}

				if (!$exists)
				{
					if (isset($table['upgrade']['indexes']))
					{
						foreach ($table['upgrade']['indexes'] as $index2)
						{
							if ($index['type'] == 'primary' && $index2['type'] == 'primary' && $index['columns'] === $index2['columns'])
								$smcFunc['db_remove_index']($table_name, 'primary');
							elseif (isset($index['name']) && isset($index2['name']) && $index['name'] == $index2['name'] && $index['type'] == $index2['type'] && $index['columns'] === $index2['columns'])
								$smcFunc['db_remove_index']($table_name, $index['name']);
							else
								$log[] = $table_name . ' has Unneeded index ' . var_dump($index);
						}
					}
					else
						$log[] = $table_name . ' has Unneeded index ' . var_dump($index);
				}
			}
		}
	}

	if (!empty($log))
		log_error(implode('<br />', $log));

	return $log;
}

function doSettings($addSettings)
{
	global $smcFunc, $modSettings;

	$update = array();

	foreach ($addSettings as $variable => $value)
	{
		list ($value, $overwrite) = $value;

		if ($overwrite || !isset($modSettings[$variable]))
			$update[$variable] = $value;
	}

	if (!empty($update))
		updateSettings($update);
}

function doPermission($permissions)
{
	global $smcFunc;

	$perm = array();

	foreach ($permissions as $permission => $default)
	{
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}permissions
			WHERE permission = {string:permission}',
			array(
				'permission' => $permission
			)
		);

		list ($num) = $smcFunc['db_fetch_row']($result);

		if ($num == 0)
		{
			foreach ($default as $grp)
				$perm[] = array($grp, $permission);
		}
	}

	if (empty($perm))
		return;

	$smcFunc['db_insert']('insert',
		'{db_prefix}permissions',
		array(
			'id_group' => 'int',
			'permission' => 'string'
		),
		$perm,
		array()
	);
}

function updateAdminFeatures($item, $enabled = false)
{
	global $modSettings;

	$admin_features = isset($modSettings['admin_features']) ? explode(',', $modSettings['admin_features']) : array('cd,cp,k,w,rg,ml,pm');

	if (!is_array($item))
		$item = array($item);

	if ($enabled)
		$admin_features = array_merge($admin_features, $item);
	else
		$admin_features = array_diff($admin_features, $item);

	updateSettings(array('admin_features' => implode(',', $admin_features)));

	return true;
}

?>