# CLAUDE.md — Presellia Pricing Studio

> Instructions permanentes pour toutes les sessions futures sur ce projet.

---

## Identité du projet

| Champ | Valeur |
|---|---|
| Nom | Presellia Pricing Studio |
| Slug / repo | `presellia-pricing-studio` |
| Fichier principal | `presellia-pricing-studio.php` |
| Préfixe constantes/classes | `PPS_` |
| Préfixe options WP | `pps_` |
| Préfixe metas propres | `_pps_*` |
| Plugin connexe | Presellia Partner Bridge (PPB) — dépendance souple |

---

## Langue et commits

- Langue des commits : **français**
- Format : `type: description` (ex: `feat: ajout export CSV`)
- Types : `feat`, `fix`, `chore`, `docs`, `refactor`
- **Ne jamais committer sans demande explicite**

---

## Versioning — séquence obligatoire

Avant chaque release, mettre à jour **deux endroits** dans `presellia-pricing-studio.php` :
1. En-tête : `* Version: X.X.X`
2. Constante : `define( 'PPS_VERSION', 'X.X.X' );`

Puis mettre à jour `CHANGELOG.md`.

---

## Règles de base

- PHP 8.0+ requis
- Pas de Composer / vendor
- Sécurité : nonces sur tous les AJAX, `current_user_can()`, `sanitize_*`, `esc_*`
- HPOS : ne pas accéder à `wp_postmeta` pour les données de commande WC

---

## Metas utilisées

| Meta | Propriétaire | Scope | Rôle |
|---|---|---|---|
| `_wc_cog_cost` | SkyVerge COG (PPS lit+écrit) | produit/variation | Coût CFA |
| `_pps_cost_usd` | PPS | produit/variation | Coût USD |
| `_pps_cost_cfa_is_manual` | PPS | produit/variation | Flag override CFA manuel |
| `_pps_other_fees_pct` | PPS | produit/variation | Frais % (plateforme, etc.) |
| `_ppb_partner_price` | PPB (PPS lit+écrit via PPB_Pricing si actif) | produit/variation | Prix revendeur |
| `_ppb_partner_tiers` | PPB (PPS lit+écrit via PPB_Pricing si actif) | produit/variation | Paliers revendeur (JSON) |
| `pps_usd_cfa_rate` | PPS (option WP) | global | Taux de conversion USD→CFA |

---

## Dépendances

- **WooCommerce** : obligatoire (vérifié au `plugins_loaded`)
- **SkyVerge Cost of Goods** : souple — si absent, `_wc_cog_cost` reste utilisable directement
- **Presellia Partner Bridge** : souple — détecté via `class_exists('PPB_Pricing')`. Si présent, délègue `set_partner_price()` et `set_partner_tiers()` à PPB pour rester en sync avec le portail partenaire

---

## Architecture

```
presellia-pricing-studio.php         → bootstrap : constantes, activation hooks, plugins_loaded
includes/
  class-pps-plugin.php               → loader singleton : require_once + instanciation modules
  class-pps-activator.php            → option pps_usd_cfa_rate par défaut (655)
  class-pps-data.php                 → data layer : lecture/écriture toutes les metas, get_catalog()
  class-pps-analytics.php            → stats commandes/CA via wc_order_product_lookup
admin/
  class-pps-admin.php                → menu WC, enqueue scripts, render helpers, AJAX handlers
  page-pps-studio.php                → template HTML — inclus depuis PPS_Admin::render_studio()
assets/
  css/pps-studio.css                 → styles page Pricing Studio
  js/pps-studio.js                   → calculs live, USD/CFA bidirectionnel, save, analytics AJAX
```

---

## Logique JS clé

### Bidirectionnel USD ↔ CFA
- Éditer USD → CFA = USD × taux (auto, si pas d'override)
- Éditer CFA manuellement → USD = CFA / taux (auto) + flag `data-manual="1"` + badge ✎
- Modifier le taux global → recalcule CFA pour toutes les lignes **sans** override manuel
- Bouton ↺ efface le flag et recalcule depuis USD

### Calculs de marge
```
coût_total    = coût_cfa × (1 + frais_pct / 100)
marge_cli %   = (prix_client  - coût_total) / prix_client  × 100
profit_cli    = prix_client  - coût_total
marge_rev %   = (prix_rev     - coût_total) / prix_rev     × 100
profit_rev    = prix_rev     - coût_total
```
`prix_client` = `sale_price` si défini, sinon `regular_price`

### Code couleur marges
- Vert  : ≥ 60%
- Orange : 40–59%
- Rouge  : < 40%

---

## AJAX

| Action WP | Handler | Description |
|---|---|---|
| `pps_bulk_save` | `PPS_Admin::ajax_bulk_save()` | Sauvegarde tous les produits + taux global |
| `pps_load_analytics` | `PPS_Admin::ajax_load_analytics()` | Stats commandes/CA depuis `wc_order_product_lookup` |

Nonce : `pps_nonce`

---

## Analytics

Utilise la table `{prefix}wc_order_product_lookup` (WC Analytics). Si la table n'existe pas, retourne un tableau vide (pas d'erreur, cellules affichent `0`).

Chargement différé : la page s'affiche sans analytics, puis un seul AJAX batch charge les stats pour tous les produits visibles après le rendu.

---

## Pièges à éviter

- `_wc_cog_cost` est partagé avec SkyVerge : écrire uniquement via `PPS_Data::save_product_data()` pour rester cohérent
- `_ppb_partner_tiers` est du JSON : toujours décoder avant usage, encoder avant écriture
- `pps_usd_cfa_rate` est sauvegardé à chaque bulk save — inclure le champ `rate` dans le POST
- Analytics : ne jamais accéder à `wp_postmeta` pour des données de commandes (HPOS)
- Variations : passer `$parent_id` à `render_product_row()` pour que le JS puisse toggler correctement via `data-parent`
