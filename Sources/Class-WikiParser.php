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

		
		//else
		//	die('NOT IMPLEMENTED!' . $type);
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
	
	function __construct(Wiki_Parser $wikiparser, WikiPage $link_info, $params)
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