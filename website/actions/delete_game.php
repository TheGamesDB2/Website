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

if(!isset($_REQUEST['game_id']) || !is_numeric($_REQUEST['game_id']))
{
	returnJSONAndDie(-1, ErrorPage::$MSG_MISSING_PARAM_ERROR);
}

require_once __DIR__ . "/../../include/TGDB.API.php";
require_once __DIR__ . "/../include/DiscordUtils.class.php";

try
{

	$API = TGDB::getInstance();
	$filters = ['game_title' => true, 'overview' => true, 'youtube' => true, 'release_date' => true, 'players' => true, 'coop' => true, 'developers' => true, 'publishers' => true, 'genres' => true, 'rating' => true];
	$games = $API->GetGameByID($_REQUEST['game_id'], 0, 1, $filters);
	if(empty($games))
	{
		returnJSONAndDie(0, "No game in record to delete.");
	}

	$covers = $API->GetGameBoxartByID($_REQUEST['game_id'], 0, 99, 'ALL');

	if(!empty($covers) && ($covers = $covers[$_REQUEST['game_id']]))
	{
		$sizes = ["original", "small", "thumb", "cropped_center_thumb", "medium", "large"];
		foreach($covers as $cover)
		{
			foreach($sizes as $size)
			{
				$image_to_delete = __DIR__ . "/../../cdn/images/$size/" . $cover->filename;
				if(file_exists($image_to_delete))
				{
					unlink($image_to_delete);
				}
			}
		}
	}

	$res = $API->DeleteGame($tgdb_user->GetUserID(), $_REQUEST['game_id']);
	DiscordUtils::PostGameUpdate($tgdb_user, [], $games[0], 2);
	returnJSONAndDie(1, "success!!");
	

}
catch (Exception $e)
{
	error_log($e);
}
returnJSONAndDie(-1, "Unexpected Error has occured, Please try again!!");
