
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
                                	<td class="mini_foto"></td>
									<td class="mini_foto">
										<form name="edit" action="{U_EDITED}" method="post">
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
													<td class="mini_foto"><img src="{U_FOTO}" alt="{D_NAME_PHOTO}" width="{D_FOTO_WIDTH}" height="{D_FOTO_HEIGHT}" title="{D_DESCRIPTION_PHOTO}" border="0" /></td>
												</tr>
											</table>
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
			            	                        <td class="category_center">{L_NAME_FILE}:</td>
			                	                    <td class="category_center">{D_NAME_FILE}</td>
												</tr>
												<tr>
			            	                        <td class="category_center">{L_NAME_PHOTO}:</td>
			                	                    <td class="category_center"><input name="name_photo" value="{D_NAME_PHOTO}" type="text" size="20" maxlength="50" /></td>
												</tr>
												<tr>
                                    				<td class="category_center">{L_DESCRIPTION_PHOTO}:</td>
                                    				<td class="category_center"><input name="description_photo" value="{D_DESCRIPTION_PHOTO}" type="text" size="20" maxlength="250" /></td>
												</tr>
												<tr>
													<td class="mini_foto">{L_NAME_CATEGORY}:</td>
													<td class="mini_foto">{D_NAME_CATEGORY}</td>
												</tr>
											</table>
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
													<td class="mini_foto"><input name="submit" type="image" value="{L_EDIT_THIS}" src="{THEMES_PATH}img/submit_small_save.gif" align="absmiddle" /></td>
												</tr>
											</table>
										</form>
									</td>
                                    <td class="mini_foto"></td>
								</tr>
							</table>
