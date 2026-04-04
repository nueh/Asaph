<?php

class Asaph_Config {

	// This title is used in templates and the rss feed
	public static $title = 'Asaph ~ Phoboslab';

	// Domain name and path where Asaph is installed in
	public static $domain = 'subdomain.yourdomain.com';
	public static $absolutePath = '/asaph/';

	// If you want to be able to move/edit/delete generated files and folders
	// with your ftp-client, it's likely you'll have to set chmod to 0777
	public static $defaultChmod = 0777;

	public static $postsPerPage = 9;
	public static $adminPostsPerPage = 9;

	// Templates
	public static $templates = array(
		'posts' => 'templates/whiteout/posts.html.php',
		'about' => 'templates/whiteout/about.html.php',
		'feed' => 'templates/rss.xml.php'
	);

	// Database settings.
	// Set driver to 'sqlite' for a file-based database (no server required),
	// or 'mysql' to use a MySQL/MariaDB server.
	//
	// SECURITY: The SQLite database file must NOT be stored inside the web
	// root. Set 'path' to an absolute path outside the publicly served
	// directory (e.g. '/var/db/asaph.db'). The default '../asaph.db' places
	// it one level above the web root, which is safe on most setups.
	public static $db = array(
		'driver'   => 'sqlite',           // 'sqlite' or 'mysql'
		'path'     => '../asaph.db',      // SQLite only: MUST be outside web root

		'host'     => 'localhost',         // MySQL only
		'database' => 'asaph',            // MySQL only
		'user'     => 'root',             // MySQL only
		'password' => '',                 // MySQL only
		'prefix'   => 'asaph_'
	);

	// Image and thumbnail settings
	public static $images = array(
		'imagePath' => 'data/images/',
		'thumbPath' => 'data/thumbs/',
		'thumbWidth' => 256,
		'thumbHeight' => 192,
		'jpegQuality' => 80,
	);
}


// Don't edit anything below here, unless you know what you're doing

define( 'ASAPH_TABLE_POSTS',	Asaph_Config::$db['prefix'].'posts' );
define( 'ASAPH_TABLE_USERS',	Asaph_Config::$db['prefix'].'users' );

$protocol = (
	(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
	$_SERVER['SERVER_PORT'] == 443 ||
	(isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443)
) ? "https://" : "http://";

define( 'ASAPH_BASE_URL',		$protocol.Asaph_Config::$domain.Asaph_Config::$absolutePath );
define( 'ASAPH_POST_PHP',		ASAPH_BASE_URL.'admin/post.php' );
define( 'ASAPH_POST_JS',		ASAPH_BASE_URL.'admin/post.js.php' );
define( 'ASAPH_BOOKMARK_POST_JS',	'window.location.protocol%2B\'//'.Asaph_Config::$domain.Asaph_Config::$absolutePath.'admin/post.js.php' );
define( 'ASAPH_POST_CSS',		ASAPH_BASE_URL.'admin/templates/post.css' );

?>
