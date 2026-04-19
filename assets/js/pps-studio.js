/* Presellia Pricing Studio — logique UI */
'use strict';

/* ------------------------------------------------------------------ */
/* État partagé                                                         */
/* ------------------------------------------------------------------ */

const tiersMap = {}; // { productId: [{min, price}, ...] }

document.addEventListener( 'DOMContentLoaded', () => {
	if ( window.ppsStudioTiers ) {
		Object.assign( tiersMap, window.ppsStudioTiers );
	}

	initTiersEditors();
	recalcAll();
	bindEvents();
	loadAnalytics();
} );

/* ------------------------------------------------------------------ */
/* Calculs de marge                                                     */
/* ------------------------------------------------------------------ */

function recalcAll() {
	document.querySelectorAll( 'tr[data-id][data-has-vars="0"]' ).forEach( recalcRow );
}

function recalcRow( row ) {
	const costCfa      = parseFloat( row.querySelector( '.pps-cost-cfa' )?.value )      || 0;
	const feesPct      = parseFloat( document.getElementById( 'pps-global-fees' )?.value ) || ppsStudio.feesPct || 0;
	const partnerPrice = parseFloat( row.querySelector( '.pps-partner-price' )?.value ) || 0;
	const priceRegular = parseFloat( row.querySelector( '.pps-price-regular' )?.value ) || 0;
	const priceSale    = parseFloat( row.querySelector( '.pps-price-sale' )?.value )    || 0;
	const clientPrice  = priceSale > 0 ? priceSale : priceRegular;

	// Affiche le coût CFA de base (colonne de référence visuelle)
	const baseCoutEl = row.querySelector( '.pps-cost-total-val' );
	if ( baseCoutEl ) {
		baseCoutEl.textContent = costCfa > 0 ? formatCfa( costCfa ) : '—';
	}

	// Les frais de paiement (%) sont prélevés SUR LE PRIX DE VENTE par l'intégrateur.
	// Revenu net = prix × (1 − frais%)
	// Profit     = revenu_net − coût_cfa
	// Marge%     = profit / prix × 100

	// Marge client
	const marginClientCell = row.querySelector( '.pps-col-margin-client' );
	const marginClientEl   = row.querySelector( '.pps-margin-client-val' );
	const profitClientEl   = row.querySelector( '.pps-profit-client-val' );

	if ( clientPrice > 0 && costCfa > 0 ) {
		const netRev = clientPrice * ( 1 - feesPct / 100 );
		const profit = netRev - costCfa;
		const m      = profit / clientPrice * 100;
		if ( marginClientEl ) marginClientEl.textContent = formatPct( m );
		if ( profitClientEl ) profitClientEl.textContent = formatCfa( profit );
		applyMarginColor( marginClientCell, m );
	} else {
		if ( marginClientEl ) marginClientEl.textContent = '—';
		if ( profitClientEl ) profitClientEl.textContent = '—';
		applyMarginColor( marginClientCell, null );
	}

	// Marge revendeur
	const marginPartnerCell = row.querySelector( '.pps-col-margin-partner' );
	const marginPartnerEl   = row.querySelector( '.pps-margin-partner-val' );
	const profitPartnerEl   = row.querySelector( '.pps-profit-partner-val' );

	if ( partnerPrice > 0 && costCfa > 0 ) {
		const netRev = partnerPrice * ( 1 - feesPct / 100 );
		const profit = netRev - costCfa;
		const m      = profit / partnerPrice * 100;
		if ( marginPartnerEl ) marginPartnerEl.textContent = formatPct( m );
		if ( profitPartnerEl ) profitPartnerEl.textContent = formatCfa( profit );
		applyMarginColor( marginPartnerCell, m );
	} else {
		if ( marginPartnerEl ) marginPartnerEl.textContent = '—';
		if ( profitPartnerEl ) profitPartnerEl.textContent = '—';
		applyMarginColor( marginPartnerCell, null );
	}
}

