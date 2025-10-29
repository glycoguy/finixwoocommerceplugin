var FinixHelpers = {
	getUpdatedValue: function ( selector ) {
		// Validate existence of the selector
		if ( ! jQuery( selector ).length ) {
			return '0.00';
		}

		let rawTotal = jQuery( selector ).text().trim();

		// Clean unwanted currency symbols, letters, non-breaking spaces, etc.
		let numericPart = rawTotal
			.replace( /[^\d.,]/g, '' )
			.replace( /\u00A0/g, '' )
			.trim();

		// Determine the decimal separator.
		let hasComma = numericPart.includes( ',' );
		let hasDot = numericPart.includes( '.' );
		let cleanTotal = numericPart;

		if ( hasComma && ! hasDot ) {
			// Format like "0,69" or "1.200,50".
			cleanTotal = numericPart.replace( ',', '.' );
		} else if ( hasDot && ! hasComma ) {
			// Format like "0.69" or "1,200.50".
			cleanTotal = numericPart;
		} else if ( hasComma && hasDot ) {
			// Determine which is the decimal separator based on position.
			let lastComma = numericPart.lastIndexOf( ',' );
			let lastDot = numericPart.lastIndexOf( '.' );

			if ( lastComma > lastDot ) {
				// Assume comma is decimal separator, dot is thousands separator.
				cleanTotal = numericPart.replace( /\./g, '' ).replace( ',', '.' );
			} else {
				// Assume dot is decimal separator, comma is thousands separator.
				cleanTotal = numericPart.replace( /,/g, '' );
			}
		}

		// Determine the number of decimals from WooCommerce if available.
		let decimals = 2;
		if ( typeof wc_checkout_params !== 'undefined' && wc_checkout_params.currency_format_num_decimals ) {
			decimals = parseInt( wc_checkout_params.currency_format_num_decimals );
		}

		return parseFloat( cleanTotal ).toFixed( decimals );
	},

	getUpdatedTotal: function () {
		return this.getUpdatedValue( '.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount' );
	},
};
