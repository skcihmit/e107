<?php
/*
+ ----------------------------------------------------------------------------+
|     e107 website system
|
|     �Steve Dunstan 2001-2002
|     http://e107.org
|     jalist@e107.org
|
|     Released under the terms and conditions of the
|     GNU General Public License (http://gnu.org).
|
|     $Source: /cvs_backup/e107_0.8/e107_handlers/theme_handler.php,v $
|     $Revision: 1.15 $
|     $Date: 2009-07-05 11:57:40 $
|     $Author: e107coders $
+----------------------------------------------------------------------------+
*/

if (!defined('e107_INIT')) { exit; }

class themeHandler{

	var $themeArray;
	var $action;
	var $id;

	/* constructor */

	function themeHandler() {

		if (isset($_POST['upload'])) {
			$this -> themeUpload();
		}

		$this -> themeArray = $this -> getThemes();

		foreach($_POST as $key => $post)
		{
			if(strstr($key,"preview"))
			{
				$this -> id = str_replace("preview_", "", $key);
				$this -> themePreview();
			}
			if(strstr($key,"selectmain"))
			{
				$this -> id = str_replace("selectmain_", "", $key);
				$this -> setTheme();
			}

			if(strstr($key,"selectadmin"))
			{
				$this -> id = str_replace("selectadmin_", "", $key);
				$this -> setAdminTheme();
			}
		}

		if(isset($_POST['submit_adminstyle']))
		{
			$this -> setAdminStyle();
		}

		if(isset($_POST['submit_style']))
		{
			$this -> setStyle();
		}

	}

	function getThemes($mode=FALSE)
	{
		$themeArray = array();
	  	$themeArray[0] = "";   // Fix for 'preview theme'.
		$tloop = 1;
		$handle = opendir(e_THEME);
		while (false !== ($file = readdir($handle)))
		{
		  	if ($file != "." && $file != ".." && $file != "CVS" && $file != "templates" && is_dir(e_THEME.$file) && is_readable(e_THEME.$file."/theme.php") )
		  	{
				if($mode == "id")
				{
					$themeArray[$tloop] = $file;
				}
				else
				{
					$themeArray[$file]['id'] = $tloop;
				}
				$tloop++;
				$STYLESHEET = FALSE;
				if(!$mode)
				{
					$handle2 = opendir(e_THEME.$file."/");
					while (false !== ($file2 = readdir($handle2))) // Read files in theme directory
				  	{
						if ($file2 != "." && $file2 != ".." && $file != "CVS" && !is_dir(e_THEME.$file."/".$file2))
						{
					  		$themeArray[$file]['files'][] = $file2;
					  		if(strstr($file2, "preview."))
					  		{
								$themeArray[$file]['preview'] = e_THEME.$file."/".$file2;
					  		}
						  	if(strstr($file2, "css") && !strstr($file2, "menu.css") && strpos($file2, "e_") !== 0 && strpos($file2, "admin_") !== 0)
						  	{
								/* get information string for css file */
								$fp=fopen(e_THEME.$file."/".$file2, "r");
								$cssContents = fread ($fp, filesize(e_THEME.$file."/".$file2));
								fclose($fp);
								$nonadmin = preg_match('/\* Non-Admin(.*?)\*\//', $cssContents) ? true : false;
								preg_match('/\* info:(.*?)\*\//', $cssContents, $match);
								$match[1]=varset($match[1],'');
								$themeArray[$file]['css'][] = array("name" => $file2, "info" => $match[1], "nonadmin" => $nonadmin);
								if($STYLESHEET)
								{
								  $themeArray[$file]['multipleStylesheets'] = TRUE;
								}
								else
								{
								  $STYLESHEET = TRUE;
								}
						  	}
 						}
	 				} // end while..

				  	closedir($handle2);

					// Load Theme information and merge with existing array. theme.xml (0.8 themes) is given priority over theme.php (0.7).
					if(in_array("theme.xml",$themeArray[$file]['files']) )
					{
		 	        	$themeArray[$file] = array_merge($themeArray[$file], $this->parse_theme_xml($file));
		    		}
					elseif(in_array("theme.php",$themeArray[$file]['files']))
					{
						$themeArray[$file] =  array_merge($themeArray[$file], $this->parse_theme_php($file));
		         }
				}
		  	}
		}
		closedir($handle);

        /*echo "<pre>";
		print_r($themeArray['e107v4a']);
		echo "</pre>";
*/

		return $themeArray;
	}

