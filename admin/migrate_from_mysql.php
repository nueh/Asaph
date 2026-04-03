<?php
/**
 * MySQL → SQLite migration script.
 *
 * Run this after:
 *   1. Setting driver = 'sqlite' in lib/asaph_config.class.php
 *   2. Running admin/install.php to create the SQLite tables
 *
 * This script connects to a MySQL server, copies all users and posts
 * into the SQLite database, and flags any users whose passwords are
 * still stored as legacy md5 hashes (they will need to use
 * admin/migrate_passwords.php afterwards).
 *
 * Delete this file from the server when done.
 */
define( 'ASAPH_PATH', '../' );
require_once( ASAPH_PATH.'lib/asaph_config.class.php' );
require_once( ASAPH_PATH.'lib/db.class.php' );

header( 'Content-type: text/html; charset=utf-8' );

// Verify current config is SQLite
if( (Asaph_Config::$db['driver'] ?? 'mysql') !== 'sqlite' ) {
	die( '<p style="color:red"><strong>Error:</strong> '
		. '<code>Asaph_Config::$db[\'driver\']</code> must be set to '
		. '<code>\'sqlite\'</code> before running this script.</p>' );
}

$message = null;
$result  = null;

if( isset($_POST['migrate']) ) {
	$mysqlConfig = [
		'driver'   => 'mysql',
		'host'     => trim($_POST['host']),
		'database' => trim($_POST['database']),
		'user'     => trim($_POST['user']),
		'password' => $_POST['password'],
		'prefix'   => trim($_POST['prefix']),
	];

	$mysqlPostsTable = $mysqlConfig['prefix'] . 'posts';
	$mysqlUsersTable = $mysqlConfig['prefix'] . 'users';

	try {
		$src = new DB( $mysqlConfig );
		$dst = new DB( Asaph_Config::$db );

		// ── Users ────────────────────────────────────────────────────────
		$users = $src->query( "SELECT id, name, pass, loginId FROM `$mysqlUsersTable` ORDER BY id" );
		$usersMigrated = 0;
		$usersLegacy   = 0;

		foreach( $users as $u ) {
			// Skip if already exists by name
			$exists = $dst->query(
				'SELECT id FROM '.ASAPH_TABLE_USERS.' WHERE name = :1',
				$u['name']
			);
			if( !empty($exists) ) {
				continue;
			}

			$dst->insertRow( ASAPH_TABLE_USERS, [
				'name'    => $u['name'],
				'pass'    => $u['pass'],   // copied as-is; legacy md5 flagged below
				'loginId' => '',
			]);
			$usersMigrated++;

			if( preg_match('/^[0-9a-f]{32}$/', $u['pass']) ) {
				$usersLegacy++;
			}
		}

		// ── Posts ────────────────────────────────────────────────────────
		$posts = $src->query(
			"SELECT id, userId, hash, created, source, thumb, image, title
			 FROM `$mysqlPostsTable`
			 ORDER BY id"
		);
		$postsMigrated = 0;

		foreach( $posts as $p ) {
			// Skip duplicates by hash
			$exists = $dst->query(
				'SELECT id FROM '.ASAPH_TABLE_POSTS.' WHERE hash = :1',
				$p['hash']
			);
			if( !empty($exists) ) {
				continue;
			}

			$dst->insertRow( ASAPH_TABLE_POSTS, [
				'userId'  => $p['userId'],
				'hash'    => $p['hash'],
				'created' => $p['created'],   // DATETIME string, compatible with SQLite TEXT
				'source'  => $p['source'],
				'thumb'   => $p['thumb'],
				'image'   => $p['image'],
				'title'   => $p['title'],
			]);
			$postsMigrated++;
		}

		$result = [
			'success'       => true,
			'users'         => $usersMigrated,
			'users_legacy'  => $usersLegacy,
			'posts'         => $postsMigrated,
		];

	} catch( PDOException $e ) {
		$result = [ 'success' => false, 'error' => $e->getMessage() ];
	}
}

// Pre-fill form with existing MySQL credentials from config as a convenience
$prefill = Asaph_Config::$db;
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
<head>
	<title>MySQL → SQLite Migration — Asaph</title>
	<link rel="stylesheet" type="text/css" href="templates/admin.css" />
	<style>
		.ok   { color: #060; font-weight: bold; }
		.warn { background: #ffc; border: 1px solid #960; padding: .5em 1em; margin: 1em 0; }
		.err  { background: #fdd; border: 1px solid #c00; padding: .5em 1em; margin: 1em 0; }
		dl dt { width: 10em; }
	</style>
</head>
<body>
<div class="install">
	<h1>MySQL → SQLite Migration</h1>

	<?php if( $result !== null ) { ?>
		<?php if( $result['success'] ) { ?>
			<p class="ok">Migration complete.</p>
			<ul>
				<li>Users copied: <?php echo (int)$result['users']; ?></li>
				<li>Posts copied: <?php echo (int)$result['posts']; ?></li>
			</ul>
			<?php if( $result['users_legacy'] > 0 ) { ?>
				<div class="warn">
					<strong><?php echo (int)$result['users_legacy']; ?> user(s)</strong> still have legacy
					md5 passwords. Run <code>admin/migrate_passwords.php</code> to upgrade them to bcrypt
					before logging in.
				</div>
			<?php } ?>
			<p><strong>Delete <code>admin/migrate_from_mysql.php</code> from your server now.</strong></p>
		<?php } else { ?>
			<div class="err"><strong>Error:</strong> <?php echo htmlspecialchars($result['error']); ?></div>
		<?php } ?>
	<?php } ?>

	<p>Enter the credentials for the <strong>source MySQL server</strong> and click Migrate.
	   Posts and users already in the SQLite database (matched by hash / username) are skipped.</p>

	<form method="post">
		<dl>
			<dt>Host:</dt>
				<dd><input type="text" name="host"
					value="<?php echo htmlspecialchars($prefill['host'] ?? 'localhost'); ?>"/></dd>
			<dt>Database:</dt>
				<dd><input type="text" name="database"
					value="<?php echo htmlspecialchars($prefill['database'] ?? ''); ?>"/></dd>
			<dt>User:</dt>
				<dd><input type="text" name="user"
					value="<?php echo htmlspecialchars($prefill['user'] ?? ''); ?>"/></dd>
			<dt>Password:</dt>
				<dd><input type="password" name="password"/></dd>
			<dt>Table prefix:</dt>
				<dd><input type="text" name="prefix"
					value="<?php echo htmlspecialchars($prefill['prefix'] ?? 'asaph_'); ?>"/></dd>
			<dt>&nbsp;</dt>
				<dd><input type="submit" name="migrate" value="Migrate" class="button"/></dd>
		</dl>
	</form>
</div>
</body>
</html>
