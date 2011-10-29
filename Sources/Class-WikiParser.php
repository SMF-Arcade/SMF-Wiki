<?php
/**
 * Contains classes related to parsing of wiki page
 *
 * @package parser
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.2
 */


/**
 * Parser for Square brackets, curly bracets and hash tags
 */
class WikiElement_Parser
{
	const WIKILINK = 1;
	const TEMPLATE = 2;
	const TEMPLATE_PARAM = 3;
	const HASHTAG = 4;
	const FUNC = 5;
	const VARIABLE = 6;
	
	static public $rules = array(
		'[' => array(
			'close' => ']',
			'min' => 2,
			'max' => 2,
			'names' => array(
				2 => WikiElement_Parser::WIKILINK,
			),
		),
		'{' => array(
			'close' => '}',
			'min' => 2,
			'max' => 3,
			'names' => array(
				2 => WikiElement_Parser::TEMPLATE,
				3 => WikiElement_Parser::TEMPLATE_PARAM,
			),
		),
	);
	
	public $char;
	public $len;
	public $rule;
	
	public $type;
	
	private $content;
	private $is_complete;
	
	private $wikiparser;
	
	public function __construct(Wiki_Parser $wikiparser, $char, $len)
	{
		$this->rule = WikiElement_Parser::$rules[$char];
		$this->char = $char;
		$this->len = $len;
		$this->is_complete = false;
		$this->wikiparser = $wikiparser;
		
		$this->throwContent(Wiki_Parser_Core::ELEMENT_OPEN, '', str_repeat($char, $len));
	}
	
	/**
	 * Adds content to this tag
	 */
	public function throwContent($type, $content = '', $unparsed = null, $additonal = array())
	{
		$i = count($this->content);
		
		if ($i > 0 && $type == Wiki_Parser_Core::TEXT && $this->content[$i - 1]['type'] == Wiki_Parser_Core::TEXT && empty($this->content[$i - 1]['additional']) && empty($additonal))
		{
			$this->content[$i - 1]['content'] .= $content;
			
			// Does this part have "unparsed" content?
			if (!empty($this->content[$i - 1]['unparsed']))
				$this->content[$i - 1]['unparsed'] .= empty($unparsed) ? $content : $unparsed;
			// Copy parsed as unparsed if we have but there is none. Done to save memory!
			elseif (empty($this->content[$i - 1]['unparsed']) && !empty($unparsed))
				$this->content[$i - 1]['unparsed'] = $this->content[$i - 1]['content'] . $unparsed;
				
			return;
		}
		
		$this->is_complete = $type == Wiki_Parser_Core::ELEMENT_CLOSE;
		
		$this->content[$i] = array(
			'type' => $type,
			'content' => $content,
			'unparsed' => $unparsed,
			'additional' => $additonal,
		);
	}

	/**
	 *
	 */
	public function throwContentArray($array)
	{
		foreach ($array as $cont)
			$this->throwContent($cont['type'], $cont['content'], $cont['unparsed'], $cont['additional']);
	}

	/**
	 * Return original unparsed content
	 */
	public function getUnparsed()
	{
		$return = '';
		foreach ($this->content as $c)
			$return .= !empty($c['unparsed'])? $c['unparsed'] : $c['content'];
		return $return;
	}
	
