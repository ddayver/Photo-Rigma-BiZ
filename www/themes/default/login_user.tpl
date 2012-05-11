							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_small">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="login_user"></td>
									<td class="login_user" align="center">
										<form name="login_user" action="{U_LOGIN}" method="post">
											<table cellpadding="0" cellspacing="1" border="0">
												<tr>
													<td style="text-align:left;">{L_LOGIN}:</td>
													<td style="text-align:left;"><input class="form" type="text" name="login" maxlength="32" /></td>
												</tr>
												<tr>
													<td style="text-align:left;">{L_PASSWORD}:</td>
													<td style="text-align:left;"><input class="form" type="password" name="password" autocomplete="off" /></td>
												</tr>
												<tr>
													<td colspan="2"><input name="submit" type="image" value="{L_ENTER}" title="{L_ENTER}" alt="{L_ENTER}" src="{THEMES_PATH}img/submit_small_vojti.gif" align="absmiddle" /></td>
												</tr>
											</table>
										</form>
									</td>
									<td class="login_user"></td>
								</tr>
								<tr>
									<td class="login_user"></td>
									<td class="login_user" align="center">
										<!-- <a href="{U_FORGOT_PASSWORD}" title="{L_FORGOT_PASSWORD}">{L_FORGOT_PASSWORD}</a><br /> -->
										<a href="{U_REGISTRATION}" title="{L_REGISTRATION}">{L_REGISTRATION}</a>
									</td>
									<td class="login_user"></td>
								</tr>
							</table>