	function themeUpload()
	{
		if (!$_POST['ac'] == md5(ADMINPWCHANGE)) {
			exit;
		}
		global $ns;
		extract($_FILES);
		if(!is_writable(e_THEME))
		{
			$ns->tablerender(TPVLAN_16, TPVLAN_20);
		}
		else
		{
			require_once(e_HANDLER."upload_handler.php");
			$fileName = $file_userfile['name'][0];
			$fileSize = $file_userfile['size'][0];
			$fileType = $file_userfile['type'][0];

			if(strstr($file_userfile['type'][0], "gzip")) {
				$fileType = "tar";
			} else if (strstr($file_userfile['type'][0], "zip")) {
				$fileType = "zip";
			} else {
				$ns->tablerender(TPVLAN_16, TPVLAN_17);
				require_once("footer.php");
				exit;
			}

			if ($fileSize) {

				$uploaded = file_upload(e_THEME);

				$archiveName = $uploaded[0]['name'];


				if($fileType == "zip") {
					require_once(e_HANDLER."pclzip.lib.php");
					$archive = new PclZip(e_THEME.$archiveName);
					$unarc = ($fileList = $archive -> extract(PCLZIP_OPT_PATH, e_THEME, PCLZIP_OPT_SET_CHMOD, 0666));
				} else {
					require_once(e_HANDLER."pcltar.lib.php");
					$unarc = ($fileList = PclTarExtract($archiveName, e_THEME));
				}

				if(!$unarc) {
					if($fileType == "zip") {
					$error = TPVLAN_46." '".$archive -> errorName(TRUE)."'";
				} else {
					$error = TPVLAN_47.PclErrorString().", ".TPVLAN_48.intval(PclErrorCode());
					}
					$ns->tablerender(TPVLAN_16, TPVLAN_18." ".$archiveName." ".$error);
					require_once("footer.php");
					exit;
				}

				$folderName = substr($fileList[0]['stored_filename'], 0, (strpos($fileList[0]['stored_filename'], "/")));
				$ns->tablerender(TPVLAN_16, "<div class='center'>".TPVLAN_19."</div>");

				@unlink(e_THEME.$archiveName);
			}
		}
	}

	function showThemes()
	{
		global $ns, $pref;
		echo "<div class='center'>
		<form enctype='multipart/form-data' method='post' action='".e_SELF."'>\n";

		foreach($this -> themeArray as $key => $theme)
		{
			if($key == $pref['sitetheme'])
			{
				$text = $this -> renderTheme(1, $theme);
			}
		}

		$ns->tablerender(TPVLAN_26." :: ".TPVLAN_33, $text);

		foreach($this -> themeArray as $key => $theme)
		{
			if($key == $pref['admintheme'])
			{
				$text = $this -> renderTheme(2, $theme);
			}
		}
		$ns->tablerender(TPVLAN_26." :: ".TPVLAN_34, $text);


		if(!is_writable(e_THEME)) {
			$ns->tablerender(TPVLAN_16, TPVLAN_15);
			$text = "";
		}
		else
		{
		  require_once(e_HANDLER.'upload_handler.php');
		  $max_file_size = get_user_max_upload();

		  $text = "<div style='text-align:center'>
			<table style='".ADMIN_WIDTH."' class='fborder'>
			<tr>
			<td class='forumheader3' style='width: 50%;'>".TPVLAN_13."</td>
			<td class='forumheader3' style='width: 50%;'>
			<input type='hidden' name='MAX_FILE_SIZE' value='{$max_file_size}' />
			<input type='hidden' name='ac' value='".md5(ADMINPWCHANGE)."' />
			<input class='tbox' type='file' name='file_userfile[]' size='50' />
			</td>
			</tr>
			<tr>
			<td colspan='2' style='text-align:center' class='forumheader'>
			<input class='button' type='submit' name='upload' value='".TPVLAN_14."' />
			</td>
			</tr>
			</table>
			<br /></div>\n";
		}

		$ns->tablerender(TPVLAN_26." :: ".TPVLAN_38, $text);
		$text = "";
		foreach($this -> themeArray as $key => $theme)
		{
			if($key != $pref['admintheme'] && $key != $pref['sitetheme'])
			{
				$text .= $this -> renderTheme(FALSE, $theme);
			}
		}



		$ns->tablerender(TPVLAN_26." :: ".TPVLAN_39, $text);
		echo "</form>\n</div>\n";
	}




