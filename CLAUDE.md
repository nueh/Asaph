# CLAUDE.md — Asaph Codebase Guide

## Project Overview

**Asaph** is a PHP-based image bookmarking/blogging platform. Users install a browser bookmarklet, then click it on any webpage to capture an image and a link, which gets stored and displayed on the blog. It has no modern build system — it is a classic PHP application deployed directly to a web server.

- **Language**: PHP 5.0+
- **Database**: MySQL 4.0+
- **Frontend**: Vanilla HTML/CSS/JavaScript, no framework
- **License**: GNU General Public License v3

---

## Directory Structure

```
Asaph/
├── index.php                   # Frontend blog entry point
├── .htaccess                   # Apache mod_rewrite URL rules
├── readme.txt                  # End-user install/usage documentation
├── admin/                      # Admin interface
│   ├── index.php               # Admin dashboard (login, manage posts/users)
│   ├── install.php             # One-time database setup script
│   ├── post.php                # Bookmarklet POST handler
│   ├── post.js.php             # Bookmarklet JavaScript loader (served as JS)
│   └── templates/              # Admin UI templates
│       ├── head.html.php
│       ├── foot.html.php
│       ├── login.html.php
│       ├── posts.html.php
│       ├── edit-post.html.php
│       ├── users.html.php
│       ├── add-user.html.php
│       ├── edit-user.html.php
│       ├── remote-login.html.php
│       ├── remote-post.html.php
│       ├── remote-success.html.php
│       ├── admin.css
│       ├── post.css
│       └── calendar.js
├── lib/                        # Core PHP classes
│   ├── asaph_config.class.php  # All configuration (edit this for setup)
│   ├── db.class.php            # MySQL database abstraction layer
│   ├── asaph.class.php         # Post retrieval (frontend)
│   ├── asaph_admin.class.php   # Auth, user/post management
│   └── asaph_post.class.php    # Image download, thumbnail, post creation
└── templates/                  # Frontend themes
    ├── rss.xml.php             # RSS feed
    ├── whiteout/               # Minimalist white theme
    │   ├── posts.html.php
    │   ├── about.html.php
    │   ├── whiteout.css
    │   └── whitebox.js         # Lightbox JS
    └── stickney/               # Alternative dark theme
        ├── posts.html.php
        ├── about.html.php
        ├── stickney.css
        └── whitebox.js
```

---

## Class Architecture

Class inheritance chain (bottom depends on top):

```
DB
 └── (used by) Asaph
                └── Asaph_Admin (extends Asaph)
                     └── Asaph_Post (extends Asaph_Admin)
```

| Class | File | Responsibility |
|---|---|---|
| `DB` | `lib/db.class.php` | MySQL connection, query execution, custom prepared statements |
| `Asaph` | `lib/asaph.class.php` | Retrieve/format posts for frontend display |
| `Asaph_Admin` | `lib/asaph_admin.class.php` | Session auth, CRUD for posts/users |
| `Asaph_Post` | `lib/asaph_post.class.php` | Download remote image, generate thumbnail, insert post |
| `Asaph_Config` | `lib/asaph_config.class.php` | Static configuration properties |

---

## Configuration

All settings live in `lib/asaph_config.class.php` as static class properties. Edit this file to configure the application — there is no `.env` file or environment variable system.

Key settings:

| Property | Description |
|---|---|
| `Asaph_Config::$title` | Blog title |
| `Asaph_Config::$domain` | Base URL (e.g. `http://example.com`) |
| `Asaph_Config::$absolutePath` | Filesystem path to root |
| `Asaph_Config::$templates` | Active theme name (`whiteout` or `stickney`) |
| `Asaph_Config::$db` | Array: `host`, `database`, `user`, `password`, `prefix` |
| `Asaph_Config::$images` | Array: image path, thumbnail dimensions, JPEG quality |

---

## Request Flow

### Frontend (public blog)
1. `index.php` — loads config, instantiates `Asaph`, queries posts
2. Renders via `templates/{theme}/posts.html.php` or `about.html.php`

### Bookmarklet posting
1. Browser loads `/admin/post.js.php` (served as JavaScript)
2. User selects an image on any page → form POST to `/admin/post.php`
3. `Asaph_Post` authenticates user, downloads image via cURL, generates thumbnail via GD, inserts record into DB

### Admin panel
1. `/admin/index.php` handles login/session, then dispatches to sub-actions
2. Uses templates in `admin/templates/` for all admin UI
3. `Asaph_Admin` methods handle CRUD operations

---

## Database

- Custom prepared-statement system (not PDO). Placeholders use `:1`, `:2`, etc.
- Database initialized by running `/admin/install.php` once; delete the file after use.
- Table prefix configured via `Asaph_Config::$db['prefix']`.

Example query pattern in `db.class.php`:
```php
$this->db->query('SELECT * FROM posts WHERE id = :1', array($id));
```

---

## URL Routing

Apache `mod_rewrite` (`.htaccess`) rewrites clean URLs to `index.php` with query params. There is no PHP router — all URL dispatching happens through query string parameters checked directly in `index.php` and `admin/index.php`.

Requires: `mod_rewrite` enabled, `AllowOverride All` in Apache config.

---

## Themes

Two themes are included: **whiteout** (minimalist light) and **stickney** (alternative). Select the active theme via `Asaph_Config::$templates`.

To create a new theme:
1. Copy an existing theme directory under `templates/`
2. Implement `posts.html.php` and `about.html.php`
3. Set `Asaph_Config::$templates` to the new directory name

---

## Coding Conventions

- **Class names**: `PascalCase` with underscores for namespace grouping (e.g., `Asaph_Admin`)
- **Methods**: `camelCase` (e.g., `getPosts()`, `checkLogin()`)
- **Static config access**: `Asaph_Config::$property` — never instantiate config
- **Error suppression**: Legacy code uses `@` operator (e.g., `@fopen()`); preserve this pattern in existing code but avoid adding new uses
- **Templates**: Plain PHP mixed with HTML — no templating engine. Keep logic minimal in templates
- **No namespaces**: This predates PHP namespaces; do not add them
- **XHTML 1.0 Strict**: Frontend templates use XHTML DOCTYPE; maintain valid markup

---

## No Build System / No Tests

- No Composer, npm, Makefile, or any build pipeline
- No automated tests (unit or integration)
- Deployment: copy files to web server, configure `asaph_config.class.php`, run `install.php`
- Manual testing is the only available method

---

## Installation (for reference)

1. Copy files to web root
2. Edit `lib/asaph_config.class.php` with correct DB credentials, domain, and path
3. Navigate to `/admin/install.php` to create database tables
4. Delete `admin/install.php` after successful install
5. Log in at `/admin/`

**Requirements**: PHP 5.0+, MySQL 4.0+, GD library, cURL (or `allow_url_fopen = On`)

---

## Git

- Main branch: `master`
- Remote: `origin`
- Feature branches follow `claude/<description>` naming convention

When making changes:
- Commit with clear, descriptive messages
- Push to the designated feature branch; do not push directly to `master` without review
