<?php
require_once __DIR__ . "/../../include/CommonUtils.class.php";
session_start();
require_once __DIR__ . "/login.common.class.php";
$_user = phpBBUser::getInstance();
FOOTER::$_time_start = microtime(true);

class HEADER
{
	private $_title = "TheGamesDB";
	private $_printExtraHeader;

	public function __construct()
	{
		if(!isset($_SESSION['style']))
		{
			$_SESSION['style'] = 5;
		}

		if(isset($_REQUEST['style']))
		{
			$_SESSION['style'] = $_REQUEST['style'];
		}
	}
	public function setTitle($title)
	{
		$this->_title = $title;
	}

	public function appendRawHeader($fun)
	{
		$this->_printExtraHeader = $fun;
	}
	public function print()
	{ global $_user;?>
<!doctype html>
<html lang="en">

<head>
	<title><?= $this->_title ?></title>
	<meta charset="utf-8">
	<!-- allow site to adapt to mobile
		 TODO: add check for mobile desktop toggle -->
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<!-- Bootstrap CSS -->
	<?php
		// NOTE: this is a placeholder for now, we need to decided if we want to have multi-theme support or choose 1
		switch($_SESSION['style'])
		{
			case 1:
				echo '<link rel="stylesheet" href="/css/darkly-bootstrap.min.css" crossorigin="anonymous">';
				break;
			case 2:
				echo '<link rel="stylesheet" href="/css/litera-bootstrap.min.css" crossorigin="anonymous">';
				break;
			case 3:
				echo '<link rel="stylesheet" href="/css/superhero-bootstrap.min.css" crossorigin="anonymous">';
				break;
			case 4:
				echo '<link rel="stylesheet" href="/css/minty-bootstrap.min.css" crossorigin="anonymous">';
				break;
			case 5:
				echo '<link rel="stylesheet" href="/css/lux-bootstrap.min.css" crossorigin="anonymous">';
				echo "<style>body { color: #424649; } </style>";
				break;
			default:
				echo '<link rel="stylesheet" href="/css/materia-bootstrap.min.css" crossorigin="anonymous">';
		}
	?>
	<script type="text/javascript" src="/js/jquery-3.2.1.min.js"></script>
	<script type="text/javascript" src="/js/popper.min.1.13.0.js"></script>
	<script type="text/javascript" src="/js/bootstrap.min.4.0.0.js"></script>
	<script type="text/javascript" src="/js/fontawesome.5.0.10.js"></script>
	<script type="text/javascript" src="/js/brands.5.0.10.js"></script>
	<link rel="stylesheet" href="/css/fontawesome.5.0.10.css" crossorigin="anonymous">
	<link rel="stylesheet" href="/css/fa-brands.5.0.10.css" crossorigin="anonymous">
	<link rel="stylesheet" href="/css/main.css" crossorigin="anonymous">
	<?php if(isset($this->_printExtraHeader)) : call_user_func($this->_printExtraHeader); endif; ?>
	<?php if(!$_user->isLoggedIn()) : ?>
	<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5337615406686584"
     crossorigin="anonymous"></script>
	 <?php endif; ?>
</head>
<body>
	<?php 
		if ($_SERVER['HTTP_HOST'] == "dev.thegamesdb.net")
		{
			echo "<div id=\"dev\" style=\"background-color: red; min-width:100%; color:white; font-size:30px; text-align: center;\">DEV SITE</div>";
		}
	?>
	<nav class="navbar navbar-expand-lg navbar-dark bg-primary" style="margin: 10px;">
		<a class="navbar-brand" href="/">TheGamesDB</a>
		<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarColor01" aria-controls="navbarColor01" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="collapse navbar-collapse" id="navbarColor01">
			<ul class="navbar-nav mr-auto">
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					Browse
					</a>
					<div class="dropdown-menu" aria-labelledby="navbarDropdown">
						<a class="dropdown-item" href="/browse.php">Games</a>
						<a class="dropdown-item" href="/list_platforms.php">Platforms</a>
						<a class="dropdown-item" href="/list_devs.php">Developers</a>
						<a class="dropdown-item" href="/list_pubs.php">Publishers</a>
						<a class="dropdown-item" href="/stats.php">Stats</a>
					</div>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="/add_game.php">Add New Game</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="https://discord.gg/2gxeAURxmA">Discord</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="https://www.patreon.com/thegamesdb" target="_blank">Patreon</a>
				</li>
			</ul>
			<form id="search-form" action="/search.php" method="get" class="form-inline my-2 my-lg-0">
				<input name="name" type="text" placeholder="Search">
				<button type="submit"><i class="fa fa-search" title="Search"></i></button>
			</form>
			<ul class="navbar-nav my-2 my-lg-0">
				<?php if($_user->isLoggedIn()) : ?>
				<div class="nav-item mr-0 dropdown">
					<button class="btn btn-link dropdown-toggle font-weight-bold" style="color:white;" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<img width="25px" src="<?= $_user->GetAvatar() ?>" style="border-radius: 50%;">
						<?= $_user->GetUsername() ?> <span class="nav-link badge badge-pill badge-<?= ($_user->GetNotificationCount() + $_user->GetPMCount()) == 0 ? 'dark' : 'danger' ?>"> <?= $_user->GetNotificationCount() + $_user->GetPMCount(); ?> </span>
						<span class="glyphicon glyphicon-star" aria-hidden="true"></span>
						<span class="caret"></span>
					</button>
					<div class="dropdown-menu " aria-labelledby="dropdownMenuButton">
						<a class="dropdown-item" href="/my_games.php">My Games</a>
						<div class="dropdown-divider"></div>
						<?php if($_user->hasPermission('m_delete_games')) : ?>
						<a class="dropdown-item" href="/add_dev_pub.php">Add Devs/Pubs</a>
						<a class="dropdown-item" href="/merge_dev_pub.php">Merge Devs/Pubs</a>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item" href="/add_platform.php">Add Platform</a>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item" href="/report_review.php">Duplicates Reports</a>
						<?php endif; ?>
						<a class="dropdown-item" href="<?= append_sid("/login.php", 'logout', false, $_user->GetUserSessionID()); ?>">Logout</a>
					</div>
				</div>
				<?php else : ?>
				<li class="nav-item mr-0">
					<a class="nav-link" href="/login.php">Log in</a>
				</li>
				<?php endif; ?>
			</ul>
		</div>
	</nav>
	<?php }// endfunction print()
}
?>
<?php
class FOOTER
{
	public static $_time_start = 0;
	public static function print()
	{ global $_user;?>
		<footer class="container-fluid bg-dark" style="margin-top:10px; padding: 20px;">
			<div class="container">
				<div class="row">
					<div class="col-lg-3">
						<h2 class="logo"><a href="/"> TheGamesDB </a></h2>
					</div>
					<div class="col-sm-4 col-lg-2">
						<h5>Get started</h5>
						<ul>
							<li><a href="/">Home</a></li>
							<?php if(empty($_user) || !$_user->isLoggedIn()) : ?>
							<li><a href="https://discord.gg/2gxeAURxmA">Request Account on Discord</a></li>
							<?php endif; ?>
							<li><a href="/browse.php">Games</a></li>
							<li><a href="/list_platforms.php">Platforms</a></li>
							<li><a href="/list_devs.php">Developers</a></li>
							<li><a href="/list_pubs.php">Publishers</a></li>
							<li><a href="/stats.php">Stats</a></li>
						</ul>
					</div>
					<div class="col-sm-4 col-lg-3">
						<h5>Developers</h5>
						<ul>
							<li><a href="https://api.thegamesdb.net/">API Documentation</a></li>
							<li><a href="https://api.thegamesdb.net/key.php">API Access Request</a></li>
							<li><a href="https://github.com/TheGamesDB2/Website">Github Repo</a></li>
						</ul>
					</div>
					<?php if(false) : ?>
					<div class="col-lg-2">
						<h5>About us</h5>
						<ul>
							<li><a href="#">Company Information</a></li>
							<li><a href="#">Contact us</a></li>
							<li><a href="#">Reviews</a></li>
						</ul>
					</div>
					<div class="col-lg-2">
						<h5>Support</h5>
						<ul>
							<li><a href="#">FAQ</a></li>
							<li><a href="#">Help desk</a></li>
						</ul>
					</div>
					<?php endif;?>
					<div class="col-sm-4 col-lg-3">
						<div class="social-networks">
							<a href="https://twitter.com/thegamesdb" class="twitter"><i class="fab fa-twitter"></i></a>
							<a href="https://www.facebook.com/thegamesdb/" class="facebook"><i class="fab fa-facebook"></i></a>
						</div>
						<a href="https://discord.gg/2gxeAURxmA" role="button" class="btn btn-info">Contact us</a>
					</div>
				</div>

				<div class="footer-copyright">
					<p>© <?= date("Y") ?> TheGamesDB </p>
				</div>
				<p>execution time: <?= microtime(true) - FOOTER::$_time_start ?></p>
			</div>
		</footer>
	</body>
</html>
	<?php }
}

?>