	function renderTheme($mode=FALSE, $theme)
	{

		/*
		mode = 0 :: normal
		mode = 1 :: selected site theme
		mode = 2 :: selected admin theme
		*/

		define("IMAGE_CHECK","<img src='".e_IMAGE_ABS."generic/check.png' style='border:0px;vertical-align:middle'  alt='' />");

		global $ns, $pref, $imode;

		$author = ($theme['email'] ? "<a href='mailto:".$theme['email']."' title='".$theme['email']."'>".$theme['author']."</a>" : $theme['author']);
		$website = ($theme['website'] ? "<a href='".$theme['website']."' rel='external'>".$theme['website']."</a>" : "");
		$preview = "<a href='".e_BASE."news.php?themepreview.".$theme['id']."' title='".TPVLAN_9."' >".($theme['preview'] ? "<img src='".$theme['preview']."' style='border: 1px solid #000;width:200px' alt='' />" : "<img src='".e_IMAGE_ABS."admin_images/nopreview.png' style='border:0px' title='".TPVLAN_12."' alt='' />")."</a>";
		$selectmainbutton = ($mode != 1 ? "<input class='button' type='submit' name='selectmain_".$theme['id']."' value='".TPVLAN_10."' />" : "");
		$selectadminbutton = ($mode != 2 ? "<input class='button' type='submit' name='selectadmin_".$theme['id']."' value='".TPVLAN_32."' />" : "");
		$previewbutton = (!$mode ? "<input class='button' type='submit' name='preview_".$theme['id']."' value='".TPVLAN_9."' /> " : "");

		$text = "<div style='text-align:center;margin-left:auto;margin-right:auto'>
		<table style='".ADMIN_WIDTH."' class='fborder'>
		<tr>
		<td class='forumheader3' style='width:202px; text-align:center; vertical-align:top'>$preview
		<br />
		<br />
		<b><span class='mediumtext'>".$theme['name']."</span></b><br />".TPVLAN_11." ".$theme['version']."
		<br />
		</td>
		<td class='forumheader3' style='vertical-align:top'>";

		$itext = $author ? "<tr><td style='vertical-align:top; width:24%'><b>".TPVLAN_4."</b>:</td><td style='vertical-align:top'>".$author."</td></tr>" : "";
		$itext .= $website ? "<tr><td style='vertical-align:top; width:24%'><b>".TPVLAN_5."</b>:</td><td style='vertical-align:top'>".$website."</td></tr>" : "";
		$itext .= $theme['date'] ? "<tr><td style='vertical-align:top; width:24%'><b>".TPVLAN_6."</b>:</td><td style='vertical-align:top'>".$theme['date']."</td></tr>" : "";
        $itext .= "<tr><td style='vertical-align:top; width:24%'><b>".TPVLAN_49."</b>:</td>
			<td style='vertical-align:top'>XHTML ";
        $itext .= ($theme['xhtmlcompliant']) ? IMAGE_CHECK : "X";
		$itext .= "  &nbsp;&nbsp;  CSS ";
		$itext .= ($theme['csscompliant']) ? IMAGE_CHECK : "X";
		$itext .= "</td></tr>";
        if($theme['xhtmlcompliant'] || $theme['xhtmlcompliant'])




		$itext .= $theme['info'] ? "<tr><td style='vertical-align:top; width:24%'><b>".TPVLAN_7."</b>:</td><td style='vertical-align:top'>".$theme['info']."</td></tr>" : "";

        if($theme['layouts'])  // New in 0.8    WORK IN PROGRESS ----
		{
            $itext .= "<tr>
					<td style='vertical-align:top; width:24%'><b>".TPVLAN_50."</b>:</td>
					<td style='vertical-align:top'><table class='fborder' style='margin-left:0px;margin-right:auto;width:400px' >
						<tr>";
                        $itext .= ($mode == 1) ? "<td class='fcaption' style='text-align:center;vertical-align:top;'>Default</td>" : "";
						$itext .= "
							<td class='fcaption'>Title</td>
							<td class='fcaption'>Requirements</td>
							<td class='fcaption' style='text-align:center;width:100px'>Menu Preset</td>
						</tr>\n";

			foreach($theme['layouts'] as $key=>$val)
		 	{
                $itext .= "
				<tr>";
				if($mode == 1)
				{
					if(!$pref['sitetheme_deflayout'])
					{
						$pref['sitetheme_deflayout'] = ($val['@attributes']['default']=='true') ? $key : "";
					  //	echo "------------- NODEFAULT";
					}
					$itext .= "
	                <td style='vertical-align:top width:auto;text-align:center'>
						<input type='radio' name='layout_default' value='{$key}' ".($pref['sitetheme_deflayout']==$key ? " checked='checked'" : "")." />
					</td>";
				}

				$itext .= "<td style='vertical-align:top'>";
				$itext .= ($val['@attributes']['previewFull']) ? "<a href='".e_THEME_ABS.$theme['path']."/".$val['@attributes']['previewFull']."' >" : "";
				$itext .= $val['@attributes']['title'];
				$itext .= ($val['@attributes']['previewFull']) ? "</a>" : "";
                $itext .= ($pref['sitetheme_deflayout'] == $key) ? " (default)" : "";
				$itext .= "</td>
					<td style='vertical-align:top'>".$val['@attributes']['requiredPlugins']."&nbsp;</td>
                    <td style='vertical-align:top;text-align:center'>";
                    $itext .= ($val['menuPresets']) ? IMAGE_CHECK : "&nbsp;";
					$itext .= "</td>
				</tr>";
			}

			$itext .= "</table></td></tr>";
		}


		$itext .= !$mode ? "<tr><td style='vertical-align:top;width:24%'><b>".TPVLAN_8."</b>:</td><td style='vertical-align:top'>".$previewbutton.$selectmainbutton.$selectadminbutton."</td></tr>" : "";


		if ($itext) {
			$text .= "<table cellspacing='3' style='width:97%'>".$itext."</table>";
		}

		if(array_key_exists("multipleStylesheets", $theme))
		{
			if($mode)
			{
				$text .= "<table cellspacing='3' style='width:97%'>
				<tr><td style='vertical-align:top; width:50%;'><b>".TPVLAN_27.":</b></td><td style='vertical-align:top width:50%;'>\n";
				foreach($theme['css'] as $css)
				{

					if($mode == 2)
					{
						if (!$css['nonadmin']) {
							$text .= "
							<input type='radio' name='admincss' value='".$css['name']."' ".($pref['admincss'] == $css['name'] || (!$pref['admincss'] && $css['name'] == "style.css") ? " checked='checked'" : "")." /><b>".$css['name'].":</b><br />".($css['info'] ? $css['info'] : ($css['name'] == "style.css" ? TPVLAN_23 : TPVLAN_24))."<br />\n";
						}
					}

					if($mode == 1)
					{
						$text .= "
						<input type='radio' name='themecss' value='".$css['name']."' ".($pref['themecss'] == $css['name'] || (!$pref['themecss'] && $css['name'] == "style.css") ? " checked='checked'" : "")." /><b>".$css['name'].":</b><br />".($css['info'] ? $css['info'] : ($css['name'] == "style.css" ? TPVLAN_23 : TPVLAN_24))."<br />\n";
					}
				}
				$text .= "</td></tr></table>";

			}
			else
			{
				$text .= "<br /><table style='width:97%' cellspacing='3'><tr><td colspan='2'><b>".TPVLAN_22.": </b></td></tr>";
				foreach($theme['css'] as $css)
				{
					$text .= "<tr><td style='width:24%;vertical-align:top'><b>".$css['name'].":</b></td><td> ".($css['info'] ? $css['info'] : ($css['name'] == "style.css" ? TPVLAN_23 : TPVLAN_24))."</td></tr>\n";
				}
				$text .= "</table><br />\n";
			}
		}

			if($mode == 1)
			{
				$text .= "<table cellspacing='3' style='width:97%'>

				<tr>
				<td style='vertical-align:top; width:24%;'><b>".TPVLAN_30."</b></td><td style='vertical-align:top width:auto;'>
				<input type='radio' name='image_preload' value='1'".($pref['image_preload'] ? " checked='checked'" : "")." /> ".TPVLAN_28."&nbsp;&nbsp;
				<input type='radio' name='image_preload' value='0'".(!$pref['image_preload'] ? " checked='checked'" : "")." /> ".TPVLAN_29."
				</td>
				</tr>
				<tr>
				<td colspan='2' class='center'>
				<input class='button' type='submit' name='submit_style' value='".TPVLAN_35."' /> ".$selectadminbutton."
				</td></tr></table>";
			}

		if($mode == 2)
		{

			$astext = "";
			require_once(e_HANDLER."file_class.php");
			$file = new e_file;

			$adminstyles = $file -> get_files(e_ADMIN."includes");

			$astext = "\n<select id='mode2' name='adminstyle' class='tbox'>\n";

			foreach($adminstyles as $as)
			{
				$style = str_replace(".php", "", $as['fname']);
				$astext .= "<option value='{$style}'".($pref['adminstyle'] == $style ? " selected='selected'" : "").">".$style."</option>\n";
			}
			$astext .= "</select>";

			$text .= "<br /><br /><table cellspacing='3' style='width:97%'>
			<tr><td style='vertical-align:top; width:50%;'><b>".TPVLAN_41.":</b></td><td style='vertical-align:top width:50%;'>$astext</td></tr>
			<tr><td colspan='2' class='center'>
			<input class='button' type='submit' name='submit_adminstyle' value='".TPVLAN_42."' /> ".$selectmainbutton."
			</td></tr></table>\n";
		}

		$text .= "</td></tr></table></div>\n";
		return $text;
	}

