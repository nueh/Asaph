Asaph version 2.0

A PHP image bookmarking and blogging platform. Install a browser
bookmarklet, click it on any page to capture an image and link, and it
gets stored and displayed on your blog.

Original author: Dominic Szablewski
Web: http://www.phoboslab.org/projects/asaph
Repository: https://github.com/nueh/Asaph



Requirements & Installation
-------------------------------------------------------------------------

Asaph requires PHP 8.0 or higher with the GD library and PDO installed.
cURL or allow_url_fopen must be enabled for image downloading.

Database: either SQLite (no server required, recommended for single-user
installs) or MySQL 5.7 or higher.

To install:

1. Upload all files to your web server.

2. Edit lib/asaph_config.class.php and set at minimum:
   - $domain and $absolutePath (your server hostname and path)
   - $db['driver']: 'sqlite' (default) or 'mysql'
   - For MySQL: $db['host'], $db['database'], $db['user'], $db['password']
   - For SQLite: $db['path'] (default: data/asaph.db)

3. Make the data/ directory writable by PHP. This is where Asaph stores
   all images, thumbnails, and (if using SQLite) the database file.

4. Point your browser to admin/install.php and follow the instructions.
   Delete install.php from your server after a successful install.

5. Optionally switch from the default whiteout theme to the stickney
   theme by replacing both occurrences of "whiteout" with "stickney" in
   the $templates setting.



Usage / Posting
-------------------------------------------------------------------------

The only way to post new entries is through a bookmarklet. After logging
in to your admin menu, drag the ASAPH link from the left sidebar to your
bookmarks bar.

Navigate to any page and click the bookmark. A small bar will appear and
all images on the page will get a dashed blue border. Click any image to
post it, or click "Post this Site" to post the page as a link.



Migrating from an existing MySQL installation
-------------------------------------------------------------------------

If you are upgrading from an older MySQL-based Asaph installation:

1. Set driver = 'sqlite' in lib/asaph_config.class.php (or keep 'mysql'
   if you want to stay on MySQL and just upgrade PHP compatibility).

2. Run admin/install.php to create the new database tables.

3. If migrating to SQLite: visit admin/migrate_from_mysql.php and enter
   your old MySQL credentials. Posts and users are copied across, with
   duplicate detection.

4. If any users have old-style passwords (stored as md5 hashes), run
   admin/migrate_passwords.php to reset them to bcrypt.

5. Delete all migration scripts from the server when done.



FAQ
-------------------------------------------------------------------------

Q: My bookmarklet is not working.
A: The most common cause is an incorrect $domain or $absolutePath setting
   in lib/asaph_config.class.php. The installer (admin/install.php) shows
   the correct values for your server in the "Asaph Config" section.
   Another cause can be an ad-block or browser extension that blocks
   iframes.

Q: When posting images I repeatedly get "Couldn't create a thumbnail!"
A: Asaph may be detecting the wrong image URL (e.g. a thumbnail linking
   to an interstitial). Enter the image URL manually into the "Image"
   field in the post form.



Changelog
-------------------------------------------------------------------------

Version 2.0
  - PHP 8.0+ required
  - SQLite support added (now the default driver); MySQL still supported
  - Database layer rewritten to use PDO (replaces mysql_*/mysqli_*)
  - Passwords now stored with bcrypt (password_hash/password_verify)
  - Login tokens generated with random_bytes() instead of md5/uniqid
  - Cookies set with httponly and samesite=Lax flags
  - Removed magic_quotes_gpc handling (removed in PHP 8.0)
  - Driver-agnostic SQL: removed UNIX_TIMESTAMP(), SQL_CALC_FOUND_ROWS,
    and MySQL-only INSERT INTO ... SET syntax
  - admin/install.php: driver-aware, updated system checks
  - admin/migrate_from_mysql.php: migrate live MySQL data to SQLite
  - admin/migrate_passwords.php: upgrade legacy md5 passwords to bcrypt

Version 1.1
  - PHP 7 compatibility (mysqli_* replacing mysql_*)

Version 1.0
  - Added more comments to source files to allow easier modification
  - New $title config variable used in templates
  - Various bugfixes in the RSS template
  - Fixed bug where changing the post date would not create a new
    directory in data/
  - Fixed bug where deleting a post would not delete the image from disk
  - Fixed bug where the user was redirected to a malformed URL on
    success (xhrLocation in remote-success.html.php)
  - New template theme "stickney"

Beta 2
  - Usage of cURL or url fopen wrappers, based on what is available
  - The RSS feed now displays images properly
  - Fixed bug where post titles were filtered through htmlspecialchars
    twice when editing a post in the admin menu



License
-------------------------------------------------------------------------

Copyright (C) 2008  Dominic Szablewski

License: http://www.gnu.org/copyleft/gpl.html

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
