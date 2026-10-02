jQuery( document ).ready( function() {

	var use_smtp_auth = jQuery( '#smtp_auth' ).prop( 'checked' );

	if( ! use_smtp_auth )
		jQuery( '#smtp_auth_parameters' ).fadeOut( 'slow' );

	jQuery( '#smtp_auth' ).click( function() {
		if( use_smtp_auth ) {		
			jQuery( '#smtp_auth_parameters' ).fadeOut( 'slow' );
			use_smtp_auth = false;
		}
		else {
			jQuery( '#smtp_auth_parameters' ).fadeIn( 'slow' );
			use_smtp_auth = true;
		}
	});

	jQuery( '#smtp_secure' ).click( function() {

	});

});
