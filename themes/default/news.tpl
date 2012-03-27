
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="news_data"></td>
									<td class="news_data">
										{L_NEWS_DATA}
<!-- IF_EDIT_SHORT_BEGIN -->
										<img border="0" src="{THEMES_PATH}img/b_edit.gif" title="{L_EDIT_BLOCK}" alt="{L_EDIT_BLOCK}" class="confirm_delete_photo" onclick="parent.location='{U_EDIT_BLOCK}';" />&nbsp;
										<img border="0" src="{THEMES_PATH}img/b_drop.gif" class="confirm_delete_photo" onclick="if (confirm('{L_CONFIRM_DELETE_BLOCK}?')) { parent.location='{U_DELETE_BLOCK}'; }" title="{L_DELETE_BLOCK}" alt="{L_DELETE_BLOCK}">
<!-- IF_EDIT_SHORT_END -->
<!-- IF_EDIT_LONG_BEGIN -->
										<br /><img border="0" src="{THEMES_PATH}img/submit_small_edit.gif" title="{L_EDIT_BLOCK}" alt="{L_EDIT_BLOCK}" class="confirm_delete_photo" onclick="parent.location='{U_EDIT_BLOCK}';" />&nbsp;
										<img border="0" src="{THEMES_PATH}img/submit_small_udalit.gif" class="confirm_delete_photo" onclick="if (confirm('{L_CONFIRM_DELETE_BLOCK}')) { parent.location='{U_DELETE_BLOCK}'; }" title="{L_DELETE_BLOCK}" alt="{L_DELETE_BLOCK}">
<!-- IF_EDIT_LONG_END -->
										<hr class="line">
									</td>
									<td class="news_data"></td>
								</tr>
								<tr>
									<td class="news"></td>
									<td class="news">
										{L_TEXT_POST}
									</td>
									<td class="news"></td>
								</tr>
							</table>