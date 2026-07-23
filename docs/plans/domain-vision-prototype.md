---
name: Domain Vision from Prototype
overview: "Prozesse, Knoten-Calcs, Blöcke; gesamte Bestandslogik (Wareneingang, Verbrauch, BOM-Check) erst ab Version 2.0."
status: discovery
version: "vision → v2.0+"
baseline: "0.3.0 + Prototyp-Spiel"
target_stock: "2.0.0+"
related_plans:
  - docs/plans/category-tree-layout.md
  - docs/plans/category-properties-mvp.md
  - docs/plans/catalog-next-0.4.md
todos:
  - id: v1-no-stock
    content: "v1.x / 0.4: kein Bestand, kein Verbrauch, kein Wareneingang — nur Catalog/Schema/Blöcke-Vorbereitung"
    status: pending
  - id: processes-v2
    content: "v2+: Prozesse am Baum (Wareneingang, Verbrauch aus BOM×Menge)"
    status: pending
  - id: stock-ledger-v2
    content: "v2+: Bestandslogik (Ledger/Saldo) als eigenes Modul"
    status: pending
  - id: node-calcs
    content: "Knoten-Berechnungen (count; BOM-Tabelle) — Calcs ggf. schon vor Stock, Buchen erst v2"
    status: pending
  - id: blocks
    content: "WP-Blöcke (Listen, Tabellen, Dropdowns) für Knoten"
    status: pending
  - id: sibling-order
    content: "Kind-Reihenfolge: manuelle sibling_order am Kind + optional Sort-Modus am Vater; nie Term-IDs tauschen"
    status: pending
---

# Domain-Vision (aus dem Prototypen)

Persistiert aus dem Prototyp-Spiel. **Kein Implementierungs-Slice für Bestand jetzt.**  
Nahziel: Catalog-UX in [`catalog-next-0.4.md`](catalog-next-0.4.md) → **v1.x ohne Bestand**.

## Versionsgrenze (Entscheidung)

| Bereich | Version |
|---------|---------|
| Catalog Split-View, Properties, Listen-UX, Media, Integrität | **≤ 1.x** (aktuell 0.3 → 0.4 …) |
| Generische Tree-/Knoten-Bausteine, ggf. erste Blöcke ohne Stock | **1.x** möglich |
| **Kind-Reihenfolge** (manuell umschichten, DnD schreibt Order) | **1.x** (vor/mit DnD; Datenmodell früh) |
| **Gesamte Bestandslogik** (Saldo, Wareneingang, Verbrauch buchen, BOM-Verfügbarkeit) | **ab 2.0** |
| Prozess-Engine am Baum, die Bestandsbewegungen auslöst | **ab 2.0** |

> Entscheidung: Bestand nicht „nebenbei“ in 0.x/1.x einstreuen — eigenes Major mit klarem Modell (Ledger, Buchungen, Historie).

```mermaid
flowchart LR
  v03["0.3 Catalog"] --> v04["0.4 UX"]
  v04 --> v1["1.x Catalog + optional Blöcke/Calcs read-only"]
  v1 --> v2["2.0+ Bestand + Prozesse"]
```

---

## Kernerkenntnisse

### 1. Prozesse am Baum → Aktionen auf Objekten (**v2+**)

Prozesse im Baum lösen **Aktionen auf Domänenobjekten** aus — nicht nur Meta anzeigen.

| Prozess | Ablauf |
|---------|--------|
| **Wareneingang** | Kontext-Knoten → erhöht **Bestand** für betroffene Parts |
| **BOM-Check** | liest Bestand → „alles da?“ für eine Stückliste |
| **Verbrauch buchen** | BOM × **Anzahl Platinen** → entnimmt Bestand je Position |

#### Szenario: BOM für 10 Platinen, Verbrauch buchen

Bisher vor allem Wareneingang + Check angedacht. Neu aus dem Prototyp:

1. Stückliste (BOM) am Knoten / Gerät definiert (Referenzen × Menge **pro Platine**)
2. Nutzer wählt **Menge Fertigung** = 10 Platinen
3. System rechnet Bedarf: `Soll[part] = BOM_qty[part] × 10`
4. Optional vorher Check: `Bestand[part] >= Soll[part]` für alle Positionen
5. **Verbrauch buchen**: Bestand verringern, Buchungssatz mit Bezug (BOM, Menge 10, Zeit, User)

```mermaid
flowchart TD
  Bom["BOM am Knoten\nPart → qty/Platine"] --> Scale["× 10 Platinen"]
  Scale --> Need["Soll-Mengen"]
  Stock["Bestand v2"] --> Check["Verfügbarkeit?"]
  Need --> Check
  Check -->|ok| Book["Verbrauch buchen"]
  Book --> Ledger["Bestandsbewegung −Soll"]
```

**Implikation:** Bestand braucht Bewegungen (Eingang/Ausgang), nicht nur einen Zähler ohne Historie — deshalb Major **2.0**.

