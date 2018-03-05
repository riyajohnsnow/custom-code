<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function hf_get_subscriptions_for_order( $order_id, $args = array() ) {

	if ( is_object( $order_id ) ) {
		$order_id = hf_get_objects_property( $order_id, 'id' );
	}

	$args = wp_parse_args( $args, array(
			'order_id'               => $order_id,
			'subscriptions_per_page' => -1,
			'order_type'             => array( 'parent', 'switch' ),
		)
	);

	if ( ! is_array( $args['order_type'] ) ) {
		$args['order_type'] = array( $args['order_type'] );
	}

	$subscriptions = array();
	$get_all       = ( in_array( 'any', $args['order_type'] ) ) ? true : false;

	if ( $order_id && in_array( 'parent', $args['order_type'] ) || $get_all ) {
		$subscriptions = hf_get_subscriptions( $args );
	}

	if ( hf_order_contains_resubscribe( $order_id ) && ( in_array( 'resubscribe', $args['order_type'] ) || $get_all ) ) {
		$subscriptions += hf_get_subscriptions_for_resubscribe_order( $order_id );
	}

	if ( hf_order_contains_renewal( $order_id ) && ( in_array( 'renewal', $args['order_type'] ) || $get_all ) ) {
		$subscriptions += hf_get_subscriptions_for_renewal_order( $order_id );
	}

	if ( hf_order_contains_switch( $order_id ) && ( in_array( 'switch', $args['order_type'] ) || $get_all ) ) {
		$subscriptions += hf_get_subscriptions_for_switch_order( $order_id );
	}

	return $subscriptions;
}

function hf_copy_order_address( $from_order, $to_order, $address_type = 'all' ) {

	if ( in_array( $address_type, array( 'shipping', 'all' ) ) ) {
		$to_order->set_address( array(
			'first_name' => hf_get_objects_property( $from_order, 'shipping_first_name' ),
			'last_name'  => hf_get_objects_property( $from_order, 'shipping_last_name' ),
			'company'    => hf_get_objects_property( $from_order, 'shipping_company' ),
			'address_1'  => hf_get_objects_property( $from_order, 'shipping_address_1' ),
			'address_2'  => hf_get_objects_property( $from_order, 'shipping_address_2' ),
			'city'       => hf_get_objects_property( $from_order, 'shipping_city' ),
			'state'      => hf_get_objects_property( $from_order, 'shipping_state' ),
			'postcode'   => hf_get_objects_property( $from_order, 'shipping_postcode' ),
			'country'    => hf_get_objects_property( $from_order, 'shipping_country' ),
		), 'shipping' );
	}

	if ( in_array( $address_type, array( 'billing', 'all' ) ) ) {
		$to_order->set_address( array(
			'first_name' => hf_get_objects_property( $from_order, 'billing_first_name' ),
			'last_name'  => hf_get_objects_property( $from_order, 'billing_last_name' ),
			'company'    => hf_get_objects_property( $from_order, 'billing_company' ),
			'address_1'  => hf_get_objects_property( $from_order, 'billing_address_1' ),
			'address_2'  => hf_get_objects_property( $from_order, 'billing_address_2' ),
			'city'       => hf_get_objects_property( $from_order, 'billing_city' ),
			'state'      => hf_get_objects_property( $from_order, 'billing_state' ),
			'postcode'   => hf_get_objects_property( $from_order, 'billing_postcode' ),
			'country'    => hf_get_objects_property( $from_order, 'billing_country' ),
			'email'      => hf_get_objects_property( $from_order, 'billing_email' ),
			'phone'      => hf_get_objects_property( $from_order, 'billing_phone' ),
		), 'billing' );
	}

	return apply_filters( 'hf_subscription_copy_order_address', $to_order, $from_order, $address_type );
}

