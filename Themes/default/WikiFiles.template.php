<?php
// Version: 0.1; WikiFiles

function template_wiki_file_upload()
{
	global $context, $txt;

	echo '
	<form action="', wiki_get_url($context['current_page_url']), '" method="post" accept-charset="', $context['character_set'], '" enctype="multipart/form-data">
		<div class="tborder">
			<h3 class="catbg headerpadding">', $txt['wiki_upload_file'], '</h3>
			<div class="windowbg2 smallpadding">
				<table cellpadding="4" width="100%">
					<tr>
						<td align="right" width="25%"></td>
						<td><input type="file" size="48" name="file" /></td>
					</tr><tr>
						<td align="right" width="25%">', $txt['file_description'], '</td>
						<td><textarea name="description"></textarea></td>
					</tr>
				</table>
			</div>
			<div class="windowbg2 smallpadding" style="text-align: right;">
				<input type="submit" name="submit_upload" value="', $txt['wiki_upload_button'], '" />
			</div>
		</div>
	</form>';
}

?>