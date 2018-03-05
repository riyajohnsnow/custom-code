<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hf_price_string( $subscription_details ) {
    
	global $wp_locale;
	$subscription_details = wp_parse_args( $subscription_details, array(
			'currency'              => '',
			'initial_amount'        => '',
			'initial_description'   => __( 'up front', HF_Subscriptions::TEXT_DOMAIN ),
			'recurring_amount'      => '',

			'subscription_interval' => 1,
			'subscription_period'   => '',
			'subscription_length'   => 0,
			'trial_length'          => 0,
			'trial_period'          => '',

			'is_synced'                => false,
			'synchronised_payment_day' => 0,

			'display_excluding_tax_label' => false,

			'use_per_slash' => true,
		)
	);

	$subscription_details['subscription_period'] = strtolower( $subscription_details['subscription_period'] );

	if ( is_numeric( $subscription_details['initial_amount'] ) ) {
		$initial_amount_string = wc_price( $subscription_details['initial_amount'], array( 'currency' => $subscription_details['currency'], 'ex_tax_label' => $subscription_details['display_excluding_tax_label'] ) );
	} else {
		$initial_amount_string = $subscription_details['initial_amount'];
	}

	if ( is_numeric( $subscription_details['recurring_amount'] ) ) {
		$recurring_amount_string = wc_price( $subscription_details['recurring_amount'], array( 'currency' => $subscription_details['currency'], 'ex_tax_label' => $subscription_details['display_excluding_tax_label'] ) );
	} else {
		$recurring_amount_string = $subscription_details['recurring_amount'];
	}

	$subscription_period_string = hf_get_subscription_period_strings( $subscription_details['subscription_interval'], $subscription_details['subscription_period'] );
	$subscription_ranges = hf_get_subscription_ranges();

	if ( $subscription_details['subscription_length'] > 0 && $subscription_details['subscription_length'] == $subscription_details['subscription_interval'] ) {
		if ( ! empty( $subscription_details['initial_amount'] ) ) {
			if ( $subscription_details['subscription_interval'] == $subscription_details['subscription_length'] && 0 == $subscription_details['trial_length'] ) {
				$subscription_string = $initial_amount_string;
			} else {
				$subscription_string = sprintf( __( '%1$s %2$s then %3$s', HF_Subscriptions::TEXT_DOMAIN ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string );
			}
		} else {
			$subscription_string = $recurring_amount_string;
		}
	} elseif ( true === $subscription_details['is_synced'] && in_array( $subscription_details['subscription_period'], array( 'week', 'month', 'year' ) ) ) {
            // do desired thing here
	} elseif ( ! empty( $subscription_details['initial_amount'] ) ) {
		$subscription_string = sprintf( _n( '%1$s %2$s then %3$s / %4$s', '%1$s %2$s then %3$s every %4$s', $subscription_details['subscription_interval'], HF_Subscriptions::TEXT_DOMAIN ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, $subscription_period_string );
	} elseif ( ! empty( $subscription_details['recurring_amount'] ) || intval( $subscription_details['recurring_amount'] ) === 0 ) {
		if ( true === $subscription_details['use_per_slash'] ) {
			$subscription_string = sprintf( _n( '%1$s / %2$s', '%1$s every %2$s', $subscription_details['subscription_interval'], HF_Subscriptions::TEXT_DOMAIN ), $recurring_amount_string, $subscription_period_string );
		} else {
			$subscription_string = sprintf( __( '%1$s every %2$s', HF_Subscriptions::TEXT_DOMAIN ), $recurring_amount_string, $subscription_period_string );
		}
	} else {
		$subscription_string = '';
	}

	if ( $subscription_details['subscription_length'] > 0 ) {
		$subscription_string = sprintf( __( '%1$s for %2$s', HF_Subscriptions::TEXT_DOMAIN ), $subscription_string, $subscription_ranges[ $subscription_details['subscription_period'] ][ $subscription_details['subscription_length'] ] );
	}

	if ( $subscription_details['trial_length'] > 0 ) {
		$trial_length = hf_get_subscription_trial_period_strings( $subscription_details['trial_length'], $subscription_details['trial_period'] );
		if ( ! empty( $subscription_details['initial_amount'] ) ) {
			$subscription_string = sprintf( __( '%1$s after %2$s free trial', HF_Subscriptions::TEXT_DOMAIN ), $subscription_string, $trial_length );
		} else {
			$subscription_string = sprintf( __( '%1$s free trial then %2$s', HF_Subscriptions::TEXT_DOMAIN ), ucfirst( $trial_length ), $subscription_string );
		}
	}

	if ( $subscription_details['display_excluding_tax_label'] && wc_tax_enabled() ) {
		$subscription_string .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
	}

	return apply_filters( 'hf_subscription_price_string', $subscription_string, $subscription_details );
}




 
function hf_get_variation_prices( $variation, $variable_product ) {

	return array(
		'price'         => apply_filters( 'woocommerce_variation_prices_price', HF_Subscriptions_Product::get_price( $variation ), $variation, $variable_product ),
		'regular_price' => apply_filters( 'woocommerce_variation_prices_regular_price', HF_Subscriptions_Product::get_regular_price( $variation, 'edit' ), $variation, $variable_product ),
		'sale_price'    => apply_filters( 'woocommerce_variation_prices_sale_price', HF_Subscriptions_Product::get_sale_price( $variation, 'edit' ), $variation, $variable_product ),
	);
}

function hf_get_price_including_tax( $product, $args = array() ) {

	$args = wp_parse_args( $args, array(
		'qty'   => 1,
		'price' => $product->get_price(),
	) );

	if ( function_exists( 'wc_get_price_including_tax' ) ) { 
		$price = wc_get_price_including_tax( $product, $args );
	} else { 
		$price = $product->get_price_including_tax( $args['qty'], $args['price'] );
	}
	return $price;
}

function hf_get_price_excluding_tax( $product, $args = array() ) {

	$args = wp_parse_args( $args, array(
		'qty'   => 1,
		'price' => $product->get_price(),
	) );

	if ( function_exists( 'wc_get_price_excluding_tax' ) ) { 
		$price = wc_get_price_excluding_tax( $product, $args );
	} else { 
		$price = $product->get_price_excluding_tax( $args['qty'], $args['price'] );
	}
	return $price;
}


function hf_get_price_html_from_text( $product = '' ) {

	if ( function_exists( 'wc_get_price_html_from_text' ) ) {
		$price_html_from_text = wc_get_price_html_from_text();
	} else { 
		$price_html_from_text = $product->get_price_html_from_text();
	}
	return $price_html_from_text;
}



function hf_get_min_max_variation_data( $variable_product, $child_variation_ids = array() ) {

	if ( empty( $child_variation_ids ) ) {
		$child_variation_ids = is_callable( array( $variable_product, 'get_visible_children' ) ) ? $variable_product->get_visible_children() : $variable_product->get_children( true );
	}

	$variations_data = array();

	foreach ( $child_variation_ids as $variation_id ) {

		if ( $variation = wc_get_product( $variation_id ) ) {

			$prices = hf_get_variation_prices( $variation, $variable_product );

			foreach ( $prices as $price_key => $amount ) {
				if ( '' !== $amount ) {
					if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
						$prices[ $price_key ] = hf_get_price_including_tax( $variable_product, array( 'price' => $amount ) );
					} else {
						$prices[ $price_key ] = hf_get_price_excluding_tax( $variable_product, array( 'price' => $amount ) );
					}
				}
			}

			$variations_data[ $variation_id ] = array(
				'price'         => $prices['price'],
				'regular_price' => $prices['regular_price'],
				'sale_price'    => $prices['sale_price'],
				'subscription'  => array(
					'period'          => HF_Subscriptions_Product::get_period( $variation ),
					'interval'        => HF_Subscriptions_Product::get_interval( $variation ),
					'length'          => HF_Subscriptions_Product::get_length( $variation ),
				),
			);
		}
	}
	return hf_calculate_min_max_variations( $variations_data );
}

function hf_calculate_min_max_variations( $variations_data ) {

	$lowest_initial_amount             = $highest_initial_amount = $lowest_price = $highest_price = '';
	$shortest_initial_period           = $longest_initial_period = $shortest_trial_period = $longest_trial_period = $shortest_trial_length = $longest_trial_length = '';
	$longest_initial_interval          = $shortest_initial_interval = $variable_subscription_period = $variable_subscription_period_interval = '';
	$lowest_regular_price              = $highest_regular_price = $lowest_sale_price = $highest_sale_price = $max_subscription_period = $max_subscription_period_interval = '';
	$variable_subscription_length      = $variable_subscription_length = '';
	$min_variation_id                  = $max_variation_id = null;

	$variations_data_prices_list        = array();
	$variations_data_periods_list       = array();
	$variations_data_intervals_list     = array();
	$variations_data_lengths_list       = array();

	foreach ( $variations_data as $variation_id => $variation_data ) {

		$is_max = $is_min = false;
		if ( '' === $variation_data['price'] ) {
			continue;
		}

		$variations_data_prices_list        = array_unique( array_merge( $variations_data_prices_list, array( $variation_data['price'] ) ) );
		$variations_data_periods_list       = array_unique( array_merge( $variations_data_periods_list, array( $variation_data['subscription']['period'] ) ) );
		$variations_data_intervals_list     = array_unique( array_merge( $variations_data_intervals_list, array( $variation_data['subscription']['interval'] ) ) );
		$variations_data_lengths_list       = array_unique( array_merge( $variations_data_lengths_list, array( $variation_data['subscription']['length'] ) ) );

		$has_free_trial = false;
		$is_lowest_price     = ( $variation_data['price'] < $lowest_price || '' === $lowest_price ) ? true : false;
		$is_longest_period   = ( HF_Subscriptions::get_longest_possible_period( $variable_subscription_period, $variation_data['subscription']['period'] ) === $variation_data['subscription']['period'] ) ? true : false;
		$is_longest_interval = ( $variation_data['subscription']['interval'] >= $variable_subscription_period_interval || '' === $variable_subscription_period_interval ) ? true : false;


			$initial_amount   = $variation_data['price'] ;
			$initial_period   = $variation_data['subscription']['period'];
			$initial_interval = $variation_data['subscription']['interval'];
		


			$longest_initial_period  = HF_Subscriptions::get_longest_possible_period( $longest_initial_period, $initial_period );
			$shortest_initial_period = HF_Subscriptions::get_shortest_possible_period( $shortest_initial_period, $initial_period );

			$is_lowest_initial_amount    = ( $initial_amount < $lowest_initial_amount || '' === $lowest_initial_amount ) ? true : false;
			$is_longest_initial_period   = ( $initial_period === $longest_initial_period ) ? true : false;
			$is_longest_initial_interval = ( $initial_interval >= $longest_initial_interval || '' === $longest_initial_interval ) ? true : false;

			$is_highest_initial   = ( $initial_amount > $highest_initial_amount || '' === $highest_initial_amount ) ? true : false;
			$is_shortest_period   = ( $initial_period === $shortest_initial_period || '' === $shortest_initial_period ) ? true : false;
			$is_shortest_interval = ( $initial_interval < $shortest_initial_interval || '' === $shortest_initial_interval ) ? true : false;

			if ( ! $is_lowest_initial_amount && $initial_amount !== $lowest_initial_amount ) {
				continue;
			}

			if ( $is_lowest_initial_amount ) {
				$is_min = true;
			} elseif ( $initial_amount === $lowest_initial_amount ) {
				if ( $has_free_trial && $initial_period == $longest_initial_period && $initial_interval == $longest_initial_interval ) {
					if ( $is_lowest_price ) {
						$is_min = true;
					} elseif ( $variation_data['price'] === $lowest_price ) {
						if ( $is_longest_period && $is_longest_interval ) {
							$is_min = true;
						} elseif ( $is_longest_period && $variation_data['subscription']['period'] !== $variable_subscription_period ) {
							$is_min = true;
						}
					}
				} elseif ( $is_longest_initial_period && $is_longest_initial_interval ) {
					$is_min = true;
				} elseif ( $is_longest_initial_period && $initial_period !== $variable_subscription_period ) {
					$is_min = true;
				}
			}

			if ( $is_highest_initial && $is_shortest_period && $is_shortest_interval ) {
				$is_max = true;
			} elseif ( $variation_data['price'] === $highest_price ) {
				if ( $is_shortest_period && $is_shortest_interval ) {
					$is_max = true;
				} elseif ( $is_shortest_period ) {
					$is_max = true;
				}
			}
		

		if ( $is_min ) {

			$min_variation_id      = $variation_id;
			$lowest_price          = $variation_data['price'];
			$lowest_regular_price  = $variation_data['regular_price'];
			$lowest_sale_price     = $variation_data['sale_price'];
			$lowest_regular_price = ( '' === $lowest_regular_price ) ? 0 : $lowest_regular_price;
			$lowest_sale_price    = ( '' === $lowest_sale_price ) ? 0 : $lowest_sale_price;
			$lowest_initial_amount    = $initial_amount;
			$longest_initial_period   = $initial_period;
			$longest_initial_interval = $initial_interval;

			$variable_subscription_period          = $variation_data['subscription']['period'];
			$variable_subscription_period_interval = $variation_data['subscription']['interval'];
			$variable_subscription_length          = $variation_data['subscription']['length'];
		}

		if ( $is_max ) {

			$max_variation_id       = $variation_id;
			$highest_price          = $variation_data['price'];
			$highest_regular_price  = $variation_data['regular_price'];
			$highest_sale_price     = $variation_data['sale_price'];
			$highest_initial_amount = $initial_amount;
			$highest_regular_price = ( '' === $highest_regular_price ) ? 0 : $highest_regular_price;
			$highest_sale_price    = ( '' === $highest_sale_price ) ? 0 : $highest_sale_price;
			$max_subscription_period          = $variation_data['subscription']['period'];
			$max_subscription_period_interval = $variation_data['subscription']['interval'];
		}
	}

	if ( sizeof( array_unique( $variations_data_prices_list ) ) > 1 ) {
		$subscription_details_identical = false;
	} elseif ( sizeof( array_unique( $variations_data_periods_list ) ) > 1 ) {
		$subscription_details_identical = false;
	} elseif ( sizeof( array_unique( $variations_data_intervals_list ) ) > 1 ) {
		$subscription_details_identical = false;
	} elseif ( sizeof( array_unique( $variations_data_lengths_list ) ) > 1 ) {
		$subscription_details_identical = false;
	} else {
		$subscription_details_identical = true;
	}

	return array(
		'min' => array(
			'variation_id'  => $min_variation_id,
			'price'         => $lowest_price,
			'regular_price' => $lowest_regular_price,
			'sale_price'    => $lowest_sale_price,
			'period'        => $variable_subscription_period,
			'interval'      => $variable_subscription_period_interval,
		),
		'max' => array(
			'variation_id'  => $max_variation_id,
			'price'         => $highest_price,
			'regular_price' => $highest_regular_price,
			'sale_price'    => $highest_sale_price,
			'period'        => $max_subscription_period,
			'interval'      => $max_subscription_period_interval,
		),
		'subscription' => array(
			'length'       => $variable_subscription_length,
		),
		'identical' => $subscription_details_identical,
	);
}