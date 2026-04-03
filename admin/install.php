<?php
if( version_compare(PHP_VERSION, '8.0.0') === -1 ) {
	die( "You need at least PHP 8.0.0 to run Asaph. Your current PHP version is ".PHP_VERSION );
}
define( 'ASAPH_PATH', '../' );

require_once( ASAPH_PATH.'lib/asaph_config.class.php' );
require_once( ASAPH_PATH.'lib/db.class.php' );

header( 'Content-type: text/html; charset=utf-8' );

$driver = Asaph_Config::$db['driver'] ?? 'mysql';

// Driver-specific CREATE TABLE statements
if( $driver === 'sqlite' ) {
	$createTablesSQL = array(
		'CREATE TABLE `'.ASAPH_TABLE_POSTS.'` (
			`id` INTEGER PRIMARY KEY AUTOINCREMENT,
			`userId` INTEGER NOT NULL,
			`hash` TEXT NOT NULL,
			`created` TEXT NOT NULL,
			`source` TEXT NOT NULL,
			`thumb` TEXT NOT NULL,
			`image` TEXT NOT NULL,
			`title` TEXT NOT NULL
		)',

		'CREATE TABLE `'.ASAPH_TABLE_USERS.'` (
			`id` INTEGER PRIMARY KEY AUTOINCREMENT,
			`name` TEXT NOT NULL,
			`pass` TEXT NOT NULL,
			`loginId` TEXT NOT NULL
		)',
	);
} else {
	$createTablesSQL = array(
		'CREATE TABLE `'.ASAPH_TABLE_POSTS.'` (
			`id` int(11) NOT NULL auto_increment,
			`userId` int(11) NOT NULL,
			`hash` char(32) NOT NULL,
			`created` datetime NOT NULL,
			`source` varchar(255) NOT NULL,
			`thumb` varchar(255) NOT NULL,
			`image` varchar(255) NOT NULL,
			`title` text NOT NULL,
			PRIMARY KEY  (`id`)
		) ENGINE=InnoDB CHARSET=utf8mb4',

		'CREATE TABLE `'.ASAPH_TABLE_USERS.'` (
			`id` int(11) NOT NULL auto_increment,
			`name` varchar(255) NOT NULL,
			`pass` varchar(255) NOT NULL,
			`loginId` char(32) NOT NULL,
			PRIMARY KEY  (`id`)
		) ENGINE=InnoDB CHARSET=utf8mb4',
	);
}


// Attempt a connection for requirement checks
$dbConnection = null;
$dbError = null;
try {
	$dbConnection = new DB( Asaph_Config::$db );
	// Force connect by running a trivial query
	$dbConnection->query( $driver === 'sqlite' ? 'SELECT 1' : 'SELECT 1' );
} catch( PDOException $e ) {
	$dbError = $e->getMessage();
	$dbConnection = null;
}

// Build requirements based on driver
if( $driver === 'sqlite' ) {
	$dbPath = ASAPH_PATH . (Asaph_Config::$db['path'] ?? 'data/asaph.db');
	$dbDir  = dirname( $dbPath );
	$dbRequirements = array(
		'SQLite database directory is writeable' => array(
			'message' => 'Make sure PHP can write to <code>'.htmlspecialchars($dbDir).'</code>. '
				. 'The database file will be created there.',
			'value' => is_dir($dbDir) && is_writeable($dbDir)
		),
		'PDO SQLite extension installed' => array(
			'message' => 'The <code>pdo_sqlite</code> PHP extension is required.',
			'value' => in_array( 'sqlite', PDO::getAvailableDrivers() )
		),
	);
} else {
	$dbRequirements = array(
		'Connection established' => array(
			'message' => 'Check your database settings in <code>lib/asaph_config.class.php</code>'
				. ( $dbError ? ': '.$dbError : '' ),
			'value' => ( $dbConnection !== null )
		),
		'MySQL Version >= 5.7' => array(
			'value' => $dbConnection && version_compare(
				(new PDO(
					'mysql:host='.Asaph_Config::$db['host'].';dbname='.Asaph_Config::$db['database'],
					Asaph_Config::$db['user'], Asaph_Config::$db['password']
				))->getAttribute( PDO::ATTR_SERVER_VERSION ), '5.7'
			) !== -1
		),
	);
}

