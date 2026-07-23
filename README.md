# WP Electronic Parts

WordPress plugin for managing electronic parts with a hierarchical category tree, typed category properties, and an admin **Catalog** split-view.

**Author:** Stefan Fambach  
**Version:** 0.3.0  
**License:** GPLv2 or later  
**Requires:** WordPress 6.4+, PHP 8.0+

## What's included

- Custom post type **Electronic Part** (`electronic_part`)
- Hierarchical taxonomy **Part Categories** (`part_category`)
- Part **Name** → auto-derived post title
- **Category properties** (typed schema on terms) and values on parts
- Admin **Catalog** split-view: category tree, category editor, parts list, part editor (AJAX)
- Classic term/part metaboxes remain as a parallel path
- REST API enabled for CPT and taxonomy

## Installation (local)

1. Copy or symlink this folder into `wp-content/plugins/wp-electronic-parts/`
2. Activate **WP Electronic Parts** under Plugins
3. Open **Catalog** in the admin menu

After activation, visit **Settings → Permalinks** once and click **Save** so rewrite rules are flushed.

## Try it out

1. In **Catalog**, build a category tree (e.g. Passive → Resistors / Capacitors)
2. Define parameters on a category (including `measure` with a units branch)
3. Add parts from the tree or category pane and fill property values
4. Optionally check classic edit screens still work

## Plans (source of truth)

| Plan | Status |
|------|--------|
| [`docs/plans/category-properties-mvp.md`](docs/plans/category-properties-mvp.md) | implemented (~0.2.0+) |
| [`docs/plans/category-tree-layout.md`](docs/plans/category-tree-layout.md) | implemented (0.3.0) |
| [`docs/plans/catalog-next-0.4.md`](docs/plans/catalog-next-0.4.md) | **planned** next slices |
| [`docs/plans/domain-vision-prototype.md`](docs/plans/domain-vision-prototype.md) | **discovery** (Prozesse, Calcs, Blöcke) |

## GitHub export

```bash
cd C:\devel\wordpress\wp-electronic-parts
git init
git add .
git commit -m "Initial playground: hierarchical part categories"
gh repo create wp-electronic-parts --public --source=. --push
```

Adjust visibility and remote name as needed.
