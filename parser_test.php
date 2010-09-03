<?php

$db_show_debug=true;
require_once('./SSI.php');
require_once($sourcedir . '/Wiki.php');

loadWiki();

$tests = array(
"11o1oob<br />
flfafa<br />
== Level 2 ==<br />
level 2<br />
=== Level 3 ===<br /><br />
level 3<br>
{{:Main_Page|wikiversion=1}}<br /><br />
{{{1}}}<br/><br />
{{DISPLAYTITLE:aaa}}<br /><br />[[Main_Page]]. [[Main_Page|click here]]"
);

foreach ($tests as $test)
{
	$page = new WikiPage(array('id' => ''), 'test_page');
	$parser = new WikiParser($page);

	echo '
	<h2>Org</h2>
	<pre>', $test, '</pre>
	<h2>Parsed</h2>
	<pre>', var_dump($parser->parse(str_replace(array("\r","\n"), '', $test))), '</pre>';
;
}

?>