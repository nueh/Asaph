<?php
/**
 * One-time password migration script.
 * Run this AFTER deploying the modern PHP code and AFTER running the ALTER TABLE.
 * Delete this file from the server immediately after use.
 *
 * Required SQL (run once before this script):
 *   ALTER TABLE asaph_users MODIFY pass VARCHAR(255) NOT NULL;
 */
define( 'ASAPH_PATH', '../' );
require_once( ASAPH_PATH.'lib/asaph_config.class.php' );

header( 'Content-type: text/html; charset=utf-8' );

try {
	$pdo = new PDO(
		'mysql:host='.Asaph_Config::$db['host'].';dbname='.Asaph_Config::$db['database'].';charset=utf8',
		Asaph_Config::$db['user'],
		Asaph_Config::$db['password'],
		[ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
	);
} catch( PDOException $e ) {
	die( 'Database connection failed: ' . htmlspecialchars($e->getMessage()) );
}

$table = Asaph_Config::$db['prefix'] . 'users';
$message = null;

// Handle password reset form submission
if( isset($_POST['reset']) && !empty($_POST['id']) && !empty($_POST['pass']) ) {
	$id   = (int) $_POST['id'];
	$hash = password_hash( $_POST['pass'], PASSWORD_DEFAULT );
	$stmt = $pdo->prepare( "UPDATE `$table` SET pass = ? WHERE id = ?" );
	$stmt->execute( [$hash, $id] );
	$message = "Password updated for user ID $id.";
}

// Fetch all users, flag which still have legacy md5 hashes (32 hex chars)
$users = $pdo->query( "SELECT id, name, pass FROM `$table` ORDER BY id" )->fetchAll();
foreach( $users as &$u ) {
	$u['legacy'] = (bool) preg_match( '/^[0-9a-f]{32}$/', $u['pass'] );
}
unset($u);

$allMigrated = !array_filter( $users, fn($u) => $u['legacy'] );
?><!DOCTYPE html>
<html>
<head>
	<title>Password Migration — Asaph</title>
	<link rel="stylesheet" type="text/css" href="templates/admin.css" />
	<style>
		.legacy { color: #c00; font-weight: bold; }
		.ok     { color: #060; }
		table   { border-collapse: collapse; margin: 1em 0; }
		td, th  { padding: .4em .8em; border: 1px solid #ccc; }
		th      { background: #eee; }
	</style>
</head>
<body>
<div class="install">
	<h1>Password Migration</h1>

	<?php if( $message ) { ?>
		<div class="warn" style="background:#dfd;border-color:#080;"><?php echo htmlspecialchars($message); ?></div>
	<?php } ?>

	<?php if( $allMigrated ) { ?>
		<p class="ok"><strong>All users have been migrated to bcrypt. Delete this file now.</strong></p>
	<?php } else { ?>
		<p>Users with a <span class="legacy">legacy md5 hash</span> must have their password reset below.
		   Users marked <span class="ok">OK</span> are already using bcrypt.</p>
	<?php } ?>

	<table>
		<tr><th>ID</th><th>Name</th><th>Hash status</th><th>Action</th></tr>
		<?php foreach( $users as $u ) { ?>
		<tr>
			<td><?php echo (int)$u['id']; ?></td>
			<td><?php echo htmlspecialchars($u['name']); ?></td>
			<td>
				<?php if( $u['legacy'] ) { ?>
					<span class="legacy">Legacy md5</span>
				<?php } else { ?>
					<span class="ok">bcrypt (OK)</span>
				<?php } ?>
			</td>
			<td>
				<form method="post" style="display:flex;gap:.5em;align-items:center;">
					<input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>"/>
					<input type="password" name="pass" placeholder="New password" required/>
					<input type="submit" name="reset" value="Set password" class="button"/>
				</form>
			</td>
		</tr>
		<?php } ?>
	</table>

	<p><strong>Delete <code>admin/migrate_passwords.php</code> from your server when finished.</strong></p>
</div>
</body>
</html>
