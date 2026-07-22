# WP Electronic Parts

WordPress plugin for managing electronic parts with a **hierarchical category tree**.

Playground version (`0.1.0`) — enough to explore the admin UI and decide what custom term properties you need later.

**Author:** Stefan Fambach  
**Version:** 0.1.0  
**License:** GPLv2 or later

## What's included

- Custom post type **Electronic Part** (`electronic_part`)
- Hierarchical taxonomy **Part Categories** (`part_category`)
- REST API enabled for both (Gutenberg-ready)
- Admin column showing categories on the parts list

## Installation (local)

1. Copy or symlink this folder into `wp-content/plugins/wp-electronic-parts/`
2. Activate **WP Electronic Parts** under Plugins
3. Open **Electronic Parts** in the admin menu

After activation, visit **Settings → Permalinks** once and click **Save** so rewrite rules are flushed.

## Try it out

1. Create a category tree, e.g.:
   - Passive Components
     - Resistors
       - SMD 0805
     - Capacitors
   - Semiconductors
     - Transistors
2. Add a few **Electronic Parts** and assign them to categories
3. Check the front end at `/parts/` and category archives at `/part-category/...`

## Planned next steps

- Custom properties on category nodes (term meta)
- Part-specific fields (MPN, footprint, value, datasheet URL, …)
- Blocks or templates for browsing the tree

## GitHub export

```bash
cd C:\devel\wordpress\wp-electronic-parts
git init
git add .
git commit -m "Initial playground: hierarchical part categories"
gh repo create wp-electronic-parts --public --source=. --push
```

Adjust visibility and remote name as needed.
