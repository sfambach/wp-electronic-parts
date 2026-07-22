---
name: Category Tree Layout
overview: "Split-View: Kategorie-Editor inkl. embedded Parts-Liste, Full Parts-Liste via Count, Part-Editor mit Zurück — shared PartsListPane, WPEP.events, AJAX."
status: implemented
version: "0.3.0"
todos:
  - id: shell-split
    content: "Admin-Shell: Tree links, rechts Mode-Container (empty|category|parts-list|part)"
    status: completed
  - id: event-bus
    content: JS Event-Bus inkl. category:*, parts-list:*, part:* Events
    status: completed
  - id: ajax-api
    content: AJAX category get/save + parts list by category + part get/save
    status: completed
  - id: tree-select
    content: "Baum: Name=category; Count=parts-list; +Kind/Delete rechts"
    status: completed
  - id: editor-category
    content: "Mode category: Settings + Parameter + embedded PartsListPane"
    status: completed
  - id: editor-parts-list
    content: Shared PartsListPane variants full + embedded
    status: completed
  - id: editor-part
    content: "Mode part: Editor inkl. Zurück nach from (list|category|toolbar)"
    status: completed
  - id: version-bump
    content: Assets verdrahten, Version bumpen
    status: completed
---

# Split-View: Kategorien, Parts-Liste, Bauteil-Editor

> Persistiert im Repo zum Weiterentwickeln. Slice umgesetzt in Plugin-Version **0.3.0** (`fed6f66`). Offene Ideen → Abschnitt **Nicht in diesem Slice** / neue Folge-Pläne.

## Ziel-UI / Navigation rechts

```
category (settings + params + embedded parts)
    │
    ├─ Count-Badge ──→ parts-list (full pane, gleiche Liste)
    │                      │
    └─ Part in Liste ──────┴──→ part editor
                                    │
                                 Zurück (je nach from)
```

```
+------------------+------------------------------------------+
| Tree             | mode=category                            |
| [▶] Name  (3)+🗑 | Settings                                 |
|                  | Parameters                               |
|                  | Parts (embedded list) ─────────────────┐ |
| Count ───────────┼→ mode=parts-list (same list, full)     │ |
|                  | Part-Klick → mode=part (+ Zurück)      │ |
+------------------+------------------------------------------+
```

| Mode | Einstieg | Rechts |
|------|----------|--------|
| `empty` | Start | Hinweis |
| `category` | Klick **Name** | Settings → Parameters → **Parts embedded** |
| `parts-list` | Klick **Count** | Nur Parts-Liste (full) |
| `part` | Klick Bauteil / New part | Part-Formular + optional Zurück |

## Shared `PartsListPane`

Eine Komponente [`assets/js/parts-list-pane.js`](../../assets/js/parts-list-pane.js):

- `variant: 'embedded'` — unter Parametern in Mode `category`
- `variant: 'full'` — eigene rechte Pane bei Count-Klick
- Gleiche Datenquelle: Event `parts-list:loaded` / AJAX `wpep_list_parts`
- Zeilen-Klick → `part:open` mit passendem `from`:
  - embedded → `from: 'category-embedded'`
  - full → `from: 'parts-list'`
- Button „Add part“ analog mit gleichem `from`

## State

```js
state = {
  mode: 'empty' | 'category' | 'parts-list' | 'part',
  categoryId: number | null,
  partId: number | null,
  partOpenedFrom: null | 'parts-list' | 'category-embedded' | 'toolbar',
  dirty: boolean
}
```

| `partOpenedFrom` | Zurück |
|------------------|--------|
| `parts-list` | Mode `parts-list` |
| `category-embedded` | Mode `category` (embedded Liste wieder da) |
| `toolbar` | Mode `category` wenn categoryId gesetzt, sonst `empty` |

Dirty-Wechsel: `confirm()`.

## Events (Auszug)

**Parts-Liste:** `parts-list:open` | `loading` | `loaded` | `failed`  
(Count → `parts-list:open`; Category-Load triggert intern auch List-Load für embedded)

**Part:**  
`part:create { categoryIds?, from }`  
`part:open { partId, categoryId, from }`  
`part:back` → Ziel laut `partOpenedFrom`  
`part:loaded` / `dirty` / `save-requested` / `saved` / `save-failed`

```mermaid
sequenceDiagram
  participant Cat as CategoryPane
  participant List as PartsListPane
  participant Bus as WPEP_events
  participant PartEd as PartEditor

  Cat->>List: mount embedded categoryId
  List->>Bus: part:open from category-embedded
  Bus->>PartEd: show with Back
  PartEd->>Bus: part:back
  Bus->>Cat: mode category again
```

## Linke Pane

```
[▶] Name .................... (3)  [+] [🗑]
```

- **Name** → `category:selected`
- **Count** → `parts-list:open` (full)
- **+** / **Delete** rechts
- Toolbar: Add root, New part (`from: 'toolbar'`)

## Rechte Pane — Mode `category` (Reihenfolge)

1. Category settings + Save  
2. Parameters + Add parameter  
3. Parts — `PartsListPane` embedded  

## Rechte Pane — Mode `parts-list`

- `PartsListPane` full  
- Add part mit `from: 'parts-list'`

## Rechte Pane — Mode `part`

- Zurück wenn `partOpenedFrom` gesetzt (nicht null)  
- Name, Kategorien, Parameterwerte  
- Save per AJAX; Tree-Counts refreshen  

## Server-API

- `wpep_get_category` / `wpep_save_category`  
- `wpep_list_parts` `{ category_id }` → `{ parts: [{ id, name, title }] }`  
- `wpep_get_part` / `wpep_save_part`  

## Module

- `wpep-events.js`, `category-tree-app.js`, `category-tree-pane.js`  
- `category-editor-pane.js`, `parts-list-pane.js`, `part-editor-pane.js`  

## Nicht in diesem Slice

- Pagination/Suche in der Parts-Liste  
- Drag-and-drop, Block-Editor  
- Ersetzen von „All Parts“ in WP-Admin  
