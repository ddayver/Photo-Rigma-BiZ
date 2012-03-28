
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
									<td class="category_center"></td>
                                    <td class="category_center">
										<form name="edit" action="{U_EDITED}" method="post">
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
			            	                        <td class="category_center">{L_NAME_DIR}:</td>
			                	                    <td class="category_center"><input name="name_dir" value="{D_NAME_DIR}" type="text" size="20" maxlength="50" /></td>
												</tr>
												<tr>
			            	                        <td class="category_center">{L_NAME_CATEGORY}:</td>
			                	                    <td class="category_center"><input name="name_category" value="{D_NAME_CATEGORY}" type="text" size="20" maxlength="50" /></td>
												</tr>
												<tr>
                                    				<td class="category_center">{L_DESCRIPTION_CATEGORY}:</td>
                                    				<td class="category_center"><input name="description_category" value="{D_DESCRIPTION_CATEGORY}" type="text" size="20" maxlength="250" /></td>
												</tr>
											</table>
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
													<td class="mini_foto"><input name="submit" type="image" value="{L_EDIT_THIS}" alt="{L_EDIT_THIS}" title="{L_EDIT_THIS}" src="{THEMES_PATH}img/submit_small_save.gif" align="absmiddle" /></td>
												</tr>
											</table>
										</form>
                                    <td class="category_center"></td>
								</tr>
							</table>