function applyMarginColor( cell, margin ) {
	if ( ! cell ) return;
	cell.classList.remove( 'pps-margin-good', 'pps-margin-ok', 'pps-margin-bad' );
	if ( margin === null ) return;
	if ( margin >= 60 )      cell.classList.add( 'pps-margin-good' );
	else if ( margin >= 40 ) cell.classList.add( 'pps-margin-ok' );
	else                     cell.classList.add( 'pps-margin-bad' );
}

/* ------------------------------------------------------------------ */
/* USD ↔ CFA bidirectionnel                                             */
/* ------------------------------------------------------------------ */

function handleUsdChange( row, usdInput ) {
	const cfaInput = row.querySelector( '.pps-cost-cfa' );
	if ( ! cfaInput || cfaInput.dataset.manual === '1' ) return;
	const rate = getGlobalRate();
	cfaInput.value = Math.round( parseFloat( usdInput.value || 0 ) * rate ) || '';
	recalcRow( row );
}

function handleCfaChange( row, cfaInput ) {
	const usdInput = row.querySelector( '.pps-cost-usd' );
	const rate     = getGlobalRate();

	cfaInput.dataset.manual    = '1';
	cfaInput.dataset.wasManual = '1';

	const badge    = row.querySelector( '.pps-manual-badge' );
	const resetBtn = row.querySelector( '.pps-reset-manual' );
	if ( badge )    badge.style.display    = 'inline';
	if ( resetBtn ) resetBtn.style.display = 'inline';

	if ( usdInput && rate > 0 ) {
		usdInput.value = ( parseFloat( cfaInput.value || 0 ) / rate ).toFixed( 2 );
	}
	recalcRow( row );
}

function onGlobalRateChange() {
	const rate = getGlobalRate();
	document.querySelectorAll( 'tr[data-has-vars="0"]' ).forEach( row => {
		const cfaInput = row.querySelector( '.pps-cost-cfa' );
		const usdInput = row.querySelector( '.pps-cost-usd' );
		if ( ! cfaInput || cfaInput.dataset.manual === '1' ) return;
		if ( usdInput ) {
			cfaInput.value = Math.round( parseFloat( usdInput.value || 0 ) * rate ) || '';
		}
		recalcRow( row );
	} );
}

function getGlobalRate() {
	return parseFloat( document.getElementById( 'pps-global-rate' )?.value ) || ppsStudio.usdCfaRate;
}

/* ------------------------------------------------------------------ */
/* Éditeur de paliers                                                   */
/* ------------------------------------------------------------------ */

function initTiersEditors() {
	document.querySelectorAll( '.pps-tiers-row' ).forEach( tiersRow => {
		const id = parseInt( tiersRow.dataset.for, 10 );
		renderTiersEditor( id, tiersRow );
	} );
}

function renderTiersEditor( id, tiersRow ) {
	const tbody = tiersRow.querySelector( '.pps-tiers-body' );
	if ( ! tbody ) return;
	tbody.innerHTML = '';
	( tiersMap[ id ] || [] ).forEach( tier => {
		tbody.appendChild( buildTierRow( tier.min, tier.price ) );
	} );
}

function buildTierRow( min, price ) {
	const tr = document.createElement( 'tr' );
	tr.className = 'pps-tier-row';
	tr.innerHTML =
		'<td><input type="number" class="pps-tier-min" min="1" step="1" value="' + ( min || '' ) + '"></td>' +
		'<td><input type="number" class="pps-tier-price" min="0" step="1" value="' + ( price || '' ) + '"></td>' +
		'<td><button type="button" class="pps-tier-delete button-link">×</button></td>';
	return tr;
}

function toggleTiersEditor( id ) {
	const tiersRow = document.querySelector( '.pps-tiers-row[data-for="' + id + '"]' );
	if ( ! tiersRow ) return;
	tiersRow.style.display = tiersRow.style.display === 'none' ? '' : 'none';
}

