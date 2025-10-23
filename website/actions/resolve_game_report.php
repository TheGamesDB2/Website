<?php
require_once __DIR__ . "/../include/ErrorPage.class.php";
require_once __DIR__ . "/../include/login.common.class.php";

function returnJSONAndDie($code, $msg)
{
	echo json_encode(array("code" => $code, "msg" => $msg));
	die();
}

$tgdb_user = TGDBUser::getInstance();
if(!$tgdb_user->isLoggedIn())
{
	returnJSONAndDie(-1, ErrorPage::$MSG_NOT_LOGGED_IN_EDIT_ERROR);
}
else
{
	if(!$tgdb_user->hasPermission('STAFF'))
	{
		returnJSONAndDie(-1, ErrorPage::$MSG_NO_PERMISSION_TO_EDIT_ERROR);
	}
}

if(!isset($_REQUEST['id']) || !is_numeric($_REQUEST['id']))
{
	returnJSONAndDie(-1, ErrorPage::$MSG_MISSING_PARAM_ERROR);
}

require_once __DIR__ . "/../../include/TGDB.API.php";

try
{

	$API = TGDB::getInstance();
	

	$res = $API->ResolveGameReport($tgdb_user->GetUserID(), $tgdb_user->GetUsername(), $_REQUEST['id']);

	returnJSONAndDie(1, "success!!");
	

}
catch (Exception $e)
{
	error_log($e);
}
returnJSONAndDie(-1, "Unexpected Error has occured, Please try again!!");
