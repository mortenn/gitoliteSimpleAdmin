<!doctype html>
<html>
	<style> html { background: black; } </style> 
	<script src="http://code.jquery.com/jquery-latest.js"></script>
	<script src="http://code.jquery.com/ui/1.8.23/jquery-ui.min.js"></script>
	<script>
		$(function(){
			$('#reposetup').tabs(); $('input[type=submit]').button();
			$('input[type=checkbox]').not(':checked').fadeTo(0,0.4);
		});
	</script>
	<link rel="stylesheet" href="http://code.jquery.com/ui/1.8.23/themes/ui-darkness/jquery-ui.css" />
	<form method="post" enctype="multipart/form-data">
		<div id="reposetup">
			<ul>
				<li><a href="#users">Users</a></li>
<?php
	foreach(GitoLiteConfig::$groups as $group => $members)
	{
		if(in_array($group, GitoLiteConfig::$usergroups))
			continue;
?>
				<li><a href="#<?php echo str_replace('@','coll_',$group); ?>">Collection <?php echo $group; ?></a></li>
<?php
	}
?>
				<li><a href="#config">Config file</a></li>
			</ul>
			<div id="config"><pre><?php echo join('', GitoLiteConfig::$configuration); ?></pre></div>
			<div id="users">
				<table>
					<tr>
						<th style="text-align:right">New user:</th>
						<td><input type="text" name="username" /> <input type="file" name="publickey" title="public key" /></td>
					</tr>
					<tr>
						<th style="text-align:right">New group:</th>
						<td><input type="text" name="groupname" /></td>
					</tr>
				</table>
				<table>
					<tr><th colspan="3">Group membership</th></tr>
					<tr>
						<th>X</th>
						<th>User</th>
<?php
	foreach(GitoLiteConfig::$usergroups as $group)
	{
?>
						<th><?php echo $group; ?></th>
<?php
	}
	foreach(GitoLiteConfig::$groups as $group => $members)
		if(count($members) == 0)
		{
?>
						<th><?php echo $group; ?></th>
<?php
		}
?>
					</tr>
<?php
	foreach(GitoLiteConfig::$users as $user)
	{
?>
					<tr>
						<td><input type="checkbox" name="delete[]" value="<?php echo $user; ?>" title="Remove this user" /></td>
						<td><?php echo $user; ?></td>
<?php
		foreach(GitoLiteConfig::$usergroups as $group)
		{
?>
						<td><input type="checkbox" name="members[<?php echo $group; ?>][]" value="<?php echo $user; ?>" <?php if(in_array($user, GitoLiteConfig::$groups[$group])) echo 'checked="checked"'; ?> /></td>
<?php
		}
		foreach(GitoLiteConfig::$groups as $group => $members)
			if(count($members) == 0)
			{
?>
						<td><input type="checkbox" name="members[<?php echo $group; ?>][]" value="<?php echo $user; ?>" /></td>
<?php
			}
?>
					</tr>
<?php
	}
?>
				</table>
				<div style="clear:both"></div>
			</div>
<?php
	foreach(GitoLiteConfig::$groups as $group => $members)
	{
		if(in_array($group, GitoLiteConfig::$usergroups))
			continue;
?>
			<div id="<?php echo str_replace('@','coll_',$group); ?>">
				<table style="float:left">
					<tr><th>Repository collection <?php echo $group; ?></th></tr>
<?php
		foreach($members as $member)
		{
?>
					<tr>
						<td><input title="Remove this repository pattern from this collection" type="checkbox" name="reposubtract[<?php echo $group; ?>][]" value="<?php echo $member; ?>" /> <?php echo $member; ?></td>
					</tr>
<?php
		}
?>
					<tr><td>New: <input type="text" name="repoadd[<?php echo $group; ?>]" /></td></tr>
				</table>
				<table style="float:left">
					<tr><th>&nbsp;</th><th title="Create repos">C*</th><th>R</th><th>W</th><th>+</th></tr>
<?php
		foreach(GitoLiteConfig::$usergroups as $usergroup)
		{
			$any = isset(GitoLiteConfig::$permissions[$group]) && isset(GitoLiteConfig::$permissions[$group][$usergroup]);
?>
					<tr>
						<th><?php echo $usergroup; ?></th>
						<td><input type="checkbox"
							name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="C"
							<?php if($any && strpos(GitoLiteConfig::$permissions[$group][$usergroup], 'C') !== false) echo 'checked="checked"'; ?>
							/></td>
						<td><input type="checkbox"
							name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="R"
							<?php if($any && strpos(GitoLiteConfig::$permissions[$group][$usergroup], 'R') !== false) echo 'checked="checked"'; ?>
							/></td>
						<td><input type="checkbox"
							name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="W"
							<?php if($any && strpos(GitoLiteConfig::$permissions[$group][$usergroup], 'W') !== false) echo 'checked="checked"'; ?>
							/></td>
						<td><input type="checkbox"
							name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="+"
							<?php if($any && strpos(GitoLiteConfig::$permissions[$group][$usergroup], '+') !== false) echo 'checked="checked"'; ?>
							/></td>
					</tr>
<?php
		}
?>
				</table>
			</div>
<?php
	}
?>
			<div style="clear:both"></div>
		</div>
		<input type="submit" value="Save" />
		<input type="submit" value="Push" name="push" />
	</form>
</html>