function collectTiersFromEditor( id ) {
	const tiersRow = document.querySelector( '.pps-tiers-row[data-for="' + id + '"]' );
	if ( ! tiersRow ) return tiersMap[ id ] || [];

	const tiers = [];
	tiersRow.querySelectorAll( '.pps-tier-row' ).forEach( row => {
		const min   = parseInt( row.querySelector( '.pps-tier-min' )?.value, 10 );
		const price = parseFloat( row.querySelector( '.pps-tier-price' )?.value );
		if ( min >= 1 && price > 0 ) tiers.push( { min, price } );
	} );

	tiers.sort( ( a, b ) => a.min - b.min );
	tiersMap[ id ] = tiers;

	const btn = document.querySelector( '.pps-tiers-btn[data-id="' + id + '"]' );
	if ( btn ) {
		btn.textContent = tiers.length > 0 ? '▾ Paliers (' + tiers.length + ')' : '▾ Paliers';
	}

	return tiers;
}

/* ------------------------------------------------------------------ */
/* Toggle variations                                                    */
/* ------------------------------------------------------------------ */

function toggleVariations( parentId, btn ) {
	const show = btn.classList.contains( 'collapsed' );

	document.querySelectorAll( 'tr[data-parent="' + parentId + '"]' ).forEach( varRow => {
		varRow.style.display = show ? '' : 'none';
		const tiersRow = varRow.nextElementSibling;
		if ( tiersRow?.classList.contains( 'pps-tiers-row' ) && ! show ) {
			tiersRow.style.display = 'none';
		}
	} );

	btn.classList.toggle( 'collapsed', ! show );
	btn.textContent = show ? '▾' : '▸';
}

/* ------------------------------------------------------------------ */
/* Bindings (délégation sur la table)                                   */
/* ------------------------------------------------------------------ */

function bindEvents() {
	const table = document.getElementById( 'pps-studio-table' );
	if ( ! table ) return;

	table.addEventListener( 'input', e => {
		const row = e.target.closest( 'tr[data-id]' );
		if ( ! row || row.dataset.hasVars === '1' ) return;

		if ( e.target.classList.contains( 'pps-cost-usd' ) ) {
			handleUsdChange( row, e.target );
		} else if ( e.target.classList.contains( 'pps-cost-cfa' ) ) {
			handleCfaChange( row, e.target );
		} else if (
			e.target.classList.contains( 'pps-partner-price' ) ||
			e.target.classList.contains( 'pps-price-regular' ) ||
			e.target.classList.contains( 'pps-price-sale' )
		) {
			recalcRow( row );
		}
	} );

	table.addEventListener( 'click', e => {
		// Paliers : toggle éditeur
		const tiersBtn = e.target.closest( '.pps-tiers-btn' );
		if ( tiersBtn ) {
			const id = parseInt( tiersBtn.dataset.id, 10 );
			collectTiersFromEditor( id );
			toggleTiersEditor( id );
			return;
		}

		// Paliers : ajouter une ligne
		const tierAdd = e.target.closest( '.pps-tier-add' );
		if ( tierAdd ) {
			tierAdd.closest( '.pps-tiers-editor' )?.querySelector( '.pps-tiers-body' )
				?.appendChild( buildTierRow( '', '' ) );
			return;
		}

		// Paliers : supprimer une ligne
		const tierDelete = e.target.closest( '.pps-tier-delete' );
		if ( tierDelete ) {
			tierDelete.closest( '.pps-tier-row' )?.remove();
			return;
		}

		// Enregistrer une ligne
		const saveBtn = e.target.closest( '.pps-save-row' );
		if ( saveBtn ) {
			saveRow( parseInt( saveBtn.dataset.id, 10 ) );
			return;
		}

		// Toggle variations
		const toggleBtn = e.target.closest( '.pps-toggle-vars' );
		if ( toggleBtn ) {
			toggleVariations( parseInt( toggleBtn.dataset.id, 10 ), toggleBtn );
			return;
		}

		// Réinitialiser override CFA manuel
		const resetBtn = e.target.closest( '.pps-reset-manual' );
		if ( resetBtn ) {
			const row = resetBtn.closest( 'tr[data-id]' );
			if ( ! row ) return;
			const cfaInput = row.querySelector( '.pps-cost-cfa' );
			const usdInput = row.querySelector( '.pps-cost-usd' );
			if ( cfaInput ) {
				cfaInput.dataset.manual    = '0';
				cfaInput.dataset.wasManual = '0';
				if ( usdInput ) {
					cfaInput.value = Math.round( parseFloat( usdInput.value || 0 ) * getGlobalRate() ) || '';
				}
				const badge = row.querySelector( '.pps-manual-badge' );
				if ( badge ) badge.style.display = 'none';
				resetBtn.style.display = 'none';
				recalcRow( row );
			}
		}
	} );

	// Taux global et frais globaux
	document.getElementById( 'pps-global-rate' )?.addEventListener( 'input', onGlobalRateChange );
	document.getElementById( 'pps-global-fees' )?.addEventListener( 'input', recalcAll );

	// Filtre catégorie
	document.getElementById( 'pps-filter-cat' )?.addEventListener( 'change', e => {
		const cat = e.target.value;
		document.querySelectorAll( '.pps-cat-group' ).forEach( group => {
			group.style.display = ( cat === '' || group.dataset.cat === cat ) ? '' : 'none';
		} );
	} );

	// Recherche
	let searchTimeout;
	document.getElementById( 'pps-search' )?.addEventListener( 'input', e => {
		clearTimeout( searchTimeout );
		searchTimeout = setTimeout( () => {
			const q = e.target.value.toLowerCase().trim();
			document.querySelectorAll( 'tr.pps-row-product, tr.pps-row-parent, tr.pps-row-variation' ).forEach( row => {
				const name = row.querySelector( '.pps-product-name' )?.textContent.toLowerCase() || '';
				const sku  = ( row.dataset.sku || '' ).toLowerCase();
				row.style.display = ( q === '' || name.includes( q ) || sku.includes( q ) ) ? '' : 'none';
			} );
		}, 200 );
	} );

	// Boutons tout enregistrer
	document.getElementById( 'pps-save-all' )?.addEventListener( 'click', bulkSave );
	document.querySelector( '.pps-save-all-bottom' )?.addEventListener( 'click', bulkSave );
}

