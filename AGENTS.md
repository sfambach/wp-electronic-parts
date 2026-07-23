# WP Electronic Parts

WordPress plugin (namespace `WPEP`) providing a hierarchical category tree and a
custom post type for electronic parts. The core UI is the admin **Catalog** page
(split view: category tree + editor), backed by vanilla JS talking to
`admin-ajax.php`. Entry point: `wp-electronic-parts.php`; PHP classes in
`includes/`; assets in `assets/`. There is no build step, no package manager, and
no `composer.json`/`package.json` — PHP/JS/CSS run as-is.

## Cursor Cloud specific instructions

The plugin has no standalone runtime; it must run inside a WordPress install. A
full host (PHP 8.3, MariaDB, WordPress) is already provisioned in the VM snapshot.
The repo at `/workspace` is symlinked into the WordPress install as the plugin
directory, so edits to the repo are picked up live (no rebuild).

Key locations and credentials:
- WordPress install: `/home/ubuntu/wordpress`
- Plugin symlink: `/home/ubuntu/wordpress/wp-content/plugins/wp-electronic-parts` -> `/workspace`
- Site URL: `http://localhost:8080` — wp-admin: `http://localhost:8080/wp-admin` (user `admin`, pass `admin`)
- Catalog page: `http://localhost:8080/wp-admin/edit.php?post_type=electronic_part&page=wpep-category-tree`

Starting services (NOT in the update script — start them manually per session):
- MariaDB is not managed by systemd here. Start it once per session with:
  `sudo mkdir -p /var/run/mysqld && sudo chown mysql:mysql /var/run/mysqld && sudo mysqld_safe &`
  then confirm with `sudo mysqladmin ping`. DB `wordpress`, user `wp`, pass `wp`.
- Dev server: from `/home/ubuntu/wordpress` run `php -S 0.0.0.0:8080` (a tmux
  session named `wp-dev-server` is used for this). `wp server` also works.

Gotchas:
- `wp-cli` is installed globally as `wp`; run it from `/home/ubuntu/wordpress` and
  pass `--allow-root`.
- After (re)activating the plugin, flush rewrite rules once so `/parts/` and
  `/part-category/...` archives resolve: `wp rewrite flush --allow-root`.
- The Catalog editor saves via `admin-ajax.php` (not the REST API); a logged-in
  admin session/nonce is required, so exercise it through wp-admin, not anonymous curl.

Lint / test / build:
- No build step. No lint or automated test tooling is configured in the repo
  (no PHPCS/PHPUnit config). Verification is manual/end-to-end through wp-admin.
  If adding tests/linting, follow the WordPress Coding Standards and PHP 8.x OOP style.