function hf_copy_order_meta( $from_order, $to_order, $type = 'subscription' ) {
	global $wpdb;

	if ( ! is_a( $from_order, 'WC_Abstract_Order' ) || ! is_a( $to_order, 'WC_Abstract_Order' ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. Orders expected aren\'t orders.',  HF_Subscriptions::TEXT_DOMAIN ) );
	}

	if ( ! is_string( $type ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. Type of copy is not a string.', HF_Subscriptions::TEXT_DOMAIN ) );
	}

	if ( ! in_array( $type, array( 'subscription', 'renewal_order', 'resubscribe_order' ) ) ) {
		$type = 'copy_order';
	}

	$meta_query = $wpdb->prepare(
		"SELECT `meta_key`, `meta_value`
		 FROM {$wpdb->postmeta}
		 WHERE `post_id` = %d
		 AND `meta_key` NOT LIKE '_schedule_%%'
		 AND `meta_key` NOT IN (
			 '_paid_date',
			 '_date_paid',
			 '_completed_date',
			 '_date_completed',
			 '_order_key',
			 '_edit_lock',
			 '_wc_points_earned',
			 '_transaction_id',
			 '_billing_interval',
			 '_billing_period',
			 '_subscription_resubscribe',
			 '_subscription_renewal',
			 '_subscription_switch',
			 '_payment_method',
			 '_payment_method_title'
		 )",
		hf_get_objects_property( $from_order, 'id' )
	);

	if ( 'renewal_order' == $type ) {
		$meta_query .= " AND `meta_key` NOT LIKE '_download_permissions_granted' ";
	}

	
	$meta_query = apply_filters( 'hf_' . $type . '_meta_query', $meta_query, $to_order, $from_order );
	$meta       = $wpdb->get_results( $meta_query, 'ARRAY_A' );
	$meta       = apply_filters( 'hf_' . $type . '_meta', $meta, $to_order, $from_order );

	foreach ( $meta as $meta_item ) {
		hf_set_objects_property( $to_order, $meta_item['meta_key'], maybe_unserialize( $meta_item['meta_value'] ), 'save', '', 'omit_key_prefix' );
	}
}



function hf_create_order_from_subscription( $subscription, $type ) {

	$type = hf_validate_new_order_type( $type );

	if ( is_wp_error( $type ) ) {
		return $type;
	}

	global $wpdb;

	try {

		$wpdb->query( 'START TRANSACTION' );

		if ( ! is_object( $subscription ) ) {
			$subscription = hf_get_subscription( $subscription );
		}

		$new_order = wc_create_order( array(
			'customer_id'   => $subscription->get_user_id(),
			'customer_note' => $subscription->get_customer_note(),
		) );

		hf_copy_order_meta( $subscription, $new_order, $type );

		$items = apply_filters( 'hf_new_order_items', $subscription->get_items( array( 'line_item', 'fee', 'shipping', 'tax' ) ), $new_order, $subscription );
		$items = apply_filters( 'hf_' . $type . '_items', $items, $new_order, $subscription );

		foreach ( $items as $item_index => $item ) {

			$item_name = apply_filters( 'hf_new_order_item_name', $item['name'], $item, $subscription );
			$item_name = apply_filters( 'hf_' . $type . '_item_name', $item_name, $item, $subscription );

			$order_item_id = wc_add_order_item( hf_get_objects_property( $new_order, 'id' ), array(
				'order_item_name' => $item_name,
				'order_item_type' => $item['type'],
			) );

			if ( HF_Subscriptions::is_woocommerce_prior_to( '3.0' ) ) {
				foreach ( $item['item_meta'] as $meta_key => $meta_values ) {
					foreach ( $meta_values as $meta_value ) {
						wc_add_order_item_meta( $order_item_id, $meta_key, maybe_unserialize( $meta_value ) );
					}
				}
			} else {
				$order_item = $new_order->get_item( $order_item_id );

				hf_copy_order_item( $item, $order_item );
				$order_item->save();
			}

			if ( 'line_item' == $item['type'] && isset( $item['product_id'] ) ) {

				$product_id = hf_get_canonical_product_id( $item );
				$product    = wc_get_product( $product_id );

				if ( false !== $product ) {

					$args = array(
						'totals' => array(
							'subtotal'     => $item['line_subtotal'],
							'total'        => $item['line_total'],
							'subtotal_tax' => $item['line_subtotal_tax'],
							'tax'          => $item['line_tax'],
							'tax_data'     => maybe_unserialize( $item['line_tax_data'] ),
						),
					);

					if ( ! empty( $item['variation_id'] ) && null !== ( $variation_data = hf_get_objects_property( $product, 'variation_data' ) ) ) {
						foreach ( $variation_data as $attribute => $variation ) {
							if ( isset( $item[ str_replace( 'attribute_', '', $attribute ) ] ) ) {
								$args['variation'][ $attribute ] = $item[ str_replace( 'attribute_', '', $attribute ) ];
							}
						}
					}

					if ( isset( $order_item ) && is_callable( array( $order_item, 'set_backorder_meta' ) ) ) { // WC 3.0
						$order_item->set_backorder_meta();
						$order_item->save();
					} elseif ( $product->backorders_require_notification() && $product->is_on_backorder( $item['qty'] ) ) { // WC 2.6
						wc_add_order_item_meta( $order_item_id, apply_filters( 'woocommerce_backordered_item_meta_name', __( 'Backordered', HF_Subscriptions::TEXT_DOMAIN ) ), $item['qty'] - max( 0, $product->get_total_stock() ) );
					}

					if ( HF_Subscriptions::is_woocommerce_prior_to( '3.0' ) ) {
						do_action( 'woocommerce_order_add_product', hf_get_objects_property( $new_order, 'id' ), $order_item_id, $product, $item['qty'], $args );
					}
				}
			}
		}

		$wpdb->query( 'COMMIT' );

		return apply_filters( 'hf_new_order_created', $new_order, $subscription, $type );

	} catch ( Exception $e ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'new-order-error', $e->getMessage() );
	}
}


