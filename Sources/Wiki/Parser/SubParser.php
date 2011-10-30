<?php
/**
 *
 */

/**
 * 
 */
abstract class Wiki_Parser_SubParser
{
	/**
	 * @var Wiki_Parser
	 */
	protected $parser;

	/**
	 *
	 */
	protected $content = array();
	
	/**
	 *
	 */
	static public function getStartChars()
	{
		trigger_error('getStartChars() not implemented!', E_USER_ERROR);
	}
	
	/**
	 *
	 */
	static public function parseStart($parser, $curChar, &$text, &$i)
	{
		trigger_error('parseStart() not implemented!', E_USER_ERROR);
	}
	
	/**
	 *
	 */
	abstract function getCloseChar();
	
	/**
	 *
	 */
	abstract function getWantedChars();

	/**
	 *
	 */
	abstract function checkEnd($parser, &$stack, $curChar, &$text, &$i);

	/**
	 *
	 */
	abstract function forceEnd($parser, $target);
	
	/**
	 * Adds content to this tag
	 */
	public final function throwContent($type, $content = '', $unparsed = null, $additonal = array())
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
	protected function throwContentTo($target)
	{
		global $context;

		foreach ($this->content as $c)
		{
			$target->throwContent(
				$c['type'],
				$c['content'],
				$c['unparsed'],
				$c['additional']
			);
		}
	}

	/**
	 *
	 */
	public final function throwContentArray($array)
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
}

?>