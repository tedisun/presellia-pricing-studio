# Changelog — Presellia Pricing Studio

## [1.2.0] — 2026-04-19

### Prix client éditables
- Les colonnes "Prix régulier" et "Promo" sont désormais des champs éditables directement dans le tableau
- La modification d'un prix recalcule immédiatement les marges en temps réel
- La sauvegarde (par ligne ou bulk) appelle `$product->set_regular_price()` / `set_sale_price()` + `$product->save()` pour rester en sync WooCommerce

## [1.1.0] — 2026-04-18

### Frais de paiement globaux
- Les frais % (ex : CinetPay ~2,5%) sont désormais une option globale (`pps_other_fees_pct`) configurée dans la barre de contrôles, au lieu d'un champ par produit
- La colonne "Frais %" disparaît du tableau ; les calculs de marge utilisent l'entrée globale en temps réel
- Sauvegarde des frais via AJAX bulk save (nouveau param `fees_pct`)

## [1.0.0] — 2026-04-18

### Initial release
- Page admin "⚡ Pricing Studio" sous WooCommerce
- Coûts sourcing USD + CFA (bidirectionnel avec taux global configurable)
- Lecture/écriture `_wc_cog_cost` en sync avec SkyVerge Cost of Goods
- Frais autres % (plateforme, transaction, etc.)
- Calcul live en JS : coût total, marge client %, profit client CFA, marge revendeur %, profit revendeur CFA
- Code couleur : vert >60%, orange 40–60%, rouge <40%
- Prix client (régulier + promo) depuis WooCommerce, lecture seule
- Prix revendeur + paliers dégressifs (dépendance souple PPB — lit/écrit `_ppb_partner_price` et `_ppb_partner_tiers`)
- Analytics WC (commandes semaine/mois, CA mois) via `wc_order_product_lookup` — chargement AJAX différé
- Filtre par catégorie et recherche par nom/SKU
- Sauvegarde globale (bulk save AJAX) + sauvegarde par ligne
- Statut produit (publié/brouillon/privé) et statut stock affiché
- Support produits simples et variables (variations expandables)
