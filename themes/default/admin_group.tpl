							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="category_center"></td>
									<td class="category_center">
										<div align="center">
<!-- IF_SELECT_GROUP_BEGIN -->
											<form name="select" action="" method="post">
												<table border="0" width="100%" cellspacing="0" cellpadding="0" class="category_center">
													<tr>
														<td>{L_SELECT_GROUP}</td>
													</tr>
													<tr>
														<td>{D_GROUP}</td>
													</tr>
													<tr>
														<td><input name="submit" type="image" value="{L_EDIT}" title="{L_EDIT}" alt="{L_EDIT}" src="{THEMES_PATH}img/submit_small_edit.gif" align="absmiddle" /></td>
													</tr>
												</table>
											</form>
<!-- IF_SELECT_GROUP_END -->
<!-- IF_EDIT_GROUP_BEGIN -->
											<form name="save_group" action="" method="post">
												<table border="0" cellspacing="1" cellpadding="0" class="news_data">
													<tr>
														<td colspan="2" class="category_center"><b>{L_GROUP_RIGHTS}</b><input type="hidden" name="id_group" value="{D_ID_GROUP}" /></td>
													</tr>
													<tr>
														<td><b>{L_NAME_GROUP}</b>:</td>
														<td><input class="form_textarea" name="name_group" type="text" size="20" maxlength="50" value="{D_NAME_GROUP}"/></td>
													</tr>
													<tr>
														<td>{L_PIC_VIEW}:</td>
														<td><input type="checkbox" name="pic_view"{D_PIC_VIEW} /></td>
													</tr>
													<tr>
														<td>{L_PIC_RATE_USER}:</td>
														<td><input type="checkbox" name="pic_rate_user"{D_PIC_RATE_USER} /></td>
													</tr>
													<tr>
														<td>{L_PIC_RATE_MODER}:</td>
														<td><input type="checkbox" name="pic_rate_moder"{D_PIC_RATE_MODER} /></td>
													</tr>
													<tr>
														<td>{L_PIC_UPLOAD}:</td>
														<td><input type="checkbox" name="pic_upload"{D_PIC_UPLOAD} /></td>
													</tr>
													<tr>
														<td>{L_PIC_MODERATE}:</td>
														<td><input type="checkbox" name="pic_moderate"{D_PIC_MODERATE} /></td>
													</tr>
													<tr>
														<td>{L_CAT_MODERATE}:</td>
														<td><input type="checkbox" name="cat_moderate"{D_CAT_MODERATE} /></td>
													</tr>
													<tr>
														<td>{L_CAT_USER}:</td>
														<td><input type="checkbox" name="cat_user"{D_CAT_USER} /></td>
													</tr>
													<tr>
														<td>{L_COMMENT_VIEW}:</td>
														<td><input type="checkbox" name="comment_view"{D_COMMENT_VIEW} /></td>
													</tr>
													<tr>
														<td>{L_COMMENT_ADD}:</td>
														<td><input type="checkbox" name="comment_add"{D_COMMENT_ADD} /></td>
													</tr>
													<tr>
														<td>{L_COMMENT_MODERATE}:</td>
														<td><input type="checkbox" name="comment_moderate"{D_COMMENT_MODERATE} /></td>
													</tr>
													<tr>
														<td>{L_NEWS_VIEW}:</td>
														<td><input type="checkbox" name="news_view"{D_NEWS_VIEW} /></td>
													</tr>
													<tr>
														<td>{L_NEWS_ADD}:</td>
														<td><input type="checkbox" name="news_add"{D_NEWS_ADD} /></td>
													</tr>
													<tr>
														<td>{L_NEWS_MODERATE}:</td>
														<td><input type="checkbox" name="news_moderate"{D_NEWS_MODERATE} /></td>
													</tr>
													<tr>
														<td>{L_ADMIN}:</td>
														<td><input type="checkbox" name="admin"{D_ADMIN} /></td>
													</tr>
													<tr>
														<td class="regist" colspan="2">
															<input name="submit" type="image" value="{L_SAVE_GROUP}" alt="{L_SAVE_GROUP}" title="{L_SAVE_GROUP}" src="{THEMES_PATH}img/submit_small_save.gif" align="absmiddle" />
														</td>
													</tr>
												</table>
											</form>
<!-- IF_EDIT_GROUP_END -->
										</div>
									</td>
									<td class="regist"></td>
								</tr>
							</table>