Offen (erst in v2-Plan klären):

- Ledger-Tabelle vs. Post-Meta + Log-CPT
- Teilbuchungen / Storno
- Reservierung vs. sofortiger Verbrauch
- Wo lebt der Prozess? (Term-Meta, CPT, Aktions-UI am Knoten)

### 2. Knoten-Berechnungen

Knoten können **Berechnungen** tragen — abgeleitete Werte aus Teilbaum oder Referenzen.

| Idee | Beispiel | Frühestens |
|------|----------|------------|
| Aggregation | Anzahl Kinder / Parts | 1.x möglich (read-only) |
| BOM-View | Referenzen + Menge → **Tabelle** | 1.x View ok; **Buchen erst 2.0** |

```mermaid
flowchart TD
  Node["Knoten"] --> Calc["Berechnung"]
  Calc --> Count["count children / parts"]
  Calc --> Bom["BOM: Referenzen × Menge"]
  Bom --> Table["Tabelle"]
  Bom -.->|"v2+"| Consume["× N Platinen → Verbrauch"]
```

**Abgrenzung Properties-MVP:** Properties = Schema + gespeicherte Werte. Calcs = Ableitung. BOM-**Anzeige** kann vor Bestand kommen; BOM-**Buchung** nicht.

### 3. WordPress-Blöcke für Knoten

| Block-Idee | Rolle | Stock? |
|------------|--------|--------|
| Liste | Kinder / Parts eines Knotens | nein |
| Tabelle | BOM-/Calc-Ergebnis | Anzeige 1.x; Bestandsspalten 2.0 |
| Dropdown | Knoten-Auswahl | nein |

Blöcke teilen das Baummodell mit dem Catalog — kein zweites Datenmodell.

### 4. Reihenfolge der Kinder (nicht über IDs)

**Problem:** Sortierung „nach Term-ID“ und Umschichten durch **ID-Tausch** ist falsch — IDs sind stabile Identitäten (Referenzen in BOM, Properties, Links).

**Modell (Entscheidung):**

| Wo | Was | Bedeutung |
|----|-----|-----------|
| **Kind** | Term-Meta `wpep_sibling_order` (int) | Position unter dem aktuellen Parent (0, 10, 20… oder dicht 1…n) |
| **Vater** (optional) | Term-Meta `wpep_children_sort` | Modus: `manual` \| `name` \| `id` — *wie* die Kinder gelesen werden |

```mermaid
flowchart TD
  Parent["Vaterknoten\nwpep_children_sort"] --> Mode{"Modus"}
  Mode -->|manual| Ord["Kinder nach wpep_sibling_order"]
  Mode -->|name| Name["Kinder nach Name"]
  Mode -->|id| Id["Kinder nach term_id\nnur Fallback / Altbestand"]
  Reorder["Umschichten / DnD"] --> Ord
  Reorder -.->|"nie"| Ids["term_id tauschen"]
```

- **Umschichten** = `wpep_sibling_order` der Geschwister neu setzen (ggf. normalisieren), **keine** `term_id`-Änderung.
- Der Vater steuert nur den **Sort-Modus**, nicht die Liste der IDs als einzige Wahrheitsquelle (vermeidet Doppelpflege beim Parent-Wechsel). Beim Wechsel des Parents: Order am Kind neu ans Ende der neuen Geschwisterliste hängen.
- Heute im Catalog: `orderby => name` — bis Order existiert, bleibt Name; danach bei `manual` Meta-Order.

**UI (später):** Hoch/Runter im Category-Editor oder DnD im Tree schreibt nur Order-Meta. API z. B. `wpep_reorder_siblings { parent_id, ordered_term_ids[] }`.

**Passt zu BOM/Calcs:** Tabellenzeilen folgen derselben Kind-Reihenfolge wie der Baum.

#### Sort-API (klar) — Strategy + Registry

Kein „Objekt pro Sortiervorgang“ nötig. **Stateless Strategy** + **Registry** (kennt alle Implementierungen). Klassische Factory (neu erzeugen) lohnt hier nicht.

```mermaid
flowchart LR
  Caller["Tree / BOM / Block"] --> Reg["Sibling_Sorter_Registry"]
  Reg --> S1["Sort_By_Name"]
  Reg --> S2["Sort_By_Order"]
  Reg --> S3["… später Sort_By_*"]
  Caller -->|"sort(id, list, direction)"| Out["sortierte Liste"]
```

**Interface (Instanz-Methoden, zustandslos):**

```php
interface Sibling_Sorter {
	public function id(): string;           // z.B. 'name' | 'order'
	public function label(): string;        // UI

	/**
	 * @param list<\WP_Term> $terms
	 * @param 'asc'|'desc'   $direction
	 * @return list<\WP_Term>
	 */
	public function sort( array $terms, string $direction = 'asc' ): array;
}
```

Optional PHP 8.1+: `enum Sort_Direction: string { case Asc = 'asc'; case Desc = 'desc'; }`

**Implementierungen (Beispiele):**

