<?php
	define('NONE', 0);
	define('REPOSITORY', 1);
	define('GROUP', 2);
	define('PERMISSION', 3);

	class GitoLitePath
	{
		public static $admin;
		public static $config;
		public static $keys;
	}

	class GitoLiteAdmin
	{
		public static function Update()
		{
			if(isset($_POST['push']))
			{
				self::Push();
				return;
			}

			if(!empty($_POST['username']) && $_FILES['publickey']['error'] == 0)
				self::AddUser($_POST['username'], $_FILES['publickey']['tmp_name']);

			if(!empty($_POST['groupname']))
				self::AddGroup($_POST['groupname']);

			if(isset($_POST['delete']))
				self::DeleteUsers($_POST['delete']);

			if(isset($_POST['repoadd']))
				self::AddRepository($_POST['repoadd']);

			if(isset($_POST['members']))
				self::AssignMembers($_POST['members']);

			if(isset($_POST['reposubtract']))
				self::DeleteRepositories($_POST['reposubtract']);

			if(isset($_POST['permission']))
				self::SetPermissions($_POST['permission']);

			GitoLiteConfig::Compile();
			file_put_contents(GitoLitePath::$config, join('',GitoLiteConfig::$configuration));

			self::Commit('Config changed from web by ' . $_SERVER['REMOTE_USER']);
		}

		public static function Pull()
		{
			chdir(GitoLitePath::$admin);
			exec('git pull');
		}

		public static function Commit($message)
		{
			chdir(GitoLitePath::$admin);
			exec('git commit -am "'.str_replace(array('"','$'),array('\"','\$'),$message).'"');
		}

		public static function Push()
		{
			chdir(GitoLitePath::$admin);
			exec('git push');
		}

		public static function Add($file)
		{
			exec('git add ' . $file);
		}

		public static function Remove($file)
		{
			exec('git rm ' . $file);
		}

		public static function AddUser($username, $keyfile)
		{
			chdir(GitoLitePath::$keys);
			$key = basename($username) . '.pub';
			move_uploaded_file($keyfile, './'.$key);
			self::Add($key);
		}

		public static function AddGroup($groupname)
		{
			if(!isset(GitoLiteConfig::$groups['@'.$groupname]))
				GitoLiteConfig::$groups['@'.$groupname] = array();
		}

		public static function AssignMembers($groups)
		{
			foreach($groups as $group => $members)
				GitoLiteConfig::$groups[$group] = $members;
			foreach(GitoLiteConfig::$usergroups as $group)
				if(!isset($groups[$group]))
					unset(GitoLiteConfig::$groups[$group]);
		}

		public static function DeleteUsers($delete)
		{
			chdir(GitoLitePath::$keys);
			foreach($delete as $unsafedelete)
			{
				$delete = basename($unsafedelete);
				foreach(GitoLiteConfig::$userkeys[$delete] as  $key)
				{
					unlink($key);
					self::Remove($file);
				}
				$filter = function($user)use($delete){ return $user != $delete; };
				foreach(GitoLiteConfig::$usergroups as $group => $members)
					if(in_array($delete, $members))
						GitoLiteConfig::$usergroups[$group] = array_filter(GitoLiteConfig::$usergroups[$group], $filter);
			}
		}

		public static function AddRepository($groups)
		{
			foreach($groups as $group => $repo)
			{
				if(trim($repo) && isset(GitoLiteConfig::$groups[$group]) && !in_array(trim($repo), GitoLiteConfig::$groups[$group]))
				{
					GitoLiteConfig::$groups[$group][] = trim($repo);
				}
			}
		}

		public static function DeleteRepositories($groups)
		{
			foreach($groups as $group => $repos)
			{
				$filter = function($member)use($repos){ return !in_array(trim($member), $repos); };
				GitoLiteConfig::$groups[$group] = array_filter(GitoLiteConfig::$groups[$group], $filter);
			}
		}

		public static function SetPermissions($permissions)
		{
			GitoLiteConfig::$permissions = $permissions;
		}
	}

	class GitoLiteConfig
	{
		public static $userkeys;
		public static $users;
    		public static $usergroups;
		public static $groups;
		public static $permissions;
		public static $configuration;

		public static function Load()
		{
			self::$configuration = file(GitoLitePath::$config);
			self::LoadUsers();
			self::ParseConfig();
		}

		public static function Compile()
		{
			self::$configuration = array();
			foreach(self::$groups as $group => $members)
				self::$configuration[] = sprintf("%-15s = %s\n", $group, join(' ',$members));
			self::$configuration[] = "\n";

			$perms = array();
			foreach(self::$permissions as $repo => $groups)
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
				self::$configuration[] = sprintf("repo\t%s\n%s\n", join(' ',$repos), $perms);
		}

		private static function LoadUsers()
		{
			chdir(GitoLitePath::$keys);
			self::$userkeys = array();
			$users = array();
			foreach(glob('*.pub') as $i => $key)
			{
				$users[$i] = preg_replace('/(|@[^.]*)\.pub$/','',$key);
				if(!isset(self::$userkeys[$users[$i]]))
					self::$userkeys[$users[$i]] = array();
				self::$userkeys[$users[$i]][] = $key;
			}
			self::$users = array_unique($users);
		}

		private static function ParseConfig()
		{
			$mode = NONE;
			self::$groups = array();
			self::$permissions = array();
			$repo = null;
			$parsed = false;
			foreach(self::$configuration as $line)
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
					// TODO replace by warning to user
					die('Unmatched line "'.$line.'"');

				switch($mode)
				{
					case GROUP:
						if(trim($parsed[2]) == '')
							$members = array();
						else
							$members = preg_split('/\s+/', trim($parsed[2]));
						self::$groups[trim($parsed[1])] = $members;
						break;
					case REPOSITORY:
						$repo = preg_split('/\s+/', trim($parsed[1]));
						foreach($repo as $r)
							self::$permissions[$r] = array();
						break;
					case PERMISSION:
						// TODO support C/D properly
						if($repo)
							foreach($repo as $r)
								foreach(preg_split('/\s+/',trim($parsed[2])) as $group)
								{
									if(!isset(self::$permissions[$r][$group]))
										self::$permissions[$r][$group] = '';
									self::$permissions[$r][$group] .= $parsed[1];
								}
						break;
					case NONE:
						break;
				}
			}
			self::$usergroups = array();
			foreach(self::$groups as $group => $members)
			{
				$user = false;
				foreach($members as $member)
					if(in_array($member, self::$users))
					{
						$user = true;
						break;
					}
				if(!$user)
					continue;
				self::$usergroups[] = $group;
			}
		}
	}
?>