function hf_validate_new_order_type( $type ) {
	if ( ! is_string( $type ) ) {
		return new WP_Error( 'order_from_subscription_type_type', sprintf( __( '$type passed to the function was not a string.', HF_Subscriptions::TEXT_DOMAIN ), $type ) );

	}
	if ( ! in_array( $type, apply_filters( 'hf_new_order_types', array( 'renewal_order', 'resubscribe_order' ) ) ) ) {
		return new WP_Error( 'order_from_subscription_type', sprintf( __( '"%s" is not a valid new order type.', HF_Subscriptions::TEXT_DOMAIN ), $type ) );
	}
	return $type;
}

function hf_get_order_address( $order, $address_type = 'shipping' ) {
	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		return array();
	}

	if ( 'billing' == $address_type ) {
		$address = array(
			'first_name' => hf_get_objects_property( $order, 'billing_first_name' ),
			'last_name'  => hf_get_objects_property( $order, 'billing_last_name' ),
			'company'    => hf_get_objects_property( $order, 'billing_company' ),
			'address_1'  => hf_get_objects_property( $order, 'billing_address_1' ),
			'address_2'  => hf_get_objects_property( $order, 'billing_address_2' ),
			'city'       => hf_get_objects_property( $order, 'billing_city' ),
			'state'      => hf_get_objects_property( $order, 'billing_state' ),
			'postcode'   => hf_get_objects_property( $order, 'billing_postcode' ),
			'country'    => hf_get_objects_property( $order, 'billing_country' ),
			'email'      => hf_get_objects_property( $order, 'billing_email' ),
			'phone'      => hf_get_objects_property( $order, 'billing_phone' ),
		);
	} else {
		$address = array(
			'first_name' => hf_get_objects_property( $order, 'shipping_first_name' ),
			'last_name'  => hf_get_objects_property( $order, 'shipping_last_name' ),
			'company'    => hf_get_objects_property( $order, 'shipping_company' ),
			'address_1'  => hf_get_objects_property( $order, 'shipping_address_1' ),
			'address_2'  => hf_get_objects_property( $order, 'shipping_address_2' ),
			'city'       => hf_get_objects_property( $order, 'shipping_city' ),
			'state'      => hf_get_objects_property( $order, 'shipping_state' ),
			'postcode'   => hf_get_objects_property( $order, 'shipping_postcode' ),
			'country'    => hf_get_objects_property( $order, 'shipping_country' ),
		);
	}

	return apply_filters( 'hf_get_order_address', $address, $address_type, $order );
}

function hf_order_contains_subscription( $order, $order_type = array( 'parent', 'resubscribe', 'switch' ) ) {

	if ( ! is_array( $order_type ) ) {
		$order_type = array( $order_type );
	}

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}

	$contains_subscription = false;
	$get_all               = ( in_array( 'any', $order_type ) ) ? true : false;

	if ( ( in_array( 'parent', $order_type ) || $get_all ) && count( hf_get_subscriptions_for_order( hf_get_objects_property( $order, 'id' ), array( 'order_type' => 'parent' ) ) ) > 0 ) {
		$contains_subscription = true;

	} elseif ( ( in_array( 'renewal', $order_type ) || $get_all ) && hf_order_contains_renewal( $order ) ) {
		$contains_subscription = true;

	} elseif ( ( in_array( 'resubscribe', $order_type ) || $get_all ) && hf_order_contains_resubscribe( $order ) ) {
		$contains_subscription = true;

	} elseif ( ( in_array( 'switch', $order_type ) || $get_all )&& hf_order_contains_switch( $order ) ) {
		$contains_subscription = true;

	}

	return $contains_subscription;
}

