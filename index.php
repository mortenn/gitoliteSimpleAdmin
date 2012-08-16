<?php
	$admin = '/var/www/data/gitolite-admin';
	$config = $admin.'/conf/gitolite.conf';
	$keys = $admin.'/keydir';

	chdir($keys);
	$users = glob('*.pub');
	foreach($users as $i => $user)
		$users[$i] = preg_replace('/\.pub$/','',$user);

	chdir($config);
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

		else if(preg_match('/\s+([RW+]+)\s*=\s*(.*)/', $line, $parsed))
			$mode = PERMISSION;
		else
			die('Unmatched line "'+$line+'"');

		switch($mode)
		{
			case GROUP:
				$groups[trim($parsed[1])] = preg_split('/\s+/', trim($parsed[2]));
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
		// TODO write to file rather than dump to browser
		header('Content-Type: text/plain');

		// TODO username+_FILE groupname

		if(isset($_POST['delete']))
		{
			foreach($_POST['delete'] as $delete)
			{
				// TODO Delete public key

				$filter = function($user)use($delete){ return $user != $delete; };
				foreach($groups as $group => $members)
					if(in_array($delete, $members))
					{
						$groups[$group] = array_filter($groups[$group], $filter);
					}
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
		foreach($groups as $group => $members)
			printf("%-15s = %s\n", $group, join(' ',$members));
		echo "\n";
		if(isset($_POST['permission']))
		{
			$perms = array();
			foreach($_POST['permission'] as $repo => $groups)
			{
				$perms[$repo] = array();
				foreach($groups as $group => $perm)
				{
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
					//$perms[$repo][$perm] = join(' ', $groups);
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
				printf("repo\t%s\n%s\n", join(' ',$repos), $perms);
		}
		die();
	}
?>
<form method="post" enctype="multipart/form-data">
	<table>
		<tr>
			<th>New user:</th>
			<td><input type="text" name="username" /></td>
			<td><input type="file" name="publickey" title="public key" /></td>
		</tr>
	</table>
	<table>
		<tr>
			<th>New group:</th>
			<td><input type="text" name="groupname" /></td>
		</tr>
	</table>
	<table>
		<tr><th>Remove users</th></tr>
<?php
	foreach($users as $user)
	{
?>
		<tr><td><input type="checkbox" name="delete[]" value="<?php echo $user; ?>" title="Remove this user" /> <?php echo $user; ?></td></tr>
<?php
	}
?>
	</table>
	<table>
		<tr><th colspan="3">Group membership</th></tr>
		<tr>
			<th>User</th>
<?php
	foreach($usergroups as $group)
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
			<td><?php echo $user; ?></td>
<?php
		foreach($usergroups as $group)
		{
?>
			<td><input type="checkbox" name="members[<?php echo $group; ?>][]" value="<?php echo $user; ?>" <?php if(in_array($user, $groups[$group])) echo 'checked="checked"'; ?> /></td>
<?php
		}
?>
		</tr>
<?php
	}
?>
	</table>
<?php
	foreach($groups as $group => $members)
	{
		if(in_array($group, $usergroups))
			continue;
?>
	<div style="clear:both">
	<table style="float:left">
		<tr><th>Repository collection <?php echo $group; ?></th></tr>
<?php
		foreach($members as $member)
		{
?>
		<tr>
			<td><input type="checkbox" name="reposubtract[<?php echo $group; ?>][]" value="<?php echo $member; ?>" /> <?php echo $member; ?></td>
		</tr>
<?php
		}
?>
		<tr><td>New: <input type="text" name="repoadd[<?php echo $group; ?>]" /></td></tr>
	</table>
	<table style="float:left">
		<tr><th>&nbsp;</th><th>R</th><th>W</th><th>+</th></tr>
<?php
		foreach($usergroups as $usergroup)
		{
?>
		<tr>
			<th><?php echo $usergroup; ?></th>
			<td><input type="checkbox" name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="R" <?php if(strpos($permissions[$group][$usergroup], 'R') !== false) echo 'checked="checked"'; ?> /></td>
			<td><input type="checkbox" name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="W" <?php if(strpos($permissions[$group][$usergroup], 'W') !== false) echo 'checked="checked"'; ?> /></td>
			<td><input type="checkbox" name="permission[<?php echo $group; ?>][<?php echo $usergroup; ?>][]" value="+" <?php if(strpos($permissions[$group][$usergroup], '+') !== false) echo 'checked="checked"'; ?> /></td>
		</tr>
<?php
		}
?>
	</table>
	</div>
<?php
	}
?>
	<div style="clear:both">
		<input type="submit" value="Save" />
	</div>
</form>
