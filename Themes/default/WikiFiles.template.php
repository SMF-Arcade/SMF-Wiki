<?php
/**
 * 
 *
 * @package SMFWiki
 * @version 0.3
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 */

function template_wiki_file_upload()
{
	global $context, $txt;

	echo '
	<script language="JavaScript" type="text/javascript"><!-- // --><![CDATA[
		function saveEntities()
		{
			var textFields = ["', $context['post_box_name'], '"];
			for (i in textFields)
				if (document.forms.uploadfile.elements[textFields[i]])
					document.forms.uploadfile[textFields[i]].value = document.forms.uploadfile[textFields[i]].value.replace(/&#/g, "&#38;#");
			for (var i = document.forms.uploadfile.elements.length - 1; i >= 0; i--)
				if (document.forms.uploadfile.elements[i].name.indexOf("options") == 0)
					document.forms.uploadfile.elements[i].value = document.forms.uploadfile.elements[i].value.replace(/&#/g, "&#38;#");
		}
	// ]]></script>
	<form action="', $context['current_page_url'], '" method="post" accept-charset="', $context['character_set'], '" name="uploadfile" id="uploadfile" onsubmit="submitonce(this);saveEntities();" enctype="multipart/form-data">
		<table cellpadding="4" width="95%">
			<tr>
				<td align="right" width="25%"></td>
				<td><input type="file" size="48" name="file" /></td>
			</tr><tr>
				<td valign="top" align="right" width="25%">', $txt['file_description'], '</td>
				<td>
					<div id="bbcBox_message"></div>
					<div id="smileyBox_message"></div>
					', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
				</td>
			</tr>
		</table>
		<div style="text-align: center">
			<span class="smalltext"><br />', $txt['shortcuts'], '</span><br />
			', template_control_richedit_buttons($context['post_box_name']), '
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';
}

?>