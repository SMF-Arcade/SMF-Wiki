<?php
// Version: 0.1; WikiFiles

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
					document.forms.uploadfile[textFields[i]].value = document.forms.editarticle[textFields[i]].value.replace(/&#/g, "&#38;#");
			for (var i = document.forms.uploadfile.elements.length - 1; i >= 0; i--)
				if (document.forms.uploadfile.elements[i].name.indexOf("options") == 0)
					document.forms.uploadfile.elements[i].value = document.forms.editarticle.elements[i].value.replace(/&#/g, "&#38;#");
		}
	// ]]></script>
	<form action="', $context['current_page_url'], '" method="post" accept-charset="', $context['character_set'], '" name="uploadfile" id="uploadfile" onsubmit="submitonce(this);saveEntities();" enctype="multipart/form-data">
		<div class="tborder">
			<h3 class="catbg headerpadding">', $txt['wiki_upload_file'], '</h3>
			<div class="windowbg2 smallpadding">
				<table cellpadding="4" width="100%">
					<tr>
						<td align="right" width="25%"></td>
						<td><input type="file" size="48" name="file" /></td>
					</tr><tr>
						<td align="right" width="25%">', $txt['file_description'], '</td>
						<td>
							<div>
								', template_control_richedit($context['post_box_name'], 'bbc'), '
							</div>
							<div>
								', template_control_richedit($context['post_box_name'], 'smileys'), '<br />
							</div>
							<div>
								', template_control_richedit($context['post_box_name'], 'message'), '
							</div>
						</td>
					</tr>
				</table>
			</div>
			<div style="text-align: center">
				<span class="smalltext"><br />', $txt['shortcuts'], '</span><br />
				', template_control_richedit($context['post_box_name'], 'buttons'), '
			</div>
		</div>

		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';
}

?>