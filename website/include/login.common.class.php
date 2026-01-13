<?php
// Include session configuration first to ensure proper session handling across subdomains
require_once __DIR__ . "/../../include/session.config.php";
require_once __DIR__ . "/../../include/config.class.php";
if(Config::$debug)
{
	require __DIR__ . "/login.pseudo.class.php";
}
else
{
	require __DIR__ . "/login.phpbb.class.php";
	require __DIR__ . "/login.tgdb.class.php";
}
