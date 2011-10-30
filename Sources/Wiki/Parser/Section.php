<?php
/**
 * 
 *
 * @package SMFWiki
 * @subpackage Element
 * @version 0.3
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 */

/**
 *
 */
class Wiki_Parser_Section
{
	/**
	 * @var Wiki_Parser
	 */
	protected $parser;
	
	/**
	 * @var Wiki_Parser_ElementContainer
	 */
	protected $content = array();
	
	/**
	 *
	 */
	protected $id;
	
		
	/**
	 *
	 */
	protected $level;
	
	/**
	 *
	 */
	protected $name;
	
	/**
	 *
	 */
	protected $hasContent = false;

	/**
	 *
	 */
	protected $blockNestingLevel = 0;
	
	/**
	 *
	 */
	protected $paragraphOpen = false;
	
	/**
	 *
	 */
	public function __construct(Wiki_Parser $parser, $name, $level = 1, $id = null)
	{
		$this->parser = $parser;
		$this->level = 1;
		$this->id = $id === null ? Wiki_Parser_Core::html_id($name) : $id;
		$this->name = $name;
	}

	/**
	 *
	 *
	 */
	public function getID()
	{
		return $this->id;
	}
	
	/**
	 *
	 *
	 */
	public function getLevel()
	{
		return $this->level;
	}
	
	/**
	 *
	 *
	 */
	public function getName()
	{
		return $this->name;
	}
	
	/**
	 *
	 *
	 */
	public function finalize()
	{
		$this->throwContent(Wiki_Parser_Core::END_PARAGRAPH, '</p>', '');
	}
	
	/**
	 * Adds content to this section
	 */
	public function throwContent($type, $content = '', $unparsed = '', $additonal = array())
	{
		$i = count($this->content);

		if ($type == Wiki_Parser_Core::CONTROL_BLOCK_LEVEL_OPEN)
		{
			if ($this->blockNestingLevel == 0 && $this->paragraphOpen)
				$this->throwContent(Wiki_Parser_Core::END_PARAGRAPH, '</p>');

			$this->blockNestingLevel++;
			$this->hasContent = false;

			return;
		}
		elseif ($type == Wiki_Parser_Core::CONTROL_BLOCK_LEVEL_CLOSE)
		{
			$this->blockNestingLevel--;
			$this->hasContent = false;

			// Clean new lines
			$this->trimContent();

			return;
		}
		
		// Merge text's
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

		// Let's not start with new line		
		if ($type == Wiki_Parser_Core::NEW_LINE && (empty($this->content) || !$this->hasContent))
			return;

		if ($type == Wiki_Parser_Core::NEW_PARAGRAPH)
		{
			if (!$this->hasContent || $this->paragraphOpen != false || $this->blockNestingLevel != 0)
				return;
		}
		elseif ($type == Wiki_Parser_Core::END_PARAGRAPH)
		{
			if (!$this->paragraphOpen)
				return;
			
			$this->paragraphOpen = false;
			$this->hasContent = false;
		}
		elseif ($this->parser->parse_bbc && ($this->paragraphOpen == false && $this->blockNestingLevel == 0
				&& ($type == Wiki_Parser_Core::TEXT || ($type == Wiki_Parser_Core::ELEMENT && !$content->is_block_level()))))
		{
			$this->content[$i++] = array(
				'type' => Wiki_Parser_Core::NEW_PARAGRAPH,
				'content' => '<p>',
				'unparsed' => '',
				'additional' => array(),
			);
			
			$this->paragraphOpen = true;
			$this->hasContent = false;
		}
		
		$this->content[$i] = array(
			'type' => $type,
			'content' => $content,
			'unparsed' => $unparsed,
			'additional' => $additonal,
		);

		if ($type == Wiki_Parser_Core::TEXT || $type == Wiki_Parser_Core::ELEMENT)
			$this->hasContent = true;
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
	 * Trim
	 */
	protected function trimContent()
	{
		while ($this->content[count($this->content) - 1]['type'] == Wiki_Parser_Core::NEW_LINE)
			unset($this->content[count($this->content)]);
	}
	
	/**
	 *
	 */
	public function getContent()
	{
		return $this->content;
	}
	
	/**
	 * Clean up after parsin complete
	 */
	public function ParseFinalize()
	{
		$this->parser = null;
		
		/*foreach ($this->content as $c)
			if ($c['type'] == Wiki_Parser_Core::ELEMENT)
				$c['content']->ParseFinalize();*/
	}
}

?>