$requirements = array(
	'File Access' => array(
		'Data directory is writeable' => array(
			'message' => 'Make sure PHP has the necessary priviliges (chmod) to write to the <code>data/</code> directory.',
			'value' => @is_writeable( ASAPH_PATH.'data' )
		),
	),

	'Database' => $dbRequirements,

	'PHP' => array(
		'cURL or URL fopen wrappers enabled' => array(
			'message' => 'Asaph needs <code>cURL</code> or <code>allow_url_fopen</code> to be enabled to copy images from other sites.',
			'value' => (
				is_callable( 'curl_init' ) ||
				iniEnabled( 'allow_url_fopen' )
			)
		),
		'GD library installed' => array(
			'message' => 'The GD library (PHP extension) is needed to manipulate images.',
			'value' => is_callable( 'gd_info' )
		)
	)
);


$suggestedPath = preg_replace( '#admin/install.php$#i', '', $_SERVER['SCRIPT_NAME'] );
$recommendations = array(
	'Asaph Config' => array(
		'Correct hostname set' => array(
			'message' =>
				'Make sure your <code>Asaph_Config::$domain</code> setting is correct. Environment variables '
				.'indicate a value of &quot;<code>'.$_SERVER['HTTP_HOST'].'</code>&quot;. If '
				.'<code>Asaph_Config::$domain</code> is not correctly set, you won\'t be able to use the bookmarklet.',
			'value' => ( $_SERVER['HTTP_HOST'] == Asaph_Config::$domain )
		),
		'Correct path set' => array(
			'message' =>
				'Make sure your <code>Asaph_Config::$absolutePath</code> setting is correct. Environment variables '
				.'indicate a value of &quot;<code>'.htmlspecialchars($suggestedPath).'</code>&quot;. If '
				.'<code>Asaph_Config::$absolutePath</code> is not correctly set, you won\'t be able to use the bookmarklet.',
			'value' => ( $suggestedPath == Asaph_Config::$absolutePath )
		)
	),
	'PHP Settings' => array(
		'Memory limit >= 4M' => array(
			'message' =>
				'If your <code>memory_limit</code> setting is too low, you might experience problems when posting '
				.'large images. For instance, PHP needs about 6mb of RAM to create a thumbnail from a 1600x1280 image.',
			'value' => ( humanToBytes(ini_get( 'memory_limit' )) > humanToBytes('4M') )
		)
	)
);


$requirementsMet = true;
foreach( array_keys($requirements) as $i ) {
	foreach( array_keys($requirements[$i]) as $j ) {
		if( empty($requirements[$i][$j]['value']) ) {
			$requirementsMet = false;
		}
	}
}


function humanToBytes( $s ) {
	$s = trim( $s );
	$last = strtolower( $s[strlen($s)-1] );
	switch( $last ) {
		case 'g': $s *= 1024;
		case 'm': $s *= 1024;
		case 'k': $s *= 1024;
	}

    return $s;
}

function iniEnabled( $s ) {
	return in_array(
		strtolower( ini_get( $s ) ),
		array( 'on', '1', 'true', 'yes' )
	);
}

function installAsaph( $adminName, $adminPass, &$sql, &$errors ) {
	$db = new DB( Asaph_Config::$db );

	if( $db->tableExists( ASAPH_TABLE_POSTS ) ) {
		$errors['table-exists'] = true;
		return false;
	}

	foreach( $sql as $q ) {
		if( !$db->query($q) ) {
			$errors['sql-error'] = $db->getError();
			return false;
		}
	}

	$db->insertRow( ASAPH_TABLE_USERS, array(
		'name'    => $adminName,
		'pass'    => password_hash( $adminPass, PASSWORD_DEFAULT ),
		'loginId' => ''
	));

	return true;
}


$mode = 'check';
$errors = array();
if(
	isset($_POST['install']) &&
	!empty($_POST['name']) &&
	!empty($_POST['pass']) &&
	!empty($_POST['pass2'])
) {
	if( $_POST['pass'] == $_POST['pass2'] ) {
		if( installAsaph( $_POST['name'], $_POST['pass'], $createTablesSQL, $errors ) ) {
			$mode = 'installed';
		} else {
			$mode = 'install-failed';
		}
	}
	else {
		$errors['passwords-not-equal'] = true;
	}
} else if( isset($_POST['install']) ) {
	$errors['missing-data'] = true;
}
 ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
