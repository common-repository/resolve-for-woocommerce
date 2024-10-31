jQuery( function( $ ) {
	'use strict';

	// Swap visibility of test and live credential fields depending on test mode on/off.
	$( '#woocommerce_resolve-pay-gateway_in-test-mode' ).on( 'change', function(e) {
		$('[id*="webshop"]').closest('tr').toggle(! e.target.checked);
		$('[id*="test-webshop"]').closest('tr').toggle(e.target.checked);
	} ).trigger( 'change' );

	// Display confirmation notice for capturing funds.
	$('#rfw-capture-payment').on( 'click', function( e ) {
		if ( ! confirm( RFWPaymentGateway.capture_notice ) ) {
			e.stopPropagation();
			e.preventDefault();
			return false;
		}
	} );

} );
