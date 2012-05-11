							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="regist"></td>
									<td class="regist">
										<div align="center">
											<form action="{U_PROFILE_EDIT}" method="post" name="profile" enctype="multipart/form-data">
												<table border="0" cellspacing="0" cellpadding="0" class="regist_data">
													<tr>
														<td>{L_LOGIN}:</td>
														<td>{D_LOGIN}</td>
													</tr>
													<tr>
														<td>{L_GROUP}:</td>
														<td>{D_GROUP}</td>
													</tr>
													<tr>
														<td>{L_EDIT_PASSWORD}<b>*</b>:</td>
														<td><input class="form" name="edit_password" type="password" size="40" maxlength="50" autocomplete="off" /></td>
													</tr>
													<tr>
														<td>{L_RE_PASSWORD}<b>*</b>:</td>
														<td><input class="form" name="re_password" type="password" size="40" maxlength="50" autocomplete="off" /></td>
													</tr>
													<tr>
														<td>{L_EMAIL}:</td>
														<td><input class="form" name="email" type="text" size="40" maxlength="50" value="{D_EMAIL}"/></td>
													</tr>
													<tr>
														<td>{L_REAL_NAME}:</td>
														<td><input class="form" name="real_name" type="text" size="40" maxlength="50" value="{D_REAL_NAME}"/></td>
													</tr>
													<tr>
														<td>{L_AVATAR}<b>*</b>:</td>
														<td>
															<img src="{U_AVATAR}" title="{D_REAL_NAME}" alt="{D_REAL_NAME}" border="0" class="image" />
														</td>
													</tr>
													<tr>
														<td>{L_DELETE_AVATAR}:</td>
														<td><input class="form" name="delete_avatar" type="checkbox" value="true" /></td>
													</tr>
													<tr>
														<td class="regist" colspan="2">
															<input type="hidden" name="MAX_FILE_SIZE" value="{D_MAX_FILE_SIZE}" /><input name="file_avatar" value="" type="file" size="20" />
															<hr class="line">
														</td>
													</tr>
													<tr>
														<td class="regist" colspan="2">
															<b>*</b> {L_HELP_EDIT}<hr class="line">
														</td>
													</tr>
<!-- IF_NEED_PASSWORD_BEGIN -->
													<tr>
														<td>{L_PASSWORD}:</td>
														<td><input class="form" name="password" type="password" size="40" maxlength="50" autocomplete="off" /></td>
													</tr>
<!-- IF_NEED_PASSWORD_END -->
													<tr>
														<td class="regist" colspan="2">
															<input name="submit" type="image" value="{L_SAVE_PROFILE}" alt="{L_SAVE_PROFILE}" title="{L_SAVE_PROFILE}" src="{THEMES_PATH}img/submit_small_save.gif" align="absmiddle" />
														</td>
													</tr>
												</table>
											</form>
										</div>
									</td>
									<td class="regist"></td>
								</tr>
							</table>