function hf_get_subscription_orders( $return_fields = 'ids', $order_type = 'parent' ) {
	global $wpdb;

	if ( ! is_array( $order_type ) ) {
		$order_type = array( $order_type );
	}

	$any_order_type = in_array( 'any', $order_type ) ? true : false;
	$return_fields  = ( 'ids' == $return_fields ) ? $return_fields : 'all';

	$orders    = array();
	$order_ids = array();

	if ( $any_order_type || in_array( 'parent', $order_type ) ) {
		$order_ids = array_merge( $order_ids, $wpdb->get_col(
			"SELECT DISTINCT post_parent FROM {$wpdb->posts}
			 WHERE post_type = 'hf_shop_subscription'
			 AND post_parent <> 0"
		) );
	}

	if ( $any_order_type || in_array( 'renewal', $order_type ) || in_array( 'resubscribe', $order_type ) || in_array( 'switch', $order_type ) ) {

		$meta_query = array(
			'relation' => 'OR',
		);

		if ( $any_order_type || in_array( 'renewal', $order_type ) ) {
			$meta_query[] = array(
				'key'     => '_subscription_renewal',
				'compare' => 'EXISTS',
			);
		}

		if ( $any_order_type || in_array( 'switch', $order_type ) ) {
			$meta_query[] = array(
				'key'     => '_subscription_switch',
				'compare' => 'EXISTS',
			);
		}

		if ( in_array( 'resubscribe', $order_type ) && ! in_array( 'parent', $order_type ) ) {
			$meta_query[] = array(
				'key'     => '_subscription_resubscribe',
				'compare' => 'EXISTS',
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$order_ids = array_merge( $order_ids, get_posts( array(
				'posts_per_page' => -1,
				'post_type'      => 'shop_order',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'meta_query'     => $meta_query,
			) ) );
		}
	}

	if ( 'all' == $return_fields ) {
		foreach ( $order_ids as $order_id ) {
			$orders[ $order_id ] = wc_get_order( $order_id );
		}
	} else {
		foreach ( $order_ids as $order_id ) {
			$orders[ $order_id ] = $order_id;
		}
	}

	return apply_filters( 'hf_get_subscription_orders', $orders, $return_fields, $order_type );
}

function hf_get_order_item( $item_id, $order ) {

	$item = array();

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. No valid subscription / order was passed in.', HF_Subscriptions::TEXT_DOMAIN ), 422 );
	}

	if ( ! absint( $item_id ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. No valid item id was passed in.', HF_Subscriptions::TEXT_DOMAIN ), 422 );
	}

	foreach ( $order->get_items() as $line_item_id => $line_item ) {
		if ( $item_id == $line_item_id ) {
			$item = $line_item;
			break;
		}
	}

	return $item;
}

function hf_get_order_item_meta( $item, $product = null ) {

	return new WC_Order_Item_Meta( $item, $product );
}


function hf_get_order_item_name( $order_item, $include = array() ) {

	$include = wp_parse_args( $include, array(
		'attributes' => false,
	) );

	$order_item_name = $order_item['name'];

	if ( $include['attributes'] && ! empty( $order_item['item_meta'] ) ) {

		$attribute_strings = array();

		foreach ( $order_item['item_meta'] as $meta_key => $meta_value ) {

			$meta_value = $meta_value[0];

			// Skip hidden core fields
			if ( in_array( $meta_key, apply_filters( 'woocommerce_hidden_order_itemmeta', array(
				'_qty',
				'_tax_class',
				'_product_id',
				'_variation_id',
				'_line_subtotal',
				'_line_subtotal_tax',
				'_line_total',
				'_line_tax',
				'_switched_subscription_item_id',
			) ) ) ) {
				continue;
			}

			if ( is_serialized( $meta_value ) ) {
				continue;
			}

			if ( taxonomy_exists( wc_sanitize_taxonomy_name( $meta_key ) ) ) {
				$term       = get_term_by( 'slug', $meta_value, wc_sanitize_taxonomy_name( $meta_key ) );
				$meta_key   = wc_attribute_label( wc_sanitize_taxonomy_name( $meta_key ) );
				$meta_value = isset( $term->name ) ? $term->name : $meta_value;
			} else {
				$meta_key   = apply_filters( 'woocommerce_attribute_label', wc_attribute_label( $meta_key ), $meta_key );
			}

			$attribute_strings[] = sprintf( '%s: %s', wp_kses_post( rawurldecode( $meta_key ) ), wp_kses_post( rawurldecode( $meta_value ) ) );
		}

		$order_item_name = sprintf( '%s (%s)', $order_item_name, implode( ', ', $attribute_strings ) );
	}

	return apply_filters( 'hf_get_order_item_name', $order_item_name, $order_item, $include );
}

function hf_get_line_item_name( $line_item ) {

	$item_meta_strings = array();

	foreach ( $line_item['item_meta'] as $meta_key => $meta_value ) {

		$meta_value = $meta_value[0];

		if ( in_array( $meta_key, apply_filters( 'woocommerce_hidden_order_itemmeta', array(
			'_qty',
			'_tax_class',
			'_product_id',
			'_variation_id',
			'_line_subtotal',
			'_line_subtotal_tax',
			'_line_total',
			'_line_tax',
			'_line_tax_data',
		) ) ) ) {
			continue;
		}

		if ( is_serialized( $meta_value ) ) {
			continue;
		}

		if ( taxonomy_exists( wc_sanitize_taxonomy_name( $meta_key ) ) ) {
			$term       = get_term_by( 'slug', $meta_value, wc_sanitize_taxonomy_name( $meta_key ) );
			$meta_key   = wc_attribute_label( wc_sanitize_taxonomy_name( $meta_key ) );
			$meta_value = isset( $term->name ) ? $term->name : $meta_value;
		} else {
			$meta_key   = apply_filters( 'woocommerce_attribute_label', wc_attribute_label( $meta_key ), $meta_key );
		}

		$item_meta_strings[] = sprintf( '%s: %s', rawurldecode( $meta_key ), rawurldecode( $meta_value ) );
	}

	if ( ! empty( $item_meta_strings ) ) {
		$line_item_name = sprintf( '%s (%s)', $line_item['name'], implode( ', ', $item_meta_strings ) );
	} else {
		$line_item_name = $line_item['name'];
	}

	return apply_filters( 'hf_line_item_name', $line_item_name, $line_item );
}

function hf_display_item_meta( $item, $order ) {
	if ( function_exists( 'wc_display_item_meta' ) ) {
		wc_display_item_meta( $item );
	} else {
		$order->display_item_meta( $item );
	}
}

function hf_display_item_downloads( $item, $order ) {
	if ( function_exists( 'wc_display_item_downloads' ) ) {
		wc_display_item_downloads( $item );
	} else {
		$order->display_item_downloads( $item );
	}
}

function hf_copy_order_item( $from_item, &$to_item ) {

	foreach ( $from_item->get_meta_data() as $meta_data ) {
		$to_item->update_meta_data( $meta_data->key, $meta_data->value );
	}

	switch ( $from_item->get_type() ) {
		case 'line_item':
			$to_item->set_props( array(
				'product_id'   => $from_item->get_product_id(),
				'variation_id' => $from_item->get_variation_id(),
				'quantity'     => $from_item->get_quantity(),
				'tax_class'    => $from_item->get_tax_class(),
				'subtotal'     => $from_item->get_subtotal(),
				'total'        => $from_item->get_total(),
				'taxes'        => $from_item->get_taxes(),
			) );
			break;
		case 'shipping':
			$to_item->set_props( array(
				'method_id' => $from_item->get_method_id(),
				'total'     => $from_item->get_total(),
				'taxes'     => $from_item->get_taxes(),
			) );
			break;
		case 'tax':
			$to_item->set_props( array(
				'rate_id'            => $from_item->get_rate_id(),
				'label'              => $from_item->get_label(),
				'compound'           => $from_item->get_compound(),
				'tax_total'          => $from_item->get_tax_total(),
				'shipping_tax_total' => $from_item->get_shipping_tax_total(),
			) );
			break;
		case 'fee':
			$to_item->set_props( array(
				'tax_class'  => $from_item->get_tax_class(),
				'tax_status' => $from_item->get_tax_status(),
				'total'      => $from_item->get_total(),
				'taxes'      => $from_item->get_taxes(),
			) );
			break;
		case 'coupon':
			$to_item->set_props( array(
				'discount'     => $from_item->discount(),
				'discount_tax' => $from_item->discount_tax(),
			) );
			break;
	}
}