	/**
	 * Adds content to upper level element.
	 */
	public function throwContentTo($target)
	{
		global $context;
		
		// If this is incomplete throw as original
		if (!$this->is_complete)
		{
			foreach ($this->content as $c)
			{
				if ($c['type'] == Wiki_Parser_Core::ELEMENT_OPEN)
					$target->throwContent(Wiki_Parser_Core::TEXT, $c['unparsed']);			
				else
					$target->throwContent(
						$c['type'],
						$c['content'],
						$c['unparsed'],
						$c['additional']
					);
			}

			return;
		}

		// If it's complete then we can parse it
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
				case Wiki_Parser_Core::ELEMENT_OPEN:
				case Wiki_Parser_Core::ELEMENT_CLOSE:
					break;

				case Wiki_Parser_Core::ELEMENT_PARAM_NAME:
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

				case Wiki_Parser_Core::ELEMENT_NEW_PARAM:
					$param++;
					$param_name = $param;
					$has_name = false;
					break;

				case Wiki_Parser_Core::ELEMENT_SEMI_COLON:
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
				$target->throwContent(Wiki_Parser_Core::ELEMENT, new WikiLink($this->wikiparser, $link_info, $params), $this->getUnparsed());
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
		else
			die('NOT IMPLEMENTED!' . $type);
	}
	
	/**
	 * Sets lenght of start tag to actual lenght if it wasn't expected lenght.
	 * @param int $lenght Actual lenght of start tag
	 */
	public function modifyLen($lenght)
	{
		$this->len = $lenght;
		$this->content[0]['unparsed'] = str_repeat($this->char, $lenght);
	}
}

/**
 *
 */
class WikiList_Parser
{
	static $listTypes = array('#', '*');
	static $listTags = array('#' => 'ol', '*' => 'ul');

	public $prefix;
	public $type;

	private $content;
	private $wikiparser;

	private $is_complete = true;

	public function __construct(Wiki_Parser $wikiparser, $type, $prefix = '')
	{
		$this->prefix = $prefix;
		$this->type = $type;
		$this->wikiparser = $wikiparser;

		//$this->throwContent(Wiki_Parser_Core::ELEMENT_OPEN, '', str_repeat($char, $len));
	}

	/**
	 * Adds content to this tag
	 */
	public function throwContent($type, $content = '', $unparsed = null, $additonal = array())
	{
		$i = count($this->content);

		if ($i > 0 && $type == Wiki_Parser_Core::TEXT && $this->content[$i - 1]['type'] == Wiki_Parser_Core::TEXT && empty($this->content[$i - 1]['additional']) && empty($additonal))
		{
			$this->content[$i - 1]['content'] .= $content;

			// Does this part have "unparsed" content?
			if (!empty($this->content[$i - 1]['unparsed']))
				$this->content[$i - 1]['unparsed'] .= empty($unparsed) ? $content : $unparsed;
			// Copy parsed as unparsed if we have but there is none. Done to save memory!
			elseif (empty($this->content[$i - 1]['unparsed']) && !empty($unparsed))
				$this->content[$i - 1]['unparsed'] = $this->content[$i - 1]['content'] . $unparsed;

			return;
		}

		// At least two lines is required for element to be complete
		//$this->is_complete = $type == Wiki_Parser_Core::LIST_ITEM_OPEN;

		$this->content[$i] = array(
			'type' => $type,
			'content' => $content,
			'unparsed' => $unparsed,
			'additional' => $additonal,
		);
	}

	/**
	 *
	 */
	public function throwContentArray($array)
	{
		foreach ($array as $cont)
			$this->throwContent($cont['type'], $cont['content'], $cont['unparsed'], $cont['additional']);
	}

	/**
	 * Return original unparsed content
	 */
	public function getUnparsed()
	{
		$return = '';
		foreach ($this->content as $c)
			$return .= !empty($c['unparsed'])? $c['unparsed'] : $c['content'];
		return $return;
	}

