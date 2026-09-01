/**
 * Quantity stepper inside the mini cart.
 *
 * Deliberately small. The server owns every number in the panel — line total, subtotal, the cart
 * bubble — so this file does one thing: send the new quantity and ask WooCommerce to repaint.
 * Painting the new figure here as well would create a second, private copy of a truth that only
 * the cart can compute, and the two would disagree the first time a coupon or a stock limit had
 * an opinion.
 */

( function ( $ ) {
	'use strict';

	if ( 'undefined' === typeof window.nvmMiniCart ) {
		return;
	}

	// The panel is replaced wholesale on every fragment refresh, so a listener bound to the
	// buttons themselves would survive exactly one click. This one is bound to the document.
	var busy = false;

	$( document ).on( 'click', '[data-nvm-mc-step]', function ( event ) {
		event.preventDefault();

		// One request at a time: two fast clicks would otherwise race, and the loser would
		// overwrite the winner with a quantity computed from a stale number.
		if ( busy ) {
			return;
		}

		var $button  = $( this );
		var $stepper = $button.closest( '[data-nvm-mc-key]' );
		var key      = $stepper.attr( 'data-nvm-mc-key' );
		var step     = parseInt( $button.attr( 'data-nvm-mc-step' ), 10 );
		var current  = parseInt( $stepper.find( '[data-nvm-mc-value]' ).text(), 10 );
		var max      = parseInt( $stepper.attr( 'data-nvm-mc-max' ), 10 );

		if ( ! key || isNaN( current ) || isNaN( step ) ) {
			return;
		}

		var next = current + step;

		// The server clamps too. This is only to avoid a pointless round trip.
		if ( next < 0 || ( max > 0 && next > max ) ) {
			return;
		}

		busy = true;
		$stepper.addClass( 'is-busy' );

		$.post( window.nvmMiniCart.ajaxUrl, {
			action: window.nvmMiniCart.action,
			nonce: window.nvmMiniCart.nonce,
			key: key,
			quantity: next
		} ).always( function () {
			busy = false;

			// Refresh on failure as well. If the request was rejected the panel is showing a
			// quantity the cart never accepted, and repainting from the server is what puts the
			// shopper back in front of the real one.
			$( document.body ).trigger( 'wc_fragment_refresh' );
		} );
	} );
}( jQuery ) );
