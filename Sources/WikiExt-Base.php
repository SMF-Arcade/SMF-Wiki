<?php
/**
 * Contains base class for Extensions
 *
 * @package parser
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.2
 */

/**
 * Wiki Extension class. This handles calling extensions and keeps log of registered extensions
 */
class WikiExtension
{
	static $variables;
	
	static function getVariable($variable)
	{
		if (!isset(WikiExtension::$variables[$variable]))
			return false;

		if ($variable == 'wikiversion')
			return $GLOBALS['wiki_version'];
		// TODO: Do this
	}
}

/**
 * Wiki Extension base class, all extensions should extend this
 */
class WikiExtensionBase
{
	
}

?>