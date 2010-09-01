<?php

$db_show_debug=true;
require_once('./SSI.php');
require_once($sourcedir . '/Wiki.php');

loadWiki();

$tests = array(
"11o1oob<br />
flfafa<br />
== Level 2 ==<br />
level 2 content line 1.<br />
level 2 content line 2.<br /><br />baat<br /><br /><br />aa
level 2 content line 3.<br />
level 2 content line 4.<br />
=== Level 3 ===<br />
[[WikiLink]].<br />
level 2 content line 5.<br />
<nowiki>line 6</nowiki></nowiki>
<nowiki>broken no wiki tag this should parse normally<br /><br />
{mr.brackets}
{{wikiversion}}

second paragraph<br /><br/>third<br />line 2 of third<br />
===broken header===
This tag is not closed: [[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa[[daa"
);

foreach ($tests as $test)
{
	$page = new WikiPage(array('id' => ''), 'test_page');
	$parser = new WikiParser($page);

	echo '
	<h2>Org</h2>
	<pre>', $test, '</pre>
	<h2>Parsed</h2>
	<pre>', var_dump($parser->parse(str_replace("\r\n", '', $test))), '</pre>
	<h2>TOC</h2>
	<pre>', var_dump($parser->tableOfContents), '</pre>';
;
}

?>