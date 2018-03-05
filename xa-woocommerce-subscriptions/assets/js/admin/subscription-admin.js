jQuery(document).ready(function($){

	$.extend({
		getParamByName: function(name) {
			name = name.replace(/[\[]/, '\\\[').replace(/[\]]/, '\\\]');
			var regexS = '[\\?&]' + name + '=([^&#]*)';
			var regex = new RegExp(regexS);
			var results = regex.exec(window.location.search);
			if(results == null) {
				return '';
			} else {
				return decodeURIComponent(results[1].replace(/\+/g, ' '));
			}
		},
		showHideSubscriptionMetaData: function(){
			if ($('select#product-type').val()==HFSubscriptions_OBJ.ProductType) {
				$('.show_if_simple').show();
				$('.grouping_options').hide();
				$('.options_group.pricing ._regular_price_field').hide();
				$('#sale-price-period').show();
				$('.hide_if_subscription').hide();
				$( 'input#_manage_stock' ).change();

				if('day' == $('#_subscription_period').val()) {
					$('.subscription_sync').hide();
				}
			} else {
				$('.options_group.pricing ._regular_price_field').show();
				$('#sale-price-period').hide();
			}
		},
		showHideVariableSubscriptionMetaData: function(){
			if ($('select#product-type').val()=='variable-subscription') {
				$( 'input#_downloadable' ).prop( 'checked', false );
				$( 'input#_virtual' ).removeAttr( 'checked' );
				$('.show_if_variable').show();
				$('.hide_if_variable').hide();
				$('.show_if_variable-subscription').show();
				$('.hide_if_variable-subscription').hide();
				$( 'input#_manage_stock' ).change();
				$('.sale_price_dates_fields').prev('.form-row').addClass('form-row-full').removeClass('form-row-last');

			} else {

				if ($('select#product-type').val()=='variable') {
					$('.show_if_variable-subscription').hide();
					$('.show_if_variable').show();
					$('.hide_if_variable').hide();
				}

				$('.sale_price_dates_fields').prev('.form-row').removeClass('form-row-full').addClass('form-row-last');
			}
		},
		setSubscriptionLengths: function(){
			$('[name^="_subscription_length"], [name^="variable_subscription_length"]').each(function(){
				var $lengthElement = $(this),
					selectedLength = $lengthElement.val(),
					hasSelectedLength = false,
					matches = $lengthElement.attr('name').match(/\[(.*?)\]/),
					periodSelector,
					interval;

				if (matches) {
					periodSelector = '[name="variable_subscription_period['+matches[1]+']"]';
					billingInterval = parseInt($('[name="variable_subscription_period_interval['+matches[1]+']"]').val());
				} else {
					periodSelector = '#_subscription_period';
					billingInterval = parseInt($('#_subscription_period_interval').val());
				}

				$lengthElement.empty();

				$.each(HFSubscriptions_OBJ.LocalizedSubscriptionLengths[ $(periodSelector).val() ], function(length,description) {
					if(parseInt(length) == 0 || 0 == (parseInt(length) % billingInterval)) {
						$lengthElement.append($('<option></option>').attr('value',length).text(description));
					}
				});

				$lengthElement.children('option').each(function(){
					if (this.value == selectedLength) {
						hasSelectedLength = true;
						return false;
					}
				});

				if(hasSelectedLength){
					$lengthElement.val(selectedLength);
				} else {
					$lengthElement.val(0);
				}

			});
		},
		
		setSalePeriod: function(){
			$('#sale-price-period').fadeOut(80,function(){
				$('#sale-price-period').text($('#_subscription_period_interval option:selected').text()+' '+$('#_subscription_period option:selected').text());
				$('#sale-price-period').fadeIn(180);
			});
		},

		moveSubscriptionVariationFields: function(){
			$('#variable_product_options .variable_subscription_pricing').not('hf_moved').each(function(){
				var $regularPriceRow = $(this).siblings('.variable_pricing'),
					//$trialSignUpRow  = $(this).siblings('.variable_subscription_trial_sign_up'),
					$saleDatesRow;

				$saleDatesRow = $(this).siblings('.variable_pricing');
				$(this).insertBefore($regularPriceRow);
				//$trialSignUpRow.insertBefore($(this));
				$regularPriceRow.children(':first').addClass('hide_if_variable-subscription');
				$(this).addClass('hf_moved');
			});
		},
		getVariationBulkEditValue: function(variation_action){
			
                        var value;
			switch( variation_action ) {
				case 'variable_subscription_period':
					value = prompt( HFSubscriptions_OBJ.BulkEditPeriodMessage );
					break;
				case 'variable_subscription_period_interval':
					value = prompt( HFSubscriptions_OBJ.BulkEditIntervalMessage );
					break;
				case 'variable_subscription_length':
					value = prompt( HFSubscriptions_OBJ.BulkEditLengthMessage );
					break;

			}

			return value;
		},
		
		showHideSubscriptionsPanels: function() {
			var tab = $( 'div.panel-wrap' ).find( 'ul.wc-tabs li' ).eq( 0 ).find( 'a' );
			var panel = tab.attr( 'href' );
			var visible = $( panel ).children( '.options_group' ).filter( function() {
				return 'none' != $( this ).css( 'display' );
			});
			if ( 0 != visible.length ) {
				tab.click().parent().show();
			}
		},
	});

	$('.options_group.pricing ._sale_price_field .description').prepend('<span id="sale-price-period" style="display: none;"></span>');
	$('.options_group.subscription_pricing').not('.variable_subscription_pricing .options_group.subscription_pricing').insertBefore($('.options_group.pricing:first'));
	$('.show_if_subscription.clear').insertAfter($('.options_group.subscription_pricing'));

	if($('#variable_product_options .variable_subscription_pricing').length > 0) {
		$.moveSubscriptionVariationFields();
	}
        
	$('#woocommerce-product-data').on('woocommerce_variations_added woocommerce_variations_loaded',function(){
		$.moveSubscriptionVariationFields();
		$.showHideVariableSubscriptionMetaData();
		$.setSubscriptionLengths();
	});

	if($('.options_group.pricing').length > 0) {
		$.setSalePeriod();
		$.showHideSubscriptionMetaData();
		$.showHideVariableSubscriptionMetaData();
		$.setSubscriptionLengths();
		$.showHideSubscriptionsPanels();
	}

	$('#woocommerce-product-data').on('change','[name^="_subscription_period"], [name^="_subscription_period_interval"], [name^="variable_subscription_period"], [name^="variable_subscription_period_interval"]',function(){
		$.setSubscriptionLengths();
                $.setSalePeriod();
	});

	$('body').bind('woocommerce-product-type-change',function(){
		$.showHideSubscriptionMetaData();
		$.showHideVariableSubscriptionMetaData();
		$.showHideSubscriptionsPanels();
	});

	$('input#_downloadable, input#_virtual').change(function(){
		$.showHideSubscriptionMetaData();
		$.showHideVariableSubscriptionMetaData();
	});

	$('body').on('woocommerce_added_attribute', function(){
		$.showHideVariableSubscriptionMetaData();
	});

	if($.getParamByName('select_subscription')=='true'){
		$('select#product-type option[value="'+HFSubscriptions_OBJ.ProductType+'"]').attr('selected', 'selected');
		$('select#product-type').select().change();
	}


	$('#posts-filter').submit(function(){
		if($('[name="post_type"]').val()=='shop_order'&&($('[name="action"]').val()=='trash'||$('[name="action2"]').val()=='trash')){
			var containsSubscription = false;
			$('[name="post[]"]:checked').each(function(){
				if(true===$('.contains_subscription',$('#post-'+$(this).val())).data('contains_subscription')){
					containsSubscription = true;
				}
				return (false === containsSubscription);
			});
			if(containsSubscription){
				return confirm(HFSubscriptions_OBJ.BulkTrashWarning);
			}
		}
	});

	$('.order_actions .submitdelete').click(function(){
		if($('[name="contains_subscription"]').val()=='true'){
			return confirm(HFSubscriptions_OBJ.TrashWarning);
		}
	});

	$( '.row-actions .submitdelete' ).click( function() {
		var order = $( this ).closest( '.type-shop_order' ).attr( 'id' );
		if ( true === $( '.contains_subscription', $( '#' + order ) ).data( 'contains_subscription' ) ) {
			return confirm( HFSubscriptions_OBJ.TrashWarning );
		}
	});

	$(window).load(function(){
		if($('[name="contains_subscription"]').length > 0 && $('[name="contains_subscription"]').val()=='true'){
			$('#woocommerce-order-totals').show();
		} else {
			$('#woocommerce-order-totals').hide();
		}
	});

	$('#variable_product_options').on('change','[name^="variable_regular_price"]',function(){
		var matches = $(this).attr('name').match(/\[(.*?)\]/);
		if (matches) {
			var loopIndex = matches[1];
			$('[name="variable_subscription_price['+loopIndex+']"]').val($(this).val());
		}
	});

	$('#variable_product_options').on('change','[name^="variable_subscription_price"]',function(){
		var matches = $(this).attr('name').match(/\[(.*?)\]/);
		if (matches) {
			var loopIndex = matches[1];
			$('[name="variable_regular_price['+loopIndex+']"]').val($(this).val());
		}
	});

	$('#general_product_data').on('change', '[name^="_hf_subscription_price"]', function() {
		$('[name="_regular_price"]').val($(this).val());
	});

	$('.users-php .submitdelete').on('click',function(){
		return confirm(HFSubscriptions_OBJ.DeleteUserWarning);
	});

	$('select.variation_actions').on('variable_subscription_sign_up_fee_ajax_data variable_subscription_period_interval_ajax_data variable_subscription_period_ajax_data variable_subscription_trial_period_ajax_data variable_subscription_trial_length_ajax_data variable_subscription_length_ajax_data', function(event, data) {
		bulk_action = event.type.replace(/_ajax_data/g,'');
		value = $.getVariationBulkEditValue( bulk_action );


		if ( value != null ) {
			data.value = value;
		}
		return data;
	});

	
	

	$( 'body' ).on( 'woocommerce-display-product-type-alert', function(e, select_val) {
		if (select_val=='variable-subscription') {
			return false;
		}
	});

	$('.hf_payment_method_selector').on('change', function() {
		var payment_method = $(this).val();
		$('.hf_payment_method_meta_fields').hide();
		$('#hf_' + payment_method + '_fields').show();
	});



});