<?php
/**
 *
 */

/**
 * 
 */
class Wiki_Parser_Bracket extends Wiki_Parser_SubParser
{
	/**
	 *
	 */
	const WIKILINK = 1;
	
	/**
	 *
	 */
	const TEMPLATE = 2;
	
	/**
	 *
	 */
	const TEMPLATE_PARAM = 3;
	
	/**
	 *
	 */
	const HASHTAG = 4;
	
	/**
	 *
	 */
	const FUNC = 5;
	
	/**
	 *
	 */
	const VARIABLE = 6;
	
	/**
	 *
	 */
	const PARAM_SEP = 1024;

	/**
	 *
	 */
	const NAME_SEP = 1025;

	/**
	 *
	 */
	const FUNC_SEP = 1026;
	
	/**
	 *
	 */
	static public $rules = array(
		'[' => array(
			'close' => ']',
			'min' => 2,
			'max' => 2,
			'names' => array(
				2 => self::WIKILINK,
			),
		),
		'{' => array(
			'close' => '}',
			'min' => 2,
			'max' => 3,
			'names' => array(
				2 => self::TEMPLATE,
				3 => self::TEMPLATE_PARAM,
			),
		),
	);
	
	/**
	 *
	 */
	static public function getStartChars()
	{
		return array_keys(self::$rules);
	}
	
	/**
	 *
	 */
	static public function parseStart($parser, $curChar, &$text, &$i)
	{
		$rule = self::$rules[$curChar];
			
		$len = strspn($text, $curChar, $i);

		if ($len >= $rule['min'])
		{
			$i += $len;
			return new self($parser, $curChar, $len);
		}
		else
			return false;
	}
	
	protected $char;
	protected $len;
	protected $rule;
	protected $type;
	
	/**
	 *
	 */
	public function __construct(Wiki_Parser $parser, $char, $len)
	{
		$this->parser = $parser;
		
		$this->rule = self::$rules[$char];
		$this->char = $char;
		$this->len = $len;
		$this->is_complete = false;
	}
	
	/**
	 *
	 */
	public function getCloseChar()
	{
		return $this->rule['close'];
	}
	
	/**
	 * 
	 */
	public function getWantedChars()
	{
		if ($this->char == '{')
			return array('|' => self::PARAM_SEP, '=' => self::NAME_SEP, ':' => self::FUNC_SEP);
		else
			return array('|' => self::PARAM_SEP, '=' => self::NAME_SEP);
	}
	
	
	
	/**
	 *
	 */
	public function checkEnd($parser, &$stack, $curChar, &$text, &$i)
	{
		$maxLen = $this->len;
		$len = strspn($text, $curChar, $i, $maxLen);

		if ($len > $this->rule['max'])
			$matchLen = $this->rule['max'];
		else
		{
			$matchLen = $len;

			while ($matchLen > 0 && !isset($this->rule['names'][$matchLen]))
				$matchLen--;
		}

		if ($matchLen <= 0)
		{
			$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($curChar, $len));
			$i += $len;
			
			return false;
		}
		