	function themePreview()
	{
		echo "<script type='text/javascript'>document.location.href='".e_BASE."news.php?themepreview.".$this -> id."'</script>\n";
		exit;
	}

	function showPreview()
	{
		include_lan(e_LANGUAGEDIR.e_LANGUAGE."/admin/lan_theme.php");
		$text = "<br /><div class='indent'>".TPVLAN_1.".</div><br />";
		global $ns;
		$ns->tablerender(TPVLAN_2, $text);
	}

	function setTheme()
	{
		global $pref, $e107cache, $ns;
		$themeArray = $this -> getThemes("id");

		$pref['sitetheme'] = $themeArray[$this -> id];
		$pref['themecss'] ='style.css';
        $pref['sitetheme_deflayout'] = $this->findDefault($themeArray[$this -> id]);
		$pref['sitetheme_layouts'] = is_array($this->themeArray[$pref['sitetheme']]['layouts']) ? $this->themeArray[$pref['sitetheme']]['layouts'] : array();

		$e107cache->clear_sys();
	 	save_prefs();

		$this->theme_adminlog('01',$pref['sitetheme'].', '.$pref['themecss']);
		$ns->tablerender("Admin Message", "<br /><div style='text-align:center;'>".TPVLAN_3." <b>'".$themeArray[$this -> id]."'</b>.</div><br />");
	}

