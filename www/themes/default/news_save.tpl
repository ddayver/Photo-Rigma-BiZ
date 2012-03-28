
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="news_data"></td>
									<td class="category_center" align="center">
										<form action="{U_SAVE_NEWS}" method="post" name="news">
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
<!-- IF_NEED_USER_BEGIN -->
												<tr>
													<td class="category_center">{L_NAME_USER}: {D_NAME_USER}</td>
												</tr>
<!-- IF_NEED_USER_END -->
												<tr>
													<td class="category_center">{L_NAME_POST}<br /><input class="form_textarea" name="name_post" type="text" size="50" maxlength="50" value="{D_NAME_POST}"/></td>
												</tr>
												<tr>
													<td class="category_center">{L_TEXT_POST}<br /><textarea class="form_textarea" name="text_post" rows="30" cols="50">{D_TEXT_POST}</textarea></td>
												</tr>
												<tr>
													<td class="category_center"><input name="submit" type="image" value="{L_SAVE_NEWS}" alt="{L_SAVE_NEWS}" title="{L_SAVE_NEWS}" src="{THEMES_PATH}img/submit_small_save.gif" align="absmiddle" /></td>
												</tr>
											</table>
										</form>
									</td>
									<td class="news_data"></td>
								</tr>
							</table>