/* ------------------------------------------------------------------ */
/* Sauvegarde                                                           */
/* ------------------------------------------------------------------ */

async function saveRow( id ) {
	const row      = document.querySelector( 'tr[data-id="' + id + '"]' );
	const statusEl = row?.querySelector( '.pps-row-status' );
	if ( ! row ) return;

	if ( statusEl ) statusEl.textContent = '…';

	const tiers    = collectTiersFromEditor( id );
	const products = {};
	products[ id ] = collectRowData( row, tiers );

	try {
		const result = await doAjax( 'pps_bulk_save', {
			rate:     document.getElementById( 'pps-global-rate' )?.value  || '',
			fees_pct: document.getElementById( 'pps-global-fees' )?.value  || '',
			products: JSON.stringify( products ),
		} );

		if ( statusEl ) {
			statusEl.className  = result.success ? 'pps-row-status pps-row-saved' : 'pps-row-status pps-row-error';
			statusEl.textContent = result.success ? '✓' : '✗';
			setTimeout( () => { statusEl.textContent = ''; statusEl.className = 'pps-row-status'; }, 2500 );
		}
	} catch {
		if ( statusEl ) { statusEl.className = 'pps-row-status pps-row-error'; statusEl.textContent = '✗'; }
	}
}

async function bulkSave() {
	if ( ! window.confirm( ppsStudio.i18n.confirm ) ) return;

	const saveBtn   = document.getElementById( 'pps-save-all' );
	const statusEl  = document.getElementById( 'pps-save-status' );
	const statusBot = document.querySelector( '.pps-save-status-bottom' );

	if ( saveBtn )   saveBtn.disabled = true;
	if ( statusEl )  statusEl.textContent  = ppsStudio.i18n.saving;
	if ( statusBot ) statusBot.textContent = ppsStudio.i18n.saving;

	const products = {};
	document.querySelectorAll( 'tr[data-id][data-has-vars="0"]' ).forEach( row => {
		const id = parseInt( row.dataset.id, 10 );
		products[ id ] = collectRowData( row, collectTiersFromEditor( id ) );
	} );

	try {
		const result = await doAjax( 'pps_bulk_save', {
			rate:     document.getElementById( 'pps-global-rate' )?.value  || '',
			fees_pct: document.getElementById( 'pps-global-fees' )?.value  || '',
			products: JSON.stringify( products ),
		} );

		const msg = result.success
			? ppsStudio.i18n.saved + ' (' + ( result.data?.message || '' ) + ')'
			: ppsStudio.i18n.error;

		if ( statusEl )  statusEl.textContent  = msg;
		if ( statusBot ) statusBot.textContent = msg;
	} catch {
		if ( statusEl )  statusEl.textContent  = ppsStudio.i18n.error;
		if ( statusBot ) statusBot.textContent = ppsStudio.i18n.error;
	} finally {
		if ( saveBtn ) saveBtn.disabled = false;
	}
}

