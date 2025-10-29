jQuery( function ( $ ) {
	$( document ).on(
		'click',
		'.finix-apple-pay.register-domain-button button',
		function ( e ) {
			const button = $( this );

			$.ajax( {
				url: finix_apple_pay_params.ajax_url,
				type: 'POST',
				data: {
					register_domain: true,
					wp_nonce: finix_apple_pay_params.nonce,
				},
				beforeSend() {
					button
						.html( finix_apple_pay_params.text.processing )
						.prop( 'disabled', true );
				},
				success( response ) {
					if ( response.success ) {
						$( '.finix-apple-pay.register-domain-button' ).remove();

						$( '.finix-apple-pay.register-domain-message' )
							.html( response.data.message )
							.addClass( 'updated success' );
					} else {
						$( '.finix-apple-pay.register-domain-message' )
							.html( response.data.message )
							.addClass( 'updated error' );
					}
				},
				error( response ) {
					$( '.finix-apple-pay.register-domain-message' )
						.html( response.data.message )
						.addClass( 'updated error' );
				},
				complete() {
					$( '.finix-apple-pay.register-domain-button' ).remove();
				},
			} );
		}
	);
} );
