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
	static protected $magicwords = array();
	static protected $specialPages = array();

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
	 *
	 * @param <type> $variable
	 * @param <type> $callback
	 */
	static function addVariable($variable, $callback)
	{
		WikiExtension::$variables[$variable] = array('callback' => $callback);
	}

	/**
	 * Check if function exists
	 * 
	 * @param string $function
	 * @return boolean True if function exists
	 */
	static function isFunction($function)
	{
		$function = strtolower($function);

		return isset(WikiExtension::$functions[$function]);
	}

	/**
	 * Returns variable from extension(s)
	 * @param string $variable
	 * @return mixed String if variable exists. False if not
	 */
	static function getFunction($function)
	{
		$function = strtolower($function);

		if (!isset(WikiExtension::$functions[$function]))
			return false;

		return WikiExtension::$functions[$function];
	}

	/**
	 *
	 * @param <type> $variable
	 * @param <type> $callback
	 */
	static function addFunction($function, $callback)
	{
		WikiExtension::$functions[$function] = array('callback' => $callback);
	}

	/**
	 * Check if Magic word exists
	 *
	 * @param string $function
	 * @return boolean True if function exists
	 */
	static function isMagicword($magicword)
	{
		global $txt;
		
		$magicword = strtolower($magicword);

		return isset(WikiExtension::$magicwords[$magicword]) || isset($txt['wiki_' . $magicword]);
	}

	/**
	 * Returns variable from extension(s)
	 * @param string $variable
	 * @return mixed String if variable exists. False if not
	 */
	static function getMagicword($magicword)
	{
		global $txt;
		
		$magicword = strtolower($magicword);

		if (!isset(WikiExtension::$magicwords[$magicword]))
			return isset($txt['wiki_' . $magicword]) ? array('txt' => $txt['wiki_' . $magicword]) : false;

		return WikiExtension::$magicwords[$magicword];
	}

	/**
	 *
	 * @param <type> $variable
	 * @param <type> $callback
	 */
	static function addMagicword($magicword, $callback)
	{
		$magicword = strtolower($magicword);
		
		WikiExtension::$magicwords[$magicword] = array('callback' => $callback);
	}

	/**
	 * Returns variable from extension(s)
	 * @param string $variable
	 * @return mixed String if variable exists. False if not
	 */
	static function getSpecialPage($specialpage)
	{
		if (!isset(WikiExtension::$specialPages[$specialpage]))
			return false;

		return WikiExtension::$specialPages[$specialpage];
	}

	/**
	 *
	 * @param <type> $specialpage
	 * @param <type> $callback
	 */
	static function registerSpecialPage($specialpage, $title, $file, $callback)
	{
		WikiExtension::$specialPages[$specialpage] = array('title' => $title, 'file' => $file, 'callback' => $callback);
	}

	/**
	 *
	 * @param string $name class name of extension
	 */
	static function addExtension($name)
	{
		call_user_func(array($name, 'registerExtension'));
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
	/**
	 *
	 */
	static function registerExtension()
	{
		WikiExtension::addVariable('wikiversion', array('WikiExtension_Core', 'variable_WikiVersion'));
		WikiExtension::addVariable('displaytitle', array('WikiExtension_Core', 'variable_DisplayTitle'));

		WikiExtension::addFunction('if', array('WikiExtension_Core', 'function_if'));

		WikiExtension::addMagicword('index', array('WikiExtension_Core', 'magicword_index'));
		WikiExtension::addMagicword('noindex', array('WikiExtension_Core', 'magicword_noindex'));
	}

	/**
	 *
	 * @global <type> $wiki_version
	 * @param WikiParser $wikiparser
	 * @param <type> $parameters
	 * @return <type>
	 */
	static function variable_WikiVersion(WikiParser $wikiparser, $parameters)
	{
		global $wiki_version;

		return $wiki_version;
	}

	/**
	 *
	 * @param WikiParser $wikiparser
	 * @param <type> $parameters
	 * @return <type>
	 */
	static function variable_DisplayTitle(WikiParser $wikiparser, $parameters)
	{
		if (empty($parameters))
			return $wikiparser->page->title;
		else
			$wikiparser->page->title = WikiParser::toText(array_shift($parameters));

		return true;
	}

	/**
	 *
	 * @param WikiParser $wikiparser
	 * @param <type> $parameters
	 */
	static function function_if(WikiParser $wikiparser, $parameters)
	{
		$result = WikiParser::toBoolean(array_shift($parameters));
		$true_cond = array_shift($parameters);
		if (!empty($parameters))
			$false_cond = array_shift($parameters);

		$wikiparser->throwContentArray($result ? $true_cond : (isset($false_cond) ? $false_cond : array()));
	}

	/**
	 *
	 */
	static function magicword_index(WikiParser $wikiparser)
	{
		$wikiparser->pageOptions['index'] = true;
	}

	/**
	 *
	 */
	static function magicword_noindex(WikiParser $wikiparser)
	{
		$wikiparser->pageOptions['index'] = true;
	}
}

?>