	function findDefault($theme)
	{
		if(varset($_POST['layout_default']))
		{
        	return $_POST['layout_default'];
		}

    	$l = $this->themeArray[$theme];
		foreach($l['layouts'] as $key=>$val)
		{
        	if(isset($val['@attributes']['default']) && ($val['@attributes']['default'] == "true"))
			{
            	return $key;
			}
		}
	}

	function setAdminTheme()
	{
		global $pref, $e107cache, $ns;
		$themeArray = $this -> getThemes("id");
		$pref['admintheme'] = $themeArray[$this -> id];
		$pref['admincss'] = file_exists(THEME.'admin_style.css') ? 'admin_style.css' : 'style.css';
		$e107cache->clear_sys();
		save_prefs();
		$this->theme_adminlog('02',$pref['admintheme'].', '.$pref['admincss']);
		$ns->tablerender("Admin Message", "<br /><div style='text-align:center;'>".TPVLAN_40." <b>'".$themeArray[$this -> id]."'</b>.</div><br />");
	}

	function setStyle()
	{
		global $pref, $e107cache, $ns;
		$pref['themecss'] = $_POST['themecss'];
		$pref['image_preload'] = $_POST['image_preload'];
		$pref['sitetheme_deflayout'] = $_POST['layout_default'];

		$e107cache->clear_sys();
		save_prefs();
		$this->theme_adminlog('03',$pref['image_preload'].', '.$pref['themecss']);
		$ns->tablerender(TPVLAN_36, "<br /><div style='text-align:center;'>".TPVLAN_37.".</div><br />");
	}

	function setAdminStyle()
	{
		global $pref, $e107cache, $ns;
		$pref['admincss'] = $_POST['admincss'];
		$pref['adminstyle'] = $_POST['adminstyle'];
		$e107cache->clear_sys();
		save_prefs();
		$this->theme_adminlog('04',$pref['adminstyle'].', '.$pref['admincss']);
		$ns->tablerender(TPVLAN_36, "<br /><div style='text-align:center;'>".TPVLAN_43.".</div><br />");
	}


