#!/usr/local/bin/php
<?php
/*
	services_rsyncd_share_edit.php
	Copyright � 2006-2008 Volker Theile (votdev@gmx.de)
	All rights reserved.

	part of FreeNAS (http://www.freenas.org)
	Copyright (C) 2005-2008 Olivier Cochard-Labb� <olivier@freenas.org>.
	All rights reserved.

	Based on m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
require("guiconfig.inc");

$id = $_GET['id'];
if(isset($_POST['id']))
	$id = $_POST['id'];

$pgtitle = array(gettext("Services"), gettext("RSYNCD"), gettext("Share"), isset($id) ? gettext("Edit") : gettext("Add"));

if (!is_array($config['mounts']['mount']))
	$config['mounts']['mount'] = array();

if(!is_array($config['rsyncd']['share']))
	$config['rsyncd']['share'] = array();

array_sort_key($config['mounts']['mount'], "devicespecialfile");
array_sort_key($config['rsyncd']['share'], "name");

$a_mount = &$config['mounts']['mount'];
$a_share = &$config['rsyncd']['share'];

if (isset($id) && $a_share[$id]) {
	$pconfig['name'] = $a_share[$id]['name'];
	$pconfig['path'] = $a_share[$id]['path'];
	$pconfig['comment'] = $a_share[$id]['comment'];
	$pconfig['browseable'] = isset($a_share[$id]['browseable']);
	$pconfig['rwmode'] = $a_share[$id]['rwmode'];
	$pconfig['maxconnections'] = $a_share[$id]['maxconnections'];
	$pconfig['hostsallow'] = $a_share[$id]['hostsallow'];
	$pconfig['hostsdeny'] = $a_share[$id]['hostsdeny'];
} else {
	$pconfig['name'] = "";
	$pconfig['path'] = "";
	$pconfig['comment'] = "";
	$pconfig['browseable'] = true;
	$pconfig['rwmode'] = "rw";
	$pconfig['maxconnections'] = "0";
	$pconfig['hostsallow'] = "ALL";
	$pconfig['hostsdeny'] = "ALL";
}

if($_POST) {
	unset($input_errors);

	$pconfig = $_POST;

	// Input validation.
	$reqdfields = explode(" ", "name comment");
	$reqdfieldsn = array(gettext("Name"), gettext("Comment"));
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	$reqdfieldst = explode(" ", "string string");
	do_input_validation_type($_POST, $reqdfields, $reqdfieldsn, $reqdfieldst, &$input_errors);

	if(!$input_errors) {
		$share = array();

		$share['name'] = $_POST['name'];
		$share['path'] = $_POST['path'];
		$share['comment'] = $_POST['comment'];
		$share['browseable'] = $_POST['browseable'] ? true : false;
		$share['rwmode'] = $_POST['rwmode'];
		$share['maxconnections'] = $_POST['maxconnections'];
		$share['hostsallow'] = $_POST['hostsallow'];
		$share['hostsdeny'] = $_POST['hostsdeny'];

		if (isset($id) && $a_share[$id])
			$a_share[$id] = $share;
		else
			$a_share[] = $share;

		touch($d_rsyncdconfdirty_path);
		write_config();

    header("Location: services_rsyncd_share.php");
		exit;
	}
}
?>
<?php include("fbegin.inc");?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
		<td class="tabnavtbl">
  		<ul id="tabnav">
				<li class="tabact"><a href="services_rsyncd.php" style="color:black" title="<?=gettext("Reload page");?>"><?=gettext("Server");?></a></li>
			  <li class="tabinact"><a href="services_rsyncd_client.php"><?=gettext("Client");?></a></li>
			  <li class="tabinact"><a href="services_rsyncd_local.php"><?=gettext("Local");?></a></li>
			</ul>
		</td>
	</tr>
	<tr>
		<td class="tabnavtbl">
			<ul id="tabnav">
				<li class="tabinact"><a href="services_rsyncd.php"><?=gettext("Settings");?></a></li>
				<li class="tabact"><a href="services_rsyncd_share.php" title="<?=gettext("Reload page");?>" style="color:black"><?=gettext("Shares");?></a></li>
			</ul>
		</td>
	</tr>
  <tr>
    <td class="tabcont">
			<form action="services_rsyncd_share_edit.php" method="post" name="iform" id="iform">
				<?php if ($input_errors) print_input_errors($input_errors); ?>
			  <table width="100%" border="0" cellpadding="6" cellspacing="0">
			  	<tr>
			      <td width="22%" valign="top" class="vncellreq"><?=gettext("Module name");?></td>
			      <td width="78%" class="vtable">
			        <input name="name" type="text" class="formfld" id="name" size="30" value="<?=htmlspecialchars($pconfig['name']);?>">
			      </td>
			    </tr>
			    <tr>
			      <td width="22%" valign="top" class="vncellreq"><?=gettext("Comment");?></td>
			      <td width="78%" class="vtable">
			        <input name="comment" type="text" class="formfld" id="comment" size="30" value="<?=htmlspecialchars($pconfig['comment']);?>">
			      </td>
			    </tr>
			    <tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("Path");?></td>
				  <td width="78%" class="vtable">
				  	<input name="path" type="text" class="formfld" id="path" size="60" value="<?=htmlspecialchars($pconfig['path']);?>">
				  	<input name="browse" type="button" class="formbtn" id="Browse" onClick='ifield = form.path; filechooser = window.open("filechooser.php?p="+escape(ifield.value)+"&sd=/mnt", "filechooser", "scrollbars=yes,toolbar=no,menubar=no,statusbar=no,width=550,height=300"); filechooser.ifield = ifield; window.ifield = ifield;' value="..." \><br/>
				  	<span class="vexpl"><?=gettext("Path to be shared.");?></span>
				  </td>
				</tr>
			    <tr>
			      <td width="22%" valign="top" class="vncell"><?=gettext("Browseable");?></td>
			      <td width="78%" class="vtable">
			      	<input name="browseable" type="checkbox" id="browseable" value="yes" <?php if ($pconfig['browseable']) echo "checked"; ?>>
			      	<?=gettext("Set browseable.");?><br/>
			        <span class="vexpl"><?=gettext("This controls whether this share is seen in the list of available shares in a net view and in the browse list.");?></span>
			      </td>
			    </tr>
			    <tr>
			      <td width="22%" valign="top" class="vncell"><?=gettext("Access mode");?></td>
			      <td width="78%" class="vtable">
			        <select name="rwmode" size="1" id="rwmode">
		            <option value="ro" <?php if ("ro" === $pconfig['rwmode']) echo "selected";?>><?=gettext("Read only");?></option>
		            <option value="rw" <?php if ("rw" === $pconfig['rwmode']) echo "selected";?>><?=gettext("Read/Write");?></option>
		            <option value="wo" <?php if ("wo" === $pconfig['rwmode']) echo "selected";?>><?=gettext("Write only");?></option>
			        </select><br/>
			        <span class="vexpl"><?=gettext("This controls the access a remote host has to this share.");?></span>
			      </td>
			    </tr>
			    <tr>
			      <td width="22%" valign="top" class="vncell"><?=gettext("Maximum connections");?></td>
			      <td width="78%" class="vtable">
			        <input name="maxconnections" type="text" id="maxconnections" size="5" value="<?=htmlspecialchars($pconfig['maxconnections']);?>"><br/>
			        <span class="vexpl"><?=gettext("Maximum number of simultaneous connections. Default is 0 (unlimited).");?></span>
			      </td>
			    </tr>
			    <tr>
			      <td width="22%" valign="top" class="vncell"><?=gettext("Hosts allow");?></td>
			      <td width="78%" class="vtable">
			        <input name="hostsallow" type="text" class="formfld" id="hostsallow" size="60" value="<?=htmlspecialchars($pconfig['hostsallow']);?>"><br/>
			        <span class="vexpl"><?=gettext("This parameter is a comma, space, or tab delimited set of hosts which are permitted to access this share. Use the keyword ALL to permit access for everyone. Leave this field empty to disable this setting.");?></span>
			      </td>
			    </tr>
			    <tr>
			      <td width="22%" valign="top" class="vncell"><?=gettext("Hosts deny");?></td>
			      <td width="78%" class="vtable">
			        <input name="hostsdeny" type="text" class="formfld" id="hostsdeny" size="60" value="<?=htmlspecialchars($pconfig['hostsdeny']);?>"><br/>
			        <span class="vexpl"><?=gettext("This parameter is a comma, space, or tab delimited set of host which are NOT permitted to access this share. Where the lists conflict, the allow list takes precedence. In the event that it is necessary to deny all by default, use the keyword ALL (or the netmask 0.0.0.0/0) and then explicitly specify to the hosts allow parameter those hosts that should be permitted access. Leave this field empty to disable this setting.");?></span>
			      </td>
			    </tr>
			    <tr>
			      <td width="22%" valign="top">&nbsp;</td>
			      <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="<?=((isset($id) && $a_share[$id]))?gettext("Save"):gettext("Add")?>">
			        <?php if (isset($id) && $a_share[$id]):?>
			        <input name="id" type="hidden" value="<?=$id;?>">
			        <?php endif;?>
			      </td>
			    </tr>
			  </table>
			</form>
		</td>
	</tr>
</table>
<?php include("fend.inc");?>
