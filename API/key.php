<?php
// Debug information
echo "<h1>Session Debug Information</h1>";
echo "<pre>";

// Show current session status
echo "Session Status: ";
switch(session_status()) {
    case PHP_SESSION_DISABLED:
        echo "Sessions are disabled\n";
        break;
    case PHP_SESSION_NONE:
        echo "Sessions are enabled, but no session exists\n";
        break;
    case PHP_SESSION_ACTIVE:
        echo "Sessions are enabled, and a session exists\n";
        break;
}

// Show session ID
echo "\nSession ID: " . session_id() . "\n";

// Show session cookie parameters
echo "\nSession Cookie Parameters:\n";
print_r(session_get_cookie_params());

// Show domain information
echo "\nDomain Information:\n";
echo "HTTP_HOST: " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'Not set') . "\n";
echo "SERVER_NAME: " . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'Not set') . "\n";

// Include session configuration first to ensure proper session handling across subdomains
require_once __DIR__ . "/../include/session.config.php";

// Show session status after configuration
echo "\nSession Status After Config: ";
switch(session_status()) {
    case PHP_SESSION_DISABLED:
        echo "Sessions are disabled\n";
        break;
    case PHP_SESSION_NONE:
        echo "Sessions are enabled, but no session exists\n";
        break;
    case PHP_SESSION_ACTIVE:
        echo "Sessions are enabled, and a session exists\n";
        break;
}

echo "\nSession ID After Config: " . session_id() . "\n";

require_once __DIR__ . "/../website/include/login.common.class.php";

// Show session data
echo "\nSession Data:\n";
print_r($_SESSION);

$key = "NA";
$tgdb_user = TGDBUser::getInstance();

// Show TGDBUser information
echo "\nTGDBUser Information:\n";
echo "Is Logged In: " . ($tgdb_user->isLoggedIn() ? 'Yes' : 'No') . "\n";
echo "User ID: " . $tgdb_user->GetUserID() . "\n";
echo "Username: " . $tgdb_user->GetUsername() . "\n";

// Show cookies
echo "\nCookies:\n";
print_r($_COOKIE);

echo "</pre>";

if($tgdb_user->isLoggedIn())
{
	if($tgdb_user->hasPermission('API_ACCESS'))
	{
	require_once __DIR__ . "/../API/include/APIAccessDB.class.php";
	$auth = APIAccessDB::getInstance();
	$key = $auth->RequestPublicAPIKey($tgdb_user->GetUserID());
	$private_key = $auth->RequestPrivateAPIKey($tgdb_user->GetUserID());
	if(!is_object($private_key))
	{
		$private_key = new stdClass();
		$private_key->key = "NA";
		$private_key->extra_allowance = "NA";

	}
}
}

?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8" />
	<title>TheGamesDB API DOCs</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
	<link href='//fonts.googleapis.com/css?family=Lato:300' rel='stylesheet' type='text/css'>
	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js" integrity="sha384-cs/chFZiN24E4KMATLdqdvsezGxaGsi4hLGOzlXwp5UZB1LY//20VyM2taTB4QvJ" crossorigin="anonymous"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js" integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm" crossorigin="anonymous"></script>
	<style>
		body {
			margin: 50px 0 0 0;
			padding: 0;
			width: 100%;
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			text-align: center;
			color: #aaa;
			font-size: 18px;
		}
		
		h1 {
			color: #719e40;
			letter-spacing: -3px;
			font-family: 'Lato', sans-serif;
			font-size: 100px;
			font-weight: 200;
			margin-bottom: 0;
		}
	</style>
</head>

<body>
	<h1>API Keys</h1>
	<div></div>

	<div class="container">
		<div class="row">
			<div class="col">
				<?php if(!$tgdb_user->isLoggedIn() ) : ?>
				<h3>You must be logged in to the site to view your api key.</h3>
				<?php elseif($tgdb_user->isLoggedIn() && !$tgdb_user->hasPermission('API_ACCESS')) : ?>
				<h3>You don't currently have permission to access the API.</h3>
				<p>Please contact us on <a href="https://discord.gg/NCRVtMAe">discord</a> to request permission.</p>
				<?php elseif($tgdb_user->isLoggedIn() && $tgdb_user->hasPermission('API_ACCESS')) : ?>

				<div class="card" style="margin-bottom:10px;">
					<div class="card-header">
						Public API Key: <?= $key ?>
					</div>
					<div class="card-body">
						<p class="card-text">This key has a limit per IP.</p>
						<p class="card-text">This key should be used in your application.</p>
					</div>
				</div>

				<div class="card" style="margin-bottom:10px;">
					<div class="card-header">
						Private API Key: <?= $private_key->apikey ?>
						<p>Remaining Requests: <?= $private_key->extra_allowance ?>/6000<p/>
					</div>
					<div class="card-body">
						<p class="card-text">This key would contain a higher one time request rate limit,
							but the key must not be made available to the general public, as it has a shared limit and not an IP based one.</p>
						<p class="card-text">This key can be used to create an initial mirror of the required data on your server,
							which you can then subsquantly updated using the Update endpoint using the public key.</p>
						<p class="card-text">Please Note: this key should only be used server side.
							<p/>

							
					</div>
				</div>
				<?php endif;?>

			</div>
		</div>

	</div>
</body>

</html>
