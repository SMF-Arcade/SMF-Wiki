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
	static protected $xmlTags = array();
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
	 * 
	 * 
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
	 * Check if XML Tag exists
	 * 
	 * @param string $tag
	 * @return boolean True if XML Tag exists
	 */
	static function isXMLTag($tag)
	{
		$tag = strtolower($tag);

		return isset(WikiExtension::$xmlTags[$tag]);
	}

	/**
	 *
	 * 
	 * @param string $variable
	 * @return mixed String if variable exists. False if not
	 */
	static function getXMLTag($tag)
	{
		$tag = strtolower($tag);

		if (!isset(WikiExtension::$xmlTags[$tag]))
			return false;

		return WikiExtension::$xmlTags[$tag];
	}

	/**
	 *
	 * @param <type> $variable
	 * @param <type> $callback
	 */
	static function addXMLTag($tag, $class)
	{
		if (!is_subclass_of($class, 'Wiki_Element_XMLTag'))
			trigger_error('XMLTag must be subclass of Wiki_Element_XMLTag', E_USER_ERROR);
			
		WikiExtension::$xmlTags[$tag] = array('class' => $class);
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
		
		//global $sourcedir;
		//require_once($sourcedir . '/Wiki/Element/Test.php');
		WikiExtension::addXMLTag('test', 'Wiki_Element_Test');
	}

	/**
	 *
	 * @global <type> $wiki_version
	 * @param Wiki_Parser $wikiparser
	 * @param <type> $parameters
	 * @return <type>
	 */
	static function variable_WikiVersion($wikiparser, $parameters)
	{
		global $wiki_version;

		return $wiki_version;
	}

	/**
	 *
	 * @param Wiki_Parser $wikiparser
	 * @param <type> $parameters
	 * @return <type>
	 */
	static function variable_DisplayTitle(Wiki_Parser $wikiparser, $parameters)
	{
		if (empty($parameters))
			return $wikiparser->page->title;
		else
			$wikiparser->page->title = Wiki_Parser_Core::toText(array_shift($parameters));

		return true;
	}

	/**
	 *
	 * @param Wiki_Parser $wikiparser
	 * @param <type> $parameters
	 */
	static function function_if($wikiparser, $parameters)
	{
		$result = Wiki_Parser_Core::toBoolean($parameters[0]);
		$true_cond = $parameters[1];
		if (!empty($parameters[2]))
			$false_cond = $parameters[2];
		$wikiparser->throwContentArray($result ? $true_cond : (isset($false_cond) ? $false_cond : array()));
	}

	/**
	 *
	 */
	static function magicword_index(Wiki_Parser $wikiparser)
	{
		$wikiparser->pageOptions['index'] = true;
	}

	/**
	 *
	 */
	static function magicword_noindex(Wiki_Parser $wikiparser)
	{
		$wikiparser->pageOptions['index'] = true;
	}
}

?>