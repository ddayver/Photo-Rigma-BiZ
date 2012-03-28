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
<!-- IF_NEED_FIND_BEGIN -->
											<form name="search" action="" method="post">
												<table border="0" width="100%" cellspacing="0" cellpadding="0" class="category_center">
													<tr>
														<td>{L_SEARCH_USER}</td>
													</tr>
													<tr>
														<td><input class="form" size="50" type="text" name="search_user" value="{D_SEARCH_USER}"/></td>
													</tr>
													<tr>
														<td class="regist">
															<hr class="line">{L_HELP_SEARCH}<hr class="line">
														</td>
													</tr>
													<tr>
														<td><input name="submit" type="image" value="search_text" title="{L_SEARCH}" alt="{L_SEARCH}" src="{THEMES_PATH}img/submit_small_poisk.gif" align="absmiddle" /></td>
													</tr>
												</table>
											</form>
<!-- IF_NEED_USER_BEGIN -->
											<table cellpadding="0" cellspacing="0" border="0" width="100%" class="category_center">
												<tr>
													<td>
														{D_FIND_USER}
													</td>
												</tr>
											</table>
<!-- IF_NEED_USER_END -->
<!-- IF_NEED_FIND_END -->
<!-- IF_FIND_USER_BEGIN -->
											<form name="save_user" action="" method="post">
												<table border="0" cellspacing="1" cellpadding="0" class="news_data">
													<tr>
														<td width="50%">{L_LOGIN}:</td>
														<td width="50%">{D_LOGIN}</td>
													</tr>
													<tr>
														<td>{L_REAL_NAME}:</td>
														<td>{D_REAL_NAME}</td>
													</tr>
													<tr>
														<td>{L_EMAIL}:</td>
														<td>{D_EMAIL}</td>
													</tr>
													<tr>
														<td>{L_AVATAR}:</td>
														<td>
															<img src="{U_AVATAR}" title="{D_REAL_NAME}" alt="{D_REAL_NAME}" border="0" class="image" />
														</td>
													</tr>
													<tr>
														<td><b>{L_GROUP}*</b>:</td>
														<td>{D_GROUP}</td>
													</tr>
													<tr>
														<td colspan="2" class="category_center"><b>{L_USER_RIGHTS}</b></td>
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
															<hr class="line"><b>*</b> {L_HELP_EDIT}<hr class="line">
														</td>
													</tr>
													<tr>
														<td class="regist" colspan="2">
															<input name="submit" type="image" value="{L_SAVE_USER}" alt="{L_SAVE_USER}" title="{L_SAVE_USER}" src="{THEMES_PATH}img/submit_small_save.gif" align="absmiddle" />
														</td>
													</tr>
												</table>
											</form>
<!-- IF_FIND_USER_END -->
										</div>
									</td>
									<td class="regist"></td>
								</tr>
							</table>