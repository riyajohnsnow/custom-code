jQuery(document).ready(function($){
	var timezone = jstimezonedetect.determine();

	$( '#hf-timezone-em' ).text( timezone.name() );

	$( '.hf-subscription.date-picker' ).each(function(){
		var $date_input   = $(this),
			date_type     = $date_input.attr( 'id' ),
			$hour_input   = $( '#'+date_type+'_hour' ),
			$minute_input = $( '#'+date_type+'_minute' ),
			time          = $('#'+date_type+'_timestamp_utc').val(),
			date          = moment.unix(time);

		if ( time > 0 ) {
			date.local();
			$date_input.val( date.year() + '-' + ( zeroise( date.months() + 1 ) ) + '-' + ( date.format( 'DD' ) ) );
			$hour_input.val( date.format( 'HH' ) );
			$minute_input.val( date.format( 'mm' ) );
		}
	});

	$( '.hf-subscription.date-picker:not(#start)' ).datepicker( 'option','minDate',moment().add(1,'hours').toDate());

	$( '[name$="_hour"], [name$="_minute"]' ).on( 'change', function() {
		$( '#' + $(this).attr( 'name' ).replace( '_hour', '' ).replace( '_minute', '' ) ).change();
	});

	$( '.hf-subscription.date-picker' ).on( 'change',function(){

		if( '' == $(this).val() ) {
			$( '#' + $(this).attr( 'id' ) + '_hour' ).val('');
			$( '#' + $(this).attr( 'id' ) + '_minute' ).val('');
			$( '#' + $(this).attr( 'id' ) + '_timestamp_utc' ).val(0);
			return;
		}

                    var time_now          = moment(),
			one_hour_from_now = moment().add(1,'hours' ),
			$date_input   = $(this),
			date_type     = $date_input.attr( 'id' ),
			date_pieces   = $date_input.val().split( '-' ),
			$hour_input   = $( '#'+date_type+'_hour' ),
			$minute_input = $( '#'+date_type+'_minute' ),
			chosen_hour   = (0 == $hour_input.val().length) ? one_hour_from_now.format( 'HH' ) : $hour_input.val(),
			chosen_minute = (0 == $minute_input.val().length) ? one_hour_from_now.format( 'mm' ) : $minute_input.val(),
			chosen_date   = moment({
				years:   date_pieces[0],
				months: (date_pieces[1] - 1),
				date:   (date_pieces[2]),
				hours:   chosen_hour,
				minutes: chosen_minute,
				seconds: one_hour_from_now.format( 'ss' )
			});


		if ( 'start' == date_type ) {

			if ( false === chosen_date.isBefore( time_now ) ) {
				alert( hf_admin_meta_boxes.i18n_start_date_notice );
				$date_input.val( time_now.year() + '-' + ( zeroise( time_now.months() + 1 ) ) + '-' + ( time_now.format( 'DD' ) ) );
				$hour_input.val( time_now.format( 'HH' ) );
				$minute_input.val( time_now.format( 'mm' ) );
			}

		}

		else if ( ('end' == date_type ) && '' != $( '#next_payment' ).val() ) {
			var change_date  = false,
				next_payment = moment.unix( $('#next_payment_timestamp_utc').val() );

			if ( 'end' == date_type && chosen_date.isBefore( next_payment, 'minute' ) ) {
				alert( hf_admin_meta_boxes.i18n_end_date_notice );
				change_date = true;
			}

			if ( true === change_date ) {
				$date_input.val( next_payment.year() + '-' + ( zeroise( next_payment.months() + 1 ) ) + '-' + ( next_payment.format( 'DD' ) ) );
				$hour_input.val( next_payment.format( 'HH' ) );
				$minute_input.val( next_payment.format( 'mm' ) );
			}
		}

		if( 0 == $hour_input.val().length ){
			$hour_input.val(one_hour_from_now.format( 'HH' ));
		}

		if( 0 == $minute_input.val().length ){
			$minute_input.val(one_hour_from_now.format( 'mm' ));
		}

		date_pieces = $date_input.val().split( '-' );

		$('#'+date_type+'_timestamp_utc').val(moment({
			years:   date_pieces[0],
			months: (date_pieces[1] - 1),
			date:   (date_pieces[2]),
			hours:   $hour_input.val(),
			minutes: $minute_input.val(),
			seconds: one_hour_from_now.format( 'ss' )
		}).utc().unix());

		$( 'body' ).trigger( 'hf-updated-date',date_type);
	});

	function zeroise( val ) {
		return (val > 9 ) ? val : '0' + val;
	}

	$('body.post-type-hf_shop_subscription #post').submit(function(){
		if('hf_process_renewal' == $( "body.post-type-hf_shop_subscription select[name='wc_order_action']" ).val()) {
			return confirm(hf_admin_meta_boxes.process_renewal_action_warning);
		}
	});

	$('body.post-type-hf_shop_subscription #post').submit(function(){
		if ( typeof hf_admin_meta_boxes.change_payment_method_warning != 'undefined' && hf_admin_meta_boxes.payment_method != $('#_payment_method').val() ) {
			return confirm(hf_admin_meta_boxes.change_payment_method_warning);
		}
	});
});