<head>
	<title>Install: Asaph</title>
	<link rel="stylesheet" type="text/css" href="templates/admin.css" />
	<link rel="Shortcut Icon" href="templates/asaph.ico" />
</head>
<body>

<div class="install">
	<?php if( $mode == 'check' ) { ?>
		<h1>Welcome to Asaph!</h1>
		<p>
			Install Asaph in 3 simple steps:
		</p>

		<ol>
			<li>Edit your settings in <code>lib/asaph_config.class.php</code></li>
			<li>Set the chmod of the <code>data/</code> directory so PHP can write to it</li>
			<li>Enter the name and password for your first user below and click install</li>
		</ol>

		<p>
			The following is the result of a short system check. <strong>All requirements must be met in order to
			install Asaph.</strong>
			Database driver: <strong><?php echo htmlspecialchars($driver); ?></strong>
		</p>

		<h1>Requirements</h1>
		<?php foreach( $requirements as $collectionTitle => $collection ) {?>
			<div class="collection">
				<h2><?php echo $collectionTitle; ?></h2>
				<?php foreach( $collection as $title => $r ) {?>
					<?php if( $r['value'] ) { ?>
						<div class="check ok">
							<h3><?php echo $title; ?>: OK</h3>
						</div>
					<?php } else { ?>
						<div class="check failed">
							<h3><?php echo $title; ?>: FAILED</h3>
							<?php if( isset($r['message']) ) { ?>
								<p><?php echo $r['message']; ?></p>
							<?php } ?>
						</div>
					<?php } ?>
				<?php } ?>
			</div>
		<?php } ?>

		<h1>Recommendations</h1>
		<?php foreach( $recommendations as $collectionTitle => $collection ) {?>
			<div class="collection">
				<h2><?php echo $collectionTitle; ?></h2>
				<?php foreach( $collection as $title => $r ) {?>
					<?php if( $r['value'] ) { ?>
						<div class="check ok">
							<h3><?php echo $title; ?>: OK</h3>
						</div>
					<?php } else { ?>
						<div class="check failed">
							<h3><?php echo $title; ?>: FAILED</h3>
							<?php if( isset($r['message']) ) { ?>
								<p><?php echo $r['message']; ?></p>
							<?php } ?>
						</div>
					<?php } ?>
				<?php } ?>
			</div>
		<?php } ?>

		<a name="form"></a>
		<?php if( $requirementsMet ) { ?>
			<form action="install.php#form" method="post">
				<h1>User Settings</h1>
				<p>
					Please enter the name and password for the admin user and click <em>Install Asaph</em>
					to create the database tables.
				</p>
				<?php if( isset($errors['passwords-not-equal']) ) { ?>
					<div class="warn">The passwords you entered did not match!</div>
				<?php } ?>
				<?php if( isset($errors['missing-data']) ) { ?>
					<div class="warn">Please enter a name and a password!</div>
				<?php } ?>
				<dl>
					<dt>Name:</dt>
						<dd><input type="text" name="name"/></dd>
					<dt>Password:</dt>
						<dd><input type="password" name="pass"/></dd>
					<dt>(repeat):</dt>
						<dd><input type="password" name="pass2"/></dd>
					<dt>&nbsp;</dt>
						<dd><input type="submit" class="button" name="install" value="Install Asaph"/></dd>
				</dl>
			</form>
		<?php } ?>
	<?php } else if( $mode == 'install-failed' ) {?>
		<h1>Installation failed</h1>
		<?php if( isset($errors['table-exists']) ) { ?>
			<div class="warn">The Asaph Posts-Table exists. Is Asaph already installed?</div>
		<?php } ?>
		<?php if( isset($errors['sql-error']) ) { ?>
			<div class="warn"><?php echo $errors['sql-error']; ?></div>
		<?php } ?>
	<?php } else if( $mode == 'installed' ) {?>
		<h1>Installation successful</h1>
		<p>
			Please remove this file (admin/install.php) from your server now.
		</p>
		<p>
			You may head over to the <a href="<?php echo Asaph_Config::$absolutePath ?>admin/">admin menu</a> or vist
			<a href="<?php echo Asaph_Config::$absolutePath ?>">your newly created Asaph-Blog</a>.
		</p>
	<?php } ?>
</div>

</body>
</html>