	// Log event to admin log
	function theme_adminlog($msg_num='00', $woffle='')
	{
		global $pref, $admin_log;
		//  if (!varset($pref['admin_log_log']['admin_banlist'],0)) return;
		$admin_log->log_event('THEME_'.$msg_num,$woffle,E_LOG_INFORMATIVE,'');
	}

	function parse_theme_php($path)
	{
		$fp=fopen(e_THEME.$path."/theme.php", "r");
		$themeContents = fread ($fp, filesize(e_THEME.$path."/theme.php"));
		fclose($fp);


		preg_match('/themename(\s*?=\s*?)("|\')(.*?)("|\');/si', $themeContents, $match);
		$themeArray['name'] = varset($match[3],'');
		preg_match('/themeversion(\s*?=\s*?)("|\')(.*?)("|\');/si', $themeContents, $match);
		$themeArray['version'] = varset($match[3],'');
		preg_match('/themeauthor(\s*?=\s*?)("|\')(.*?)("|\');/si', $themeContents, $match);
		$themeArray['author'] = varset($match[3],'');
		preg_match('/themeemail(\s*?=\s*?)("|\')(.*?)("|\');/si', $themeContents, $match);
		$themeArray['email'] = varset($match[3],'');
		preg_match('/themewebsite(\s*?=\s*?)("|\')(.*?)("|\');/si', $themeContents, $match);
		$themeArray['website'] = varset($match[3],'');
		preg_match('/themedate(\s*?=\s*?)("|\')(.*?)("|\');/si', $themeContents, $match);
		$themeArray['date'] = varset($match[3],'');
		preg_match('/themeinfo(\s*?=\s*?)("|\')(.*?)("|\');/si', $themeContents, $match);
		$themeArray['info'] = varset($match[3],'');
        preg_match('/xhtmlcompliant(\s*?=\s*?)(\S*?);/si', $themeContents, $match);
		$xhtml = strtolower($match[2]);
		$themeArray['xhtmlcompliant'] = ($xhtml == "true" ? true : false);

		preg_match('/csscompliant(\s*?=\s*?)(\S*?);/si', $themeContents, $match);
		$css = strtolower($match[2]);
		$themeArray['csscompliant'] = ($css == "true" ? true : false);

  		if (!$themeArray['name'])
		{
			unset($themeArray);
		}
    	return $themeArray;
	}

    function parse_theme_xml($path)
	{
		global $tp;
	  //	loadLanFiles($path, 'admin');					// Look for LAN files on default paths
		require_once(e_HANDLER.'xml_class.php');
		$xml = new xmlClass;
		$vars = $xml->loadXMLfile(e_THEME.$path.'/theme.xml', true, true);

		$vars['name']					= varset($vars['@attributes']['name']);
		$vars['version']				= varset($vars['@attributes']['version']);
		$vars['date']					= varset($vars['@attributes']['date']);
		$vars['compatibility']		= varset($vars['@attributes']['compatibility']);


		$vars['email']	 				= varset($vars['author']['@attributes']['email']);
      $vars['website'] 				= varset($vars['author']['@attributes']['url']);
		$tmp								= varset($vars['author']['@attributes']['name']);
		$vars['author'] = $tmp;


		$vars['info'] 					= $vars['description'];
		$vars['xhtmlcompliant'] 	= (strtolower($vars['compliance']['@attributes']['xhtml']) == 'true' ? 1 : 0);
		$vars['csscompliant'] 		= (strtolower($vars['compliance']['@attributes']['css']) == 'true' ? 1 : 0);
		$vars['path']					= $path;
		$vars['@attributes']['default'] = (strtolower($vars['@attributes']['default'])=='true') ? 1 : 0;

		unset($vars['authorEmail'],$vars['authorUrl'],$vars['xhtmlCompliant'],$vars['cssCompliant'],$vars['description']);

		// Compile layout information into a more usable format.
		foreach($vars['layouts'] as $layout)
		{
			foreach($layout as $key=>$val)
			{
				$name = $val['@attributes']['name'];
				unset($val['@attributes']['name']);
				$lays[$name] = $val;
			}
		}
		$vars['layouts'] = $lays;

	  	return $vars;
	}

}
?>