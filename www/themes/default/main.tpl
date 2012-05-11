<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<title>{TITLE}</title>
	<meta name="description" content="{META_DESRIPTION}" />
	<meta name="keywords" content="{META_KEYWORDS}" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<!-- IF_NEED_REDIRECT_BEGIN -->
	<meta http-equiv="refresh" content="{REDIRECT_TIME}; URL={U_REDIRECT_URL}" />
<!-- IF_NEED_REDIRECT_END -->
	<link href="{THEMES_PATH}style.css" rel="stylesheet" type="text/css" />
</head>

<body bgcolor="#141519;">
<div align="center">
	<table class="my" width="{GALLERY_WIDHT}" cellspacing="0" cellpadding="0">
		<tr>
           	<td class="shapko">
				<img src="{THEMES_PATH}img/logo-1.png" alt="{SITE_NAME} - {SITE_DESCRIPTION}" title="{SITE_NAME} - {SITE_DESCRIPTION}"/>
			</td>
		</tr>
		<tr>
			<td class="polja_small_left">
				<table cellpadding="0" cellspacing="0" width="100%">
					<tr>
						<td>
{TEXT_SHORT_MENU}
						</td>
						<td>
							<form name="poisk" action="{U_SEARCH}" method="post">
								<table align="right" border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td><input class="form" size="50" type="text" name="search_main_text" /></td>
										<td><input name="poisk" type="image" value="search_main_text" title="{L_SEARCH}" alt="{L_SEARCH}" src="{THEMES_PATH}img/submit_small_poisk.gif" align="absmiddle" /></td>
									</tr>
								</table>
							</form>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td class="polja">
				<table cellpadding="0" cellspacing="0" width="100%">
                       	<col width="{LEFT_PANEL_WIDHT}" />
                        <col />
                        <col />
                        <col />
                        <col width="{RIGHT_PANEL_WIDHT}"  />
                	<tr class="td_spacer">
						<td class="td_spacer" width="{LEFT_PANEL_WIDHT}"><img src="{THEMES_PATH}img/spacer.gif" height="1" border="0" alt="" width="{LEFT_PANEL_WIDHT}" /></td>
						<td class="td_spacer"><img src="{THEMES_PATH}img/spacer.gif" height="1" border="0" alt="" width="1" /></td>
						<td class="td_spacer"><img src="{THEMES_PATH}img/spacer.gif" height="1" border="0" alt="" width="1" /></td>
						<td class="td_spacer"><img src="{THEMES_PATH}img/spacer.gif" height="1" border="0" alt="" width="1" /></td>
						<td class="td_spacer" width="{RIGHT_PANEL_WIDHT}"><img src="{THEMES_PATH}img/spacer.gif" height="1" border="0" alt="" width="{RIGHT_PANEL_WIDHT}" /></td>
					</tr>
					<tr>
						<td class="polja">
{TEXT_LONG_MENU}
{TEXT_TOP_FOTO}
{TEXT_LAST_FOTO}
						</td>
						<td width="5px" class="td_spacer">&nbsp;</td>
						<td class="polja">
{MAIN_BLOCK}
						</td>
						<td width="5px" class="td_spacer">&nbsp;</td>
						<td class="polja">
{TEXT_USER_INFO}
{TEXT_STATISTIC}
{TEXT_BEST_USER}
{TEXT_RANDOM_FOTO}
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td class="polja_copyright" colspan="5">&copy;&nbsp;{COPYRIGHT_YEAR}&nbsp;<a href="{COPYRIGHT_URL}" title="{COPYRIGHT_TEXT}" target="_blank">{COPYRIGHT_TEXT}</a></td>
		</tr>
	</table>
</div>
</body>

</html>
