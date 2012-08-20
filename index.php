<?php
	require_once('config.php');

	chdir($admin);
	exec('git pull');

	chdir($keys);
	$keys = glob('*.pub');
	$userkeys = array();
	$users = array();
	foreach($keys as $i => $key)
	{
		$users[$i] = preg_replace('/(|@[^.]*)\.pub$/','',$key);
		if(!isset($userkeys[$users[$i]]))
			$userkeys[$users[$i]] = array();
		$userkeys[$users[$i]][] = $key;
	}
	$users = array_unique($users);
	$configuration = file($config);
	
	define('NONE', 0);
	define('REPOSITORY', 1);
	define('GROUP', 2);
	define('PERMISSION', 3);
	$mode = NONE;
	$groups = array();
	$permissions = array();
	$repo = null;
	foreach($configuration as $line)
	{
		if(trim($line) == '')
			$mode = NONE;

		else if(preg_match('/^(@[^\s]+)\s+=\s*(.*)/', $line, $parsed))
			$mode = GROUP;

		else if(preg_match('/^repo\s+(.+)/', $line, $parsed))
			$mode = REPOSITORY;

		else if(preg_match('/\s+([CDRW+]+)\s*=\s*(.*)/', $line, $parsed))
			$mode = PERMISSION;

		else
			die('Unmatched line "'.$line.'"');

		switch($mode)
		{
			case GROUP:
				if(trim($parsed[2]) == '')
					$members = array();
				else
					$members = preg_split('/\s+/', trim($parsed[2]));
				$groups[trim($parsed[1])] = $members;
				break;
			case REPOSITORY:
				$repo = preg_split('/\s+/', trim($parsed[1]));
				foreach($repo as $r)
					$permissions[$r] = array();
				break;
			case PERMISSION:
				if($repo)
					foreach($repo as $r)
						foreach(preg_split('/\s+/',trim($parsed[2])) as $group)
							if(isset($permissions[$r][$group]))
								$permissions[$r][$group] .= $parsed[1];
							else
								$permissions[$r][$group] = $parsed[1];
				break;
			case NONE:
				break;
		}
	}

	$usergroups = array();
	foreach($groups as $group => $members)
	{
		$user = false;
		foreach($members as $member)
			if(in_array($member, $users))
			{
				$user = true;
				break;
			}
		if(!$user)
			continue;
		$usergroups[] = $group;
	}
	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_POST['push']))
		{
			exec('git push');
			header('HTTP/1.0 303 See Other');
			header('Location: index.php');
			die();
		}
		$fp = fopen($config, 'w');

		if(!empty($_POST['username']) && $_FILES['publickey']['error'] == 0)
		{
			chdir($keys);
			$key = basename($_POST['username']) . '.pub';
			move_uploaded_file($_FILES['publickey']['tmp_name'], './'.$key);
			exec('git add ' . $key);
		}
		if(!empty($_POST['groupname']))
			$groups['@'.$_POST['groupname']] = array();

		if(isset($_POST['delete']))
		{
			chdir($keys);
			foreach($_POST['delete'] as $unsafedelete)
			{
				$delete = basename($unsafedelete);
				foreach($userkeys[$delete] as  $key)
				{
					unlink($key);
					exec('git rm ' . $key);
				}
				$filter = function($user)use($delete){ return $user != $delete; };
				foreach($usergroups as $group => $members)
					if(in_array($delete, $members))
						$usergroups[$group] = array_filter($usergroups[$group], $filter);
			}
		}
		if(isset($_POST['repoadd']))
		{
			foreach($_POST['repoadd'] as $group => $repo)
			{
				if(trim($repo) && isset($groups[$group]) && !in_array(trim($repo), $groups[$group]))
				{
					$groups[$group][] = trim($repo);
				}
			}
		}
		if(isset($_POST['reposubtract']))
		{
			foreach($_POST['reposubtract'] as $group => $repos)
			{
				$filter = function($member)use($repos){ return !in_array(trim($member), $repos); };
				$groups[$group] = array_filter($groups[$group], $filter);
				
			}
		}
		foreach($groups as $group => $members)
		{
			if(isset($_POST['members'][$group]))
				fprintf($fp, "%-15s = %s\n", $group, join(' ',$_POST['members'][$group]));
			else
				fprintf($fp, "%-15s = %s\n", $group, join(' ',$members));
		}
		fprintf($fp, "\n");
		if(isset($_POST['permission']))
		{
			$perms = array();
			foreach($_POST['permission'] as $repo => $groups)
			{
				$perms[$repo] = array();
				foreach($groups as $group => $perm)
				{
					if($perm[0] == 'C')
					{
						unset($perm[0]);
						$perms[$repo]['C'][] = $group;
					}
					$perm = join('',$perm);
					if(!empty($perm))
					{
						if(!isset($perms[$repo][$perm]))
							$perms[$repo][$perm] = array();
						$perms[$repo][$perm][] = $group;
					}
				}
			}
			foreach($perms as $repo => $rights)
			{
				foreach($rights as $perm => $groups)
					$perms[$repo][$perm] = sprintf("\t%s\t= %s\n", $perm, join(' ', $groups));
				$perms[$repo] = join('',$perms[$repo]);
			}
			$same = array();
			foreach($perms as $repo => $rights)
				if(!isset($same[$rights]))
					$same[$rights] = array($repo);
				else
					$same[$rights][] = $repo;

			foreach($same as $perms => $repos)
				fprintf($fp, "repo\t%s\n%s\n", join(' ',$repos), $perms);
		}
		fclose($fp);
		exec('git commit -am "Config changed from web by ' . $_SERVER['REMOTE_USER'] . '"');
		header('HTTP/1.0 303 See Other');
		header('Location: index.php');
		die();
	}
