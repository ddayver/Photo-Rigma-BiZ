
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="news_data"></td>
									<td class="category_center">
<!-- IF_SESSION_ADMIN_ON_BEGIN -->
										<div  align="center">
											<table cellspacing="0" cellpadding="0" border="0">
												<tr>
													<td class="category_center">
														{L_SELECT_SUBACT}
														<hr class="line">
													</td>
            									</tr>
												<tr>
													<td class="news_data">
														{D_SELECT_SUBACT}
													</td>
    	        								</tr>
											</table>
										</div>
<!-- IF_SESSION_ADMIN_ON_END -->
<!-- IF_SESSION_ADMIN_OFF_BEGIN -->
										<form name="login_admin" action="" method="post">
											<table cellspacing="0" cellpadding="0" border="0" width="100%">
												<tr>
													<td class="category_center" align="center">
														<br />
														{L_ENTER_ADMIN_PASS}
														<br /><br />
														<input class="form" type="password" name="admin_password" autocomplete="off" />
														<br /><br />
														<input name="submit" type="image" value="{L_ENTER}" title="{L_ENTER}" alt="{L_ENTER}" src="{THEMES_PATH}img/submit_small_vojti.gif" align="absmiddle" />
														<br />
													</td>
            									</tr>
											</table>
										</form>
<!-- IF_SESSION_ADMIN_OFF_END -->
									</td>
									<td class="news_data"></td>
								</tr>
							</table>