	/**
	 * Adds content to upper level element.
	 */
	public function throwContentTo($target)
	{
		global $context;

		// If this is incomplete throw as original
		if (!$this->is_complete)
		{
			foreach ($this->content as $c)
			{
				if ($c['type'] == Wiki_Parser_Core::LIST_ITEM_OPEN)
					$target->throwContent(Wiki_Parser_Core::TEXT, $c['unparsed']);
				elseif ($c['type'] == Wiki_Parser_Core::LIST_ITEM_CLOSE || $c['type'] == Wiki_Parser_Core::LIST_OPEN || $c['type'] == Wiki_Parser_Core::LIST_CLOSE)
					continue;
				else
					$target->throwContent(
						$c['type'],
						$c['content'],
						$c['unparsed'],
						$c['additional']
					);
			}

			return;
		}

		$target->throwContent(Wiki_Parser_Core::CONTROL_BLOCK_LEVEL_OPEN);
		$target->throwContent(Wiki_Parser_Core::LIST_OPEN, '<' . WikiList_Parser::$listTags[$this->type] . '>', $this->type);

		foreach ($this->content as $c)
		{
			/*if ($c['type'] == Wiki_Parser_Core::LIST_ITEM_OPEN)
				$target->throwContent(Wiki_Parser_Core::TEXT, $c['unparsed']);
			elseif ($c['type'] == Wiki_Parser_Core::LIST_ITEM_CLOSE || $c['type'] == Wiki_Parser_Core::LIST_OPEN || $c['type'] == Wiki_Parser_Core::LIST_CLOSE)
				continue;
			else*/
				$target->throwContent(
					$c['type'],
					$c['content'],
					$c['unparsed'],
					$c['additional']
				);
		}

		$target->throwContent(Wiki_Parser_Core::LIST_CLOSE, '</' . WikiList_Parser::$listTags[$this->type] . '>', '');
		$target->throwContent(Wiki_Parser_Core::CONTROL_BLOCK_LEVEL_CLOSE);
	}

	/**
	 * Sets lenght of start tag to actual lenght if it wasn't expected lenght.
	 * @param int $lenght Actual lenght of start tag
	 */
	public function modifyLen($lenght)
	{
		$this->len = $lenght;
		$this->content[0]['unparsed'] = str_repeat($this->char, $lenght);
	}
}

/**
 * WikiElement base class
 */
abstract class WikiElement
{
	/**
	 * Tell if Element is block level (for example <div>)
	 */
	abstract function is_block_level();

	/**
	 * Returns html to display element
	 */
	abstract function getHtml();
}

/**
 * Represents wikilink like [[Main_Page|link text]]
 */
class WikiLink extends WikiElement
{
	private $link_info;
	private $linkNamespace;
	
	private $link;
	private $linkText;
	
	private $params;
	
	private $html = '';
	private $is_block_level = false;
	