| `id()` | Verhalten |
|--------|-----------|
| `name` | nach `$term->name` (natürlich/`strcasecmp`), dann stabile Tie-Break z. B. `term_id` |
| `order` | nach Meta `wpep_sibling_order`, Tie-Break Name oder `term_id` |

Beide: bei `desc` Ergebnis umdrehen bzw. Vergleich invertieren — **eine** Implementierung pro Kriterium, Direction als Parameter.

**Registry (hält alle Strategien):**

```php
final class Sibling_Sorter_Registry {
	/** @var array<string, Sibling_Sorter> */
	private array $sorters = [];

	public function register( Sibling_Sorter $sorter ): void {
		$this->sorters[ $sorter->id() ] = $sorter;
	}

	public function has( string $id ): bool { /* … */ }

	public function get( string $id ): Sibling_Sorter { /* Unknown → Fallback name */ }

	/** @return array<string, string> id => label */
	public function choices(): array { /* für Parent-Select */ }

	/**
	 * @param list<\WP_Term> $terms
	 * @return list<\WP_Term>
	 */
	public function sort( string $id, array $terms, string $direction = 'asc' ): array {
		return $this->get( $id )->sort( $terms, $direction );
	}
}
```

Vater-Meta `wpep_children_sort` speichert die **Sorter-`id()`** (+ optional Direction separat, z. B. `wpep_children_sort_dir`, Default `asc`).

#### Dynamisch laden — wie „Factory kennt neue Impl“?

PHP hat **keine** eingebaute „finde alle Klassen die Interface X implementieren“. Optionen:

| Ansatz | Pros | Empfohlen? |
|--------|------|------------|
| **Hook** `wpep_register_sibling_sorters` → Plugins rufen `$registry->register( new X )` | WP-idiomatisch, echt erweiterbar | **ja** |
| Explizite Liste im Bootstrap | einfach, klar | ja für Core-Sorter |
| Ordner scannen + Reflection | magisch, fragil, langsam | nein |
| Composer-Plugin / Attribute-Discovery | overkill für WP-Plugin | nein |

Core registriert `name` + `order` beim `plugins_loaded`/`init`; Dritte hängen sich an den Hook.

#### Statisch vs. Instanz?

| | Statische Methoden | Stateless-Instanz (Vorschlag) |
|--|--------------------|-------------------------------|
| PHP-Interface | Interfaces beschreiben **Instanzen**; `static` in Interfaces geht erst eingeschränkt / unüblich | passt naturgemäß |
| Registry | speichert `callable` / class-string | speichert `Sibling_Sorter`-Objekte |
| Tests / Mock | umständlicher | einfach |
| Zustand | keiner nötig | keiner — eine Instanz reicht für immer |

**Empfehlung:** zustandslose **Klassen-Instanzen** (einmal `new Sort_By_Name()`, registrieren). Kein State pro Sort-Aufruf. „Statisch“ ist in PHP möglich (`Sort_By_Name::sort( $list, $dir )`), aber dann eher Registry von Callables — weniger typsicher am Interface. In Java/C# analog: Strategy-Interface + Singleton/DI; static utility ohne Polymorphie.

Für `wp-taxonomy-tree` später: gleiches Interface generisch über `list<object>` + Accessor, hier zuerst an `\WP_Term` gebunden.

---

## Einordnung

| ≤ 1.x | ab 2.0 |
|-------|--------|
| Taxonomie, Properties, Catalog-UI | Prozesse mit Bestandsbewegung |
| optionale Calcs / BOM-Tabelle (read) | Wareneingang, Verbrauch × N, BOM-Check gegen Saldo |
| Blöcke Listen/Dropdown/Tabelle ohne Stock | Bestandsanzeige, Buchungs-UI |
| Kind-Reihenfolge (`sibling_order` + Sort-Modus am Vater) | — |

**Reihenfolge:**

1. **0.4 / 1.x** — Catalog-UX, Integrität; **sibling_order**-Modell + einfache Umordnung; optional Calcs/Blöcke ohne Stock  
2. **2.0 Planung** — Ledger-Modell, Buchungsarten (WE, Verbrauch), BOM×Menge  
3. **2.x** — UI-Prozesse am Baum, BOM-Check, Blöcke mit Bestandsspalten  

`wp-taxonomy-tree`: generische Tree/Knoten/Blöcke.  
`wp-electronic-parts`: Domäne Parts; **Bestandsmodul erst ab 2.0**.

## Explizit geparkt bis 2.0

- Gesamte Bestandslogik (Saldo, Bewegungen, Historie)  
- Wareneingang buchen  
- Verbrauch buchen (inkl. BOM × N Platinen)  
- Verfügbarkeits-Check gegen Bestand  
- Reservierungen / Storno (falls nötig)

## Offen (nicht blockierend für 0.4)

- SI/Einheiten  
- BOM als CPT vs. Calc am Knoten  
- Prozess-Engine vs. feste Aktions-Buttons
