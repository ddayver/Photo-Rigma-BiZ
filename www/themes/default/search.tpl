
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="category_center"></td>
									<td class="category_center">
										<form name="search" action="{U_SEARCH}" method="post">
											<table align="center" border="0" cellpadding="0" cellspacing="0">
												<tr>
													<td>{L_SEARCH_TITLE}</td>
												</tr>
												<tr>
													<td><input class="form" size="50" type="text" name="search_text" value="{D_SEARCH_TEXT}"/>
													<input name="poisk" type="image" value="search_text" title="{L_SEARCH}" alt="{L_SEARCH}" src="{THEMES_PATH}img/submit_small_poisk.gif" align="absmiddle" /></td>
												</tr>
												<tr>
													<td><input class="form" name="search_user" type="checkbox" value="true" {D_NEED_USER} />&nbsp;{L_NEED_USER} <input class="form" name="search_category" type="checkbox" value="true" {D_NEED_CATEGORY} />&nbsp;{L_NEED_CATEGORY} <input class="form" name="search_news" type="checkbox" value="true" {D_NEED_NEWS} />&nbsp;{L_NEED_NEWS} <input class="form" name="search_photo" type="checkbox" value="true" {D_NEED_PHOTO} />&nbsp;{L_NEED_PHOTO}</td>
												</tr>
											</table>
										</form>
									</td>
									<td class="category_center"></td>
								</tr>
							</table>
<!-- IF_NEED_USER_BEGIN -->
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{L_FIND_USER}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="category_center"></td>
									<td class="category_center">
										{D_FIND_USER}
									</td>
									<td class="category_center"></td>
								</tr>
							</table>
<!-- IF_NEED_USER_END -->
<!-- IF_NEED_CATEGORY_BEGIN -->
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{L_FIND_CATEGORY}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="category_center"></td>
									<td class="category_center">
										{D_FIND_CATEGORY}
									</td>
									<td class="category_center"></td>
								</tr>
							</table>
<!-- IF_NEED_CATEGORY_END -->
<!-- IF_NEED_NEWS_BEGIN -->
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{L_FIND_NEWS}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="category_center"></td>
									<td class="category_center">
										{D_FIND_NEWS}
									</td>
									<td class="category_center"></td>
								</tr>
							</table>
<!-- IF_NEED_NEWS_END -->
<!-- IF_NEED_PHOTO_BEGIN -->
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{L_FIND_PHOTO}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="category_center"></td>
									<td class="category_center">
										{D_FIND_PHOTO}
									</td>
									<td class="category_center"></td>
								</tr>
							</table>
<!-- IF_NEED_PHOTO_END -->