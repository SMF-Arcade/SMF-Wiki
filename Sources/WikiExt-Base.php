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
	static protected $variables = array();
	static protected $functions = array();

	/**
	 * Checks if variable exists
	 * @param string $variable
	 * @return boolean True if variable
	 */
	static function variableExists($variable)
	{
		$variable = strtolower($variable);

		return isset(WikiExtension::$variables[$variable]);
	}

	/**
	 * Returns variable from extension(s)
	 * @param string $variable
	 * @return mixed String if variable exists. False if not
	 */
	static function getVariable($variable)
	{
		$variable = strtolower($variable);
		
		if (!isset(WikiExtension::$variables[$variable]))
			return false;

		return WikiExtension::$variables[$variable];
	}

	/**
	 * Check if function exists
	 * 
	 * @param string $function
	 * @return boolean True if function exists
	 */
	static function isFunction($function)
	{
		return isset(WikiExtension::$functions[$function]);
	}

	static function addExtension($name)
	{
		call_user_func(array($name, 'registerExtension'));
	}

	static function addVariable($variable, $callback)
	{
		WikiExtension::$variables[$variable] = array('callback' => $callback);
	}
}

/**
 * Wiki Extension base class, all extensions should extend this
 */
abstract class WikiExtensionBase
{
	abstract static function registerExtension();
}

/**
 *
 */
class WikiExtension_Core extends WikiExtensionBase
{
	static function registerExtension()
	{
		WikiExtension::addVariable('wikiversion', array('WikiExtension_Core', 'variable_WikiVersion'));
		WikiExtension::addVariable('displaytitle', array('WikiExtension_Core', 'variable_DisplayTitle'));
	}

	static function variable_WikiVersion(WikiParser $wikiparser, $parameters)
	{
		global $wiki_version;

		return $wiki_version;
	}

	static function variable_DisplayTitle(WikiParser $wikiparser, $parameters)
	{
		if (empty($parameters))
			return $wikiparser->page->title;
		else
			$wikiparser->title = WikiParser::toText(array_shift($parameters));

		return true;
	}
}

?>