?>
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
	foreach($groups as $group => $members)
	{
		if(in_array($group, $usergroups))
			continue;
?>
		<li><a href="#<?php echo str_replace('@','coll_',$group); ?>">Collection <?php echo $group; ?></a></li>
<?php
	}
?>
		<li><a href="#config">Config file</a></li>
	</ul>
<div id="config">
<pre><?php echo join('', $configuration); ?></pre>
</div>
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
	foreach($usergroups as $group)
	{
?>
			<th><?php echo $group; ?></th>
<?php
	}
	foreach($groups as $group => $members)
		if(count($members) == 0)
		{
?>
			<th><?php echo $group; ?></th>
<?php
		}
?>
		</tr>
<?php
	foreach($users as $user)
	{
?>
		<tr>
			<td><input type="checkbox" name="delete[]" value="<?php echo $user; ?>" title="Remove this user" /></td>
			<td><?php echo $user; ?></td>
<?php
		foreach($usergroups as $group)
		{
?>
			<td><input type="checkbox" name="members[<?php echo $group; ?>][]" value="<?php echo $user; ?>" <?php if(in_array($user, $groups[$group])) echo 'checked="checked"'; ?> /></td>
<?php
		}
		foreach($groups as $group => $members)
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
	foreach($groups as $group => $members)
	{
		if(in_array($group, $usergroups))
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
		foreach($usergroups as $usergroup)
		{
			$any = isset($permissions[$group]) && isset($permissions[$group][$usergroup]);
?>
			<tr>
				<th><?php echo $usergroup; ?></th>
				<td><input type="checkbox" name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="C" <?php if($any && strpos($permissions[$group][$usergroup], 'C') !== false) echo 'checked="checked"'; ?> /></td>
				<td><input type="checkbox" name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="R" <?php if($any && strpos($permissions[$group][$usergroup], 'R') !== false) echo 'checked="checked"'; ?> /></td>
				<td><input type="checkbox" name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="W" <?php if($any && strpos($permissions[$group][$usergroup], 'W') !== false) echo 'checked="checked"'; ?> /></td>
				<td><input type="checkbox" name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="+" <?php if($any && strpos($permissions[$group][$usergroup], '+') !== false) echo 'checked="checked"'; ?> /></td>
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