function collectRowData( row, tiers ) {
	const cfaInput = row.querySelector( '.pps-cost-cfa' );
	return {
		regular_price:     row.querySelector( '.pps-price-regular' )?.value  || '',
		sale_price:        row.querySelector( '.pps-price-sale' )?.value     || '',
		cost_usd:          row.querySelector( '.pps-cost-usd' )?.value       || '',
		cost_cfa:          cfaInput?.value                                    || '',
		cost_cfa_is_manual: cfaInput?.dataset.wasManual === '1' ? '1' : '0',
		partner_price:     row.querySelector( '.pps-partner-price' )?.value  || '',
		partner_tiers:     JSON.stringify( tiers ),
	};
}

/* ------------------------------------------------------------------ */
/* Analytics (chargement AJAX différé)                                  */
/* ------------------------------------------------------------------ */

async function loadAnalytics() {
	const ids = window.ppsStudioProductIds || [];
	if ( ! ids.length ) return;

	document.querySelectorAll( '.pps-analytics-cell' ).forEach( cell => {
		cell.textContent = '…';
		cell.classList.add( 'pps-analytics-loading' );
	} );

	try {
		const result = await doAjax( 'pps_load_analytics', { ids: JSON.stringify( ids ) } );

		document.querySelectorAll( '.pps-analytics-cell' ).forEach( cell => {
			cell.classList.remove( 'pps-analytics-loading' );
			cell.textContent = '0';
		} );

		if ( ! result.success ) return;

		Object.entries( result.data ).forEach( ( [ id, s ] ) => {
			const cell = ( stat ) => document.querySelectorAll(
				'.pps-analytics-cell[data-id="' + id + '"][data-stat="' + stat + '"]'
			);
			cell( 'orders_week' ).forEach(  c => { c.textContent = s.orders_week  || 0; } );
			cell( 'orders_month' ).forEach( c => { c.textContent = s.orders_month || 0; } );
			cell( 'revenue_month' ).forEach( c => { c.textContent = formatCfa( s.revenue_month || 0 ); } );
		} );
	} catch {
		document.querySelectorAll( '.pps-analytics-cell' ).forEach( cell => {
			cell.classList.remove( 'pps-analytics-loading' );
			cell.textContent = '—';
		} );
	}
}

/* ------------------------------------------------------------------ */
/* Utilitaires                                                          */
/* ------------------------------------------------------------------ */

async function doAjax( action, data ) {
	const body = new URLSearchParams( {
		action,
		nonce: ppsStudio.nonce,
		...data,
	} );
	const res = await fetch( ppsStudio.ajaxUrl, {
		method:  'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body:    body.toString(),
	} );
	return res.json();
}

function formatCfa( val ) {
	if ( val === null || val === undefined || isNaN( val ) ) return '—';
	return Math.round( val ).toLocaleString( 'fr-FR' ) + ' CFA';
}

function formatPct( val ) {
	if ( val === null || val === undefined || isNaN( val ) ) return '—';
	return val.toFixed( 1 ) + '%';
}
