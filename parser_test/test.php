<?php

$db_show_debug=true;
require_once('../SSI.php');
require_once('parser.php');

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

second paragraph<br /><br/>third<br />line 2 of third<br />
===broken header==="
);

foreach ($tests as $test)
{
	$parser = new WikiParser(array());

	echo '
	<pre>', $test, '</pre>
	<pre>', var_dump($parser->parse(str_replace("\r\n", '', $test))), '</pre>';
;
}

?>