
							<table cellpadding="0" cellspacing="0" border="0" width="100%" class="polja_big">
								<tr>
									<td class="name_block_left">&nbsp;</td>
									<td class="name_block">{NAME_BLOCK}</td>
									<td class="name_block_right">&nbsp;</td>
								</tr>
								<tr>
                                	<td class="mini_foto"></td>
									<td class="mini_foto">
										<form name="edit" action="{U_UPLOADED}" method="post" enctype="multipart/form-data">
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
			            	                        <td class="category_center">{L_FILE_PHOTO}:</td>
			                	                    <td class="category_left"><input type="hidden" name="MAX_FILE_SIZE" value="{D_MAX_FILE_SIZE}" /><input name="file_photo" value="" type="file" size="20" /></td>
												</tr>
												<tr>
			            	                        <td class="category_center">{L_NAME_PHOTO}:</td>
			                	                    <td class="category_left"><input name="name_photo" value="" type="text" size="20" maxlength="50" /></td>
												</tr>
												<tr>
                                    				<td class="category_center">{L_DESCRIPTION_PHOTO}:</td>
                                    				<td class="category_left"><input name="description_photo" value="" type="text" size="20" maxlength="250" /></td>
												</tr>
												<tr>
													<td class="category_center">{L_NAME_CATEGORY}:</td>
													<td class="category_left">{D_NAME_CATEGORY}</td>
												</tr>
											</table>
											<table cellpadding="0" cellspacing="0" border="0" width="100%">
												<tr>
													<td class="category_center"><input name="submit" type="image" value="{L_UPLOAD_THIS}" src="{THEMES_PATH}img/submit_small_upload.gif" align="absmiddle" /></td>
												</tr>
											</table>
										</form>
									</td>
                                    <td class="mini_foto"></td>
								</tr>
							</table>