		// There's still opening tags left to search end for
		if ($matchLen < $this->len)
		{
			$open = $this->len - $matchLen;
			$element->modifyLen($matchLen);
			
			// Nested tag?
			if ($open >= $this->rule['min'])
				$target = new self($this->parser, $curChar, $open);
			// or just unnecassary character?
			else
			{
				$target = array_pop($stack);
				$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($element->char, $open));
			}
		}
		else
			$target = array_pop($stack);
		
		if (!$this->createElement($target))
			return false;

		$i += $matchLen;
		
		return $target;
	}
	
	/**
	 *
	 */
	public function forceEnd($parser, $target)
	{
		$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($this->char, $this->len));
		$this->throwContentTo($target);
		//$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($curChar, $matchLen));
	}
	
	/**
	 *
	 */
	function createElement($target)
	{
		global $context;
		
		$param = 0;
		$param_name = 0;
		$has_name = false;
		$found_semicolon = false;
		
		$params = array();

		$type = $this->rule['names'][$this->len];

		foreach ($this->content as $c)
		{
			switch ($c['type'])
			{
				case self::NAME_SEP:
					if (!$has_name)
					{
						$param_name = Wiki_Parser_Core::toText($params[$param]);
						unset($params[$param]);
						$params[$param_name] = array();
						$has_name = true;
					}
					else
					{
						$params[$param_name][] = $c;
					}
					break;

				case self::PARAM_SEP:
					$param++;
					$param_name = $param;
					$has_name = false;
					break;

				case self::FUNC_SEP:
					// {{DISPLAYTITLE:My Display Title}}
					if (!$found_semicolon && $this->rule['close'] == '}' && $this->len == 2 && $param == 0 && isset($params[0]))
					{
						$page = Wiki_Parser_Core::toText($params[0]);

						if ($page[0] == '#' || WikiExtension::isFunction($page))
						{
							$type = WikiElement_Parser::FUNC;
							$param++;
							$param_name = $param;
							$has_name = false;
							$found_semicolon = true;
						}
						elseif (WikiExtension::variableExists($page))
						{
							$type = WikiElement_Parser::VARIABLE;
							$param++;
							$param_name = $param;
							$has_name = false;
							$found_semicolon = true;
						}
						else
						{
							$c['type'] = Wiki_Parser_Core::TEXT;
							$params[$param_name][] = $c;
						}
					}
					else
						$params[$param_name][] = $c;
					break;

				default:
					$params[$param_name][] = $c;
					break;
			}
		}

		// Template might not actually be actual template but function or variable
		if ($type == WikiElement_Parser::TEMPLATE)
		{
			$page = Wiki_Parser_Core::toText($params[0]);

			 if ($page[0] == '#')
				$type = WikiElement_Parser::FUNC;
			elseif (WikiExtension::isFunction($page))
				$type = WikiElement_Parser::FUNC;
			elseif (WikiExtension::variableExists($page))
				$type = WikiElement_Parser::VARIABLE;
		}

		// Wikilink
		if ($type == WikiElement_Parser::WIKILINK)
		{
			$parsedPage = Wiki_Parser_Core::toText(array_shift($params));
			$force_link = false;

			if ($parsedPage[0] == ':')
			{
				$parsedPage = substr($parsedPage, 1);
				$force_link = true;
			}

			list ($linkNamespace, $linkPage) = wiki_parse_url_name($parsedPage, true);
			$link_info = cache_quick_get('wiki-pageinfo-' .  wiki_cache_escape($linkNamespace, $linkPage), 'Subs-Wiki.php', 'wiki_get_page_info', array($linkPage, $context['namespaces'][$linkNamespace]));

			if (!$force_link && $linkNamespace == $context['namespace_category']['id'])
			{
				$this->wikiparser->page->addCategory($link_info);
			}
			else
			{
				$target->throwContent(Wiki_Parser_Core::ELEMENT, new WikiLink($this->parser, $link_info, $params), $this->getUnparsed());
			}
		}
		// Function
		elseif ($type == WikiElement_Parser::FUNC)
		{
			$function = Wiki_Parser_Core::toText(array_shift($params));
			$unparsed = $this->getUnparsed();

			if ($function[0] == '#')
				$function = substr($function, 1);
			
			$value = WikiExtension::getFunction($function);

			if (isset($value['callback']))
				call_user_func($value['callback'], $target, $params);
		   else
				$target->throwContent(Wiki_Parser_Core::WARNING, 'unknown_function', $this->getUnparsed(), array($function));
		}
		// Template
		elseif ($type == WikiElement_Parser::TEMPLATE)
		{
			$page = Wiki_Parser_Core::toText(array_shift($params));

			if (strpos($page, ':') === false)
				$namespace = 'Template';
			else
				list ($namespace, $page) = wiki_parse_url_name($page, true);

			$template = cache_quick_get('wiki-pageinfo-' .  wiki_cache_escape($namespace, $page), 'Subs-Wiki.php', 'wiki_get_page_info', array($page, $context['namespaces'][$namespace]));

			if ($template->exists)
			{
				$raw_content = wiki_get_page_raw_content($template);				

				$template_parser = new Wiki_Parser($this->wikiparser->page, $params, true, true);
				$template_parser->parseTo($target, $raw_content);
				unset($template_parser);
			}
			else
				$target->throwContent(Wiki_Parser_Core::WARNING, 'template_not_found', $this->getUnparsed(), array(wiki_get_url_name($page, $namespace)));
		}
		// Template parameter
		elseif ($type == WikiElement_Parser::TEMPLATE_PARAM)
		{
			$variable = Wiki_Parser_Core::toText(array_shift($params), true);
			$unparsed = $this->getUnparsed();

			// Get variable
			if (count($params) == 0)
			{
				if (is_numeric($variable))
					$variable--;
				
				if (isset($this->wikiparser->parameters[$variable]))
				{
					$value = $this->wikiparser->parameters[$variable];

					if ($value === false)
						$target->throwContent(Wiki_Parser_Core::WARNING, 'unknown_variable', $unparsed, array($variable));
					elseif (is_string($value))
						$target->throwContent(Wiki_Parser_Core::TEXT, $value, $unparsed);
					else
						$target->throwContentArray($value);

				}
				else
					$target->throwContent(Wiki_Parser_Core::WARNING, 'unknown_variable', $unparsed, array($variable));
			}
			else
				$target->throwContent(Wiki_Parser_Core::WARNING, 'unknown_variable', $unparsed, array($variable));
		}
		// Variable
		elseif ($type == WikiElement_Parser::VARIABLE)
		{
			$variable = Wiki_Parser_Core::toText(array_shift($params), true);
			$unparsed = $this->getUnparsed();
			
			// Get variable
			$value = WikiExtension::getVariable($variable);

			if ($value === false && count($params) !== 0)
				$target->throwContent(Wiki_Parser_Core::WARNING, 'unknown_variable', $unparsed);
			elseif ($value === false && count($params) == 1)
				$this->wikiparser->page->variables[$variable] = Wiki_Parser_Core::toText($params[0]);
			elseif ($value !== false)
				$target->throwContent(Wiki_Parser_Core::ELEMENT, new WikiVariable($target, $value['callback'], $params), $unparsed);
			else
				$target->throwContent(Wiki_Parser_Core::WARNING, 'unknown_variable', $unparsed);
		}
		
		return true;
	}
	
	/**
	 * Sets lenght of start tag to actual lenght if it wasn't expected lenght.
	 * @param int $lenght Actual lenght of start tag
	 */
	public function modifyLen($lenght)
	{
		$this->len = $lenght;
	}
}