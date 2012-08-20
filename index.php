<?php
	require_once('gitolite.php');
	require_once('config.php');

	GitoLiteAdmin::Pull();
	GitoLiteConfig::Load();

	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		GitoLiteAdmin::Update();
		header('HTTP/1.0 303 See Other');
		header('Location: index.php');
		die();
	}

	require('editor.php');
?>