	function __construct(Wikiparser $wikiparser, WikiPage $link_info, $params)
	{
		global $context;
		
		$this->link = wiki_get_url_name($link_info->page, $link_info->namespace['id']);
		
		$this->link_info = $link_info;
		
		if (isset($params[0]))
		{
			$this->linkText = Wiki_Parser_Core::toText($params[0]);
			unset($params[0]);
		}
		else
			$this->linkText = $this->link_info->title;
			
		$this->params = $params;

		if ($link_info->namespace['id'] == $context['namespace_images']['id'] && $this->link_info->exists)
		{
			if (!empty($this->params))
			{
				$align = '';
				$size = '';
				$caption = '';
				$alt = '';
				$link = wiki_get_url($this->link);

				// Size
				if (!empty($this->params[1]))
				{
					$size = Wiki_Parser_Core::toText($this->params[1]);

					if ($size == 'thumb')
						$size = ' width="180"';
					elseif (is_numeric($size))
						$size = ' width="' . $size . '"';
					elseif (strpos($size, 'x') !== false)
					{
						list ($width, $height) = explode('x', $size, 2);

						if (is_numeric($width) && is_numeric($height))
							$size = ' width="' . $width . '" height="' . $height. '"';
					}
					else
						$size = '';
				}

				// Align
				if (!empty($this->params[2]))
				{
					$align = trim(Wiki_Parser_Core::toText($this->params[1]));
					$align = ($align == 'left' || $align == 'right') ? $align : '';
				}

				// Alt
				if (!empty($this->params[3]))
					$alt = Wiki_Parser_Core::toText($this->params[2]);

				// Caption
				if (!empty($this->params[4]))
					$alt = Wiki_Parser_Core::toText($this->params[3]);

				// Link
				if (isset($this->params['link']))
				{
					$link = Wiki_Parser_Core::toText($this->params['link']);
					if (!empty($link) && substr(0,7, $link) !== 'http://')
						$link = wiki_get_url($this->params['link']);
				}

				if (!empty($align) || !empty($caption))
				{
					$style = array();
					$class = array();

					if (!empty($align))
					{
						$style[] = 'float: ' . $align;
						$style[] = 'clear: ' . $align;
					}

					$this->is_block_level = true;

					$this->html = '<div' . (!empty($class) ? ' class="' . implode(' ', $class) . '"' : '') . (!empty($style) ? ' style="' . implode('; ', $style) . '"' : '') . '>
						<span class="topslice"><span></span></span>
						<div style="padding: 5px">';

				}

				$this->html .= (!empty($link) ? '<a href="' . $link . '">' : '') . '<img src="' . wiki_get_url(array('page' => $this->link, 'image')) . '" alt="' . $alt . '"' . (!empty($caption) ? ' title="' . $caption . '"' : '') . $size . ' />' . (!empty($link) ? '</a>' : '');

				if (!empty($align) || !empty($caption))
					$this->html .= (!empty($caption) ? '<span style="text-align: center">' . $caption . '</span>' : '') . '
						</div>
						<span class="botslice"><span></span></span>
					</div>';
			}
			else
				$this->html .= '<a href="' . wiki_get_url($this->link) . '"><img src="' . wiki_get_url(array('page' => $this->link, 'image')) . '" alt="" /></a>';
		}
		else
		{
			$class = array();

			if (!$this->link_info->exists)
				$class[] = 'redlink';
			if ($this->link_info->url_name == $context['current_page_name'])
				$class[] = 'current_page';

			$this->html .= '<a href="' . wiki_get_url($this->link) . '"' . (!empty($class) ? ' class="'. implode(' ', $class) . '"' : '') . '>' . $this->linkText . '</a>';
		}
	}

	/**
	 * Returns html code for this element
	 * @return string html for this element
	 */
	function getHtml()
	{
		return $this->html;
	}

	/**
	 * Returns if this is block level tag
	 * @return boolean
	 */
	function is_block_level()
	{
		return $this->is_block_level;
	}
}

/**
 * Represents XML Tags like <my_tag></my_tag>
 */
class WikiTag extends WikiElement
{
	public $tag;
	public $attributes;
	public $content;
	public $html;
	
	function __construct($tag, $attributes, $content)
	{
		$this->tag = $tag;
		$this->attributes = $attributes;
		$this->content = $content;
	}

	/**
	 * Returns html code for this element
	 * @return string html for this element
	 */
	function getHtml()
	{
		die('not implemented');
	}

	/**
	 * Returns if this is block level tag
	 * @return boolean
	 */
	function is_block_level()
	{
		die('not implemented');
	}
}

/**
 * Represents template variables like {{{1}}}
 */
class WikiVariable extends WikiElement
{
	var $wikiparser;
	var $callback;
	var $params;
	var $value;

	function __construct($wikiparser, $callback, $params)
	{
		$this->wikiparser = $wikiparser;
		$this->callback = $callback;
		$this->params = $params;
		$this->value = call_user_func($this->callback, $this->wikiparser, $this->params);
	}

	/**
	 *
	 * @return mixed
	 */
	function getValue()
	{
		return $this->value;
	}

	/**
	 * Returns html code fir this element
	 * @return string html for this element
	 */
	function getHtml()
	{
		$value = $this->getValue();

		return is_string($value) ? $value : '';
	}
	
	function toBoolean()
	{
		return !empty($this->value);
	}

	/**
	 * Returns if this is block level tag
	 * @return boolean
	 * @todo implemnet actual code
	 */
	function is_block_level()
	{
		return false;
	}
}

?>