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
	const P_SEP = 1;

	/**
	 *
	 */
	const N_SEP = 2;

	/**
	 *
	 */
	const F_SEP = 3;
	
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
			return array('|' => self::P_SEP, '=' => self::N_SEP, ':' => self::F_SEP);
		else
			return array('|' => self::P_SEP, '=' => self::N_SEP);
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
		
		if (!$this->createElement())
		{
			$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($this->char, $this->len));
			$this->fail($target);
			$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($curChar, $matchLen));
		}

		$i += $matchLen;
		
		return $target;
	}
	
	/**
	 *
	 */
	function createElement()
	{
		return false;
	}
}