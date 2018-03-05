<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'includes/populate-select2.php' );
require_once( 'includes/compatibility-functions.php' );
require_once( 'includes/price-functions.php' );
require_once( 'includes/subscription-cart-functions.php' );
require_once( 'includes/order-functions.php' );
require_once( 'includes/date-time-functions.php' );
require_once( 'includes/user-functions.php' );
require_once( 'includes/helper-functions.php' );
require_once( 'includes/renewal-functions.php' );
require_once( 'includes/resubscribe-functions.php' );

if ( is_admin() ) {
	    
        function hf_add_admin_notice( $message, $notice_type = 'success' ) {

                $notices = get_transient( '_hf_admin_notices' );
                if ( false === $notices ) {
                        $notices = array();
                }
                $notices[ $notice_type ][] = $message;
                set_transient( '_hf_admin_notices', $notices, 60 * 60 );
        }

        function hf_display_admin_notices( $clear = true ) {

                $notices = get_transient( '_hf_admin_notices' );
                if ( false !== $notices && ! empty( $notices ) ) {
                        if ( ! empty( $notices['success'] ) ) {
                                array_walk( $notices['success'], 'esc_html' );
                                echo '<div id="moderated" class="updated"><p>' . wp_kses_post( implode( "</p>\n<p>", $notices['success'] ) ) . '</p></div>';
                        }

                        if ( ! empty( $notices['error'] ) ) {
                                array_walk( $notices['error'], 'esc_html' );
                                echo '<div id="moderated" class="error"><p>' . wp_kses_post( implode( "</p>\n<p>", $notices['error'] ) ) . '</p></div>';
                        }
                }

                if ( false !== $clear ) {
                        hf_clear_admin_notices();
                }
        }
        add_action( 'admin_notices', 'hf_display_admin_notices' );

        
        function hf_clear_admin_notices() {
                delete_transient( '_hf_admin_notices' );
        }    
    
}


function hf_is_order_received_page() {
	return ( false !== strpos( $_SERVER['REQUEST_URI'], 'order-received' ) );
}

function hf_is_subscription( $subscription ) {

	if ( is_object( $subscription ) && is_a( $subscription, 'HF_Subscription' ) ) {
		$is_subscription = true;
	} elseif ( is_numeric( $subscription ) && 'hf_shop_subscription' == get_post_type( $subscription ) ) {
		$is_subscription = true;
	} else {
		$is_subscription = false;
	}

	return apply_filters( 'hf_is_subscription', $is_subscription, $subscription );
}


 
function hf_check_subscriptions_exist() {
	global $wpdb;
	$sql = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1;", 'hf_shop_subscription' );
	$num_rows_found = $wpdb->query( $sql );
	return ( 0 !== $num_rows_found ) ? true: false;
}

function hf_get_subscription( $the_subscription ) {
	//print_r($the_subscription);
	if ( is_object( $the_subscription ) && hf_is_subscription( $the_subscription ) ) {
		$the_subscription = $the_subscription->get_id();
	}
        
	$subscription = WC()->order_factory->get_order( $the_subscription );

	if ( ! hf_is_subscription( $subscription ) ) {
		$subscription = false;
	}

	return apply_filters( 'hf_get_subscription', $subscription );
}


function hf_create_subscription( $args = array() ) {

	$order = ( isset( $args['order_id'] ) ) ? wc_get_order( $args['order_id'] ) : null;

	if ( ! empty( $order ) ) {
		$default_start_date  = hf_get_datetime_utc_string( hf_get_objects_property( $order, 'date_created' ) );
	} else {
		$default_start_date = gmdate( 'Y-m-d H:i:s' );
	}

	$default_args = array(
		'status'             => '',
		'order_id'           => 0,
		'customer_note'      => null,
		'customer_id'        => ( ! empty( $order ) ) ? $order->get_user_id() : null,
		'start_date'         => $default_start_date,
		'created_via'        => ( ! empty( $order ) ) ? hf_get_objects_property( $order, 'created_via' ) : '',
		'order_version'      => ( ! empty( $order ) ) ? hf_get_objects_property( $order, 'version' ) : WC_VERSION,
		'currency'           => ( ! empty( $order ) ) ? hf_get_objects_property( $order, 'currency' ) : get_woocommerce_currency(),
		'prices_include_tax' => ( ! empty( $order ) ) ? ( ( hf_get_objects_property( $order, 'prices_include_tax' ) ) ? 'yes' : 'no' ) : get_option( 'woocommerce_prices_include_tax' ),
	);

	$args              = wp_parse_args( $args, $default_args );
	$subscription_data = array();

	if ( ! is_string( $args['start_date'] ) || false === hf_is_datetime_mysql_format( $args['start_date'] ) ) {
		return new WP_Error( 'hf_subscription_invalid_start_date_format', __( 'Invalid date. The date must be a string and of the format: "Y-m-d H:i:s".', HF_Subscriptions::TEXT_DOMAIN ) );
	} else if ( hf_date_to_time( $args['start_date'] ) > current_time( 'timestamp', true ) ) {
		return new WP_Error( 'hf_subscription_invalid_start_date', __( 'Subscription start date must be before current day.', HF_Subscriptions::TEXT_DOMAIN ) );
	}

	if ( empty( $args['customer_id'] ) || ! is_numeric( $args['customer_id'] ) || $args['customer_id'] <= 0 ) {
		return new WP_Error( 'hf_subscription_invalid_customer_id', __( 'Invalid subscription customer_id.', HF_Subscriptions::TEXT_DOMAIN ) );
	}

	if ( empty( $args['billing_period'] ) || ! in_array( strtolower( $args['billing_period'] ), array_keys( hf_get_subscription_period_strings() ) ) ) {
		return new WP_Error( 'hf_subscription_invalid_billing_period', __( 'Invalid subscription billing period given.', HF_Subscriptions::TEXT_DOMAIN ) );
	}

	if ( empty( $args['billing_interval'] ) || ! is_numeric( $args['billing_interval'] ) || absint( $args['billing_interval'] ) <= 0 ) {
		return new WP_Error( 'hf_subscription_invalid_billing_interval', __( 'Invalid subscription billing interval given. Must be an integer greater than 0.', HF_Subscriptions::TEXT_DOMAIN ) );
	}

	$subscription_data['post_type']     = 'hf_shop_subscription';
	$subscription_data['post_status']   = 'wc-' . apply_filters( 'woocommerce_default_subscription_status', 'pending' );
	$subscription_data['ping_status']   = 'closed';
	$subscription_data['post_author']   = 1;
	$subscription_data['post_password'] = uniqid( 'order_' );
	$post_title_date = strftime( __( '%b %d, %Y @ %I:%M %p', HF_Subscriptions::TEXT_DOMAIN ) );
	$subscription_data['post_title']    = sprintf( __( 'Subscription &ndash; %s', HF_Subscriptions::TEXT_DOMAIN ), $post_title_date );
	$subscription_data['post_date_gmt'] = $args['start_date'];
	$subscription_data['post_date']     = get_date_from_gmt( $args['start_date'] );

	if ( $args['order_id'] > 0 ) {
		$subscription_data['post_parent'] = absint( $args['order_id'] );
	}

	if ( ! is_null( $args['customer_note'] ) && ! empty( $args['customer_note'] ) ) {
		$subscription_data['post_excerpt'] = $args['customer_note'];
	}

	if ( $args['status'] ) {
		if ( ! in_array( 'wc-' . $args['status'], array_keys( hf_get_subscription_statuses() ) ) ) {
			return new WP_Error( 'woocommerce_invalid_subscription_status', __( 'Invalid subscription status given.', HF_Subscriptions::TEXT_DOMAIN ) );
		}
		$subscription_data['post_status']  = 'wc-' . $args['status'];
	}

	$subscription_id = wp_insert_post( apply_filters( 'woocommerce_new_subscription_data', $subscription_data, $args ), true );

	if ( is_wp_error( $subscription_id ) ) {
		return $subscription_id;
	}

	update_post_meta( $subscription_id, '_order_key', 'wc_' . apply_filters( 'woocommerce_generate_order_key', uniqid( 'order_' ) ) );
	update_post_meta( $subscription_id, '_order_currency', $args['currency'] );
	update_post_meta( $subscription_id, '_prices_include_tax', $args['prices_include_tax'] );
	update_post_meta( $subscription_id, '_created_via', sanitize_text_field( $args['created_via'] ) );

	update_post_meta( $subscription_id, '_billing_period', $args['billing_period'] );
	update_post_meta( $subscription_id, '_billing_interval', absint( $args['billing_interval'] ) );
	update_post_meta( $subscription_id, '_customer_user', $args['customer_id'] );
	update_post_meta( $subscription_id, '_order_version', $args['order_version'] );

	return hf_get_subscription( $subscription_id );
}

function hf_get_subscription_statuses() {

	$subscription_statuses = array(
		'wc-pending'        => __( 'Pending', HF_Subscriptions::TEXT_DOMAIN ),
		'wc-active'         => __( 'Active', HF_Subscriptions::TEXT_DOMAIN ),
		'wc-on-hold'        => __( 'On hold', HF_Subscriptions::TEXT_DOMAIN ),
		'wc-cancelled'      => __( 'Cancelled', HF_Subscriptions::TEXT_DOMAIN ),
		'wc-switched'       => __( 'Switched', HF_Subscriptions::TEXT_DOMAIN ),
		'wc-expired'        => __( 'Expired', HF_Subscriptions::TEXT_DOMAIN ),
		'wc-pending-cancel' => __( 'Pending Cancellation', HF_Subscriptions::TEXT_DOMAIN ),
	);

	return apply_filters( 'hf_subscription_statuses', $subscription_statuses );
}

function hf_get_subscription_status_name( $status ) {

	if ( ! is_string( $status ) ) {
		return new WP_Error( 'hf_subscription_wrong_status_format', __( 'Can not get status name. Status is not a string.', HF_Subscriptions::TEXT_DOMAIN ) );
	}

	$statuses = hf_get_subscription_statuses();
	$sanitized_status_key = hf_sanitize_subscription_status_key( $status );
	$status_name   = isset( $statuses[ $sanitized_status_key ] ) ? $statuses[ $sanitized_status_key ] : $status;
	return apply_filters( 'hf_subscription_status_name', $status_name, $status );
}

function hf_get_address_type_to_display( $address_type ) {
    
	if ( ! is_string( $address_type ) ) {
		return new WP_Error( 'hf_subscription_wrong_address_type_format', __( 'Can not get address type display name. Address type is not a string.', HF_Subscriptions::TEXT_DOMAIN ) );
	}

	$address_types = apply_filters( 'hf_subscription_address_types', array(
		'shipping' => __( 'Shipping Address', HF_Subscriptions::TEXT_DOMAIN ),
		'billing' => __( 'Billing Address', HF_Subscriptions::TEXT_DOMAIN ),
	) );

	$address_type_display = isset( $address_types[ $address_type ] ) ? $address_types[ $address_type ] : $address_type;
	return apply_filters( 'hf_subscription_address_type_display', $address_type_display, $address_type );
}

function hf_get_subscription_available_date_types() {

	$dates = array(
		'start'        => __( 'Start Date', HF_Subscriptions::TEXT_DOMAIN ),
		'next_payment' => __( 'Next Payment', HF_Subscriptions::TEXT_DOMAIN ),
		'last_payment' => __( 'Last Order Date', HF_Subscriptions::TEXT_DOMAIN ),
		'cancelled'    => __( 'Cancelled Date', HF_Subscriptions::TEXT_DOMAIN ),
		'end'          => __( 'End Date', HF_Subscriptions::TEXT_DOMAIN ),
	);
	return apply_filters( 'hf_subscription_available_dates', $dates );
}

function hf_display_date_type( $date_type, $subscription ) {

	if ( 'last_payment' === $date_type ) {
		$display_date_type = false;
	} elseif ( 'cancelled' === $date_type && 0 == $subscription->get_date( $date_type ) ) {
		$display_date_type = false;
	} else {
		$display_date_type = true;
	}
	return apply_filters( 'hf_display_date_type', $display_date_type, $date_type, $subscription );
}

function hf_get_date_meta_key( $date_type ) {
    
	if ( ! is_string( $date_type ) ) {
		return new WP_Error( 'hf_subscription_wrong_date_type_format', __( 'Date type is not a string.', HF_Subscriptions::TEXT_DOMAIN ) );
	} elseif ( empty( $date_type ) ) {
		return new WP_Error( 'hf_subscription_wrong_date_type_format', __( 'Date type can not be an empty string.', HF_Subscriptions::TEXT_DOMAIN ) );
	}
	return apply_filters( 'hf_subscription_date_meta_key_prefix', sprintf( '_schedule_%s', $date_type ), $date_type );
}

function hf_normalise_date_type_key( $date_type_key, $display_deprecated_notice = false ) {

	$prefix_length = strlen( 'schedule_' );
	if ( 'schedule_' === substr( $date_type_key, 0, $prefix_length ) ) {
		$date_type_key = substr( $date_type_key, $prefix_length );
	}

	$suffix_length = strlen( '_date' );
	if ( '_date' === substr( $date_type_key, -$suffix_length ) ) {
		$date_type_key = substr( $date_type_key, 0, -$suffix_length );
	}

	$deprecated_notice = '';

	if ( 'start' === $date_type_key ) {
		$deprecated_notice = 'The "start" date type parameter has been deprecated to align date types with improvements to date APIs in WooCommerce 3.0, specifically the introduction of a new "date_created" API. Use "date_created"';
		$date_type_key     = 'date_created';
	} elseif ( 'last_payment' === $date_type_key ) {
		$deprecated_notice = 'The "last_payment" date type parameter has been deprecated due to ambiguity (it actually returns the date created for the last order) and to align date types with improvements to date APIs in WooCommerce 3.0, specifically the introduction of a new "date_paid" API. Use "last_order_date_created" or "last_order_date_paid"';
		$date_type_key = 'last_order_date_created';
	}

	if ( true === $display_deprecated_notice && ! empty( $deprecated_notice ) ) {
		hf_deprecated_argument( esc_attr( hf_get_calling_function_name() ), '2.2.0', $deprecated_notice );
	}

	return $date_type_key;
}

function hf_get_calling_function_name() {

	$backtrace         = version_compare( phpversion(), '5.4.0', '>=' ) ? debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ) : debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	$calling_function  = isset( $backtrace[2]['class'] ) ? $backtrace[2]['class'] : '';
	$calling_function .= isset( $backtrace[2]['type'] ) ? ( ( '->' == $backtrace[2]['type'] ) ? '::' : $backtrace[2]['type'] ) : '';
	$calling_function .= isset( $backtrace[2]['function'] ) ? $backtrace[2]['function'] : '';
	return $calling_function;
}

function hf_sanitize_subscription_status_key( $status_key ) {
	if ( ! is_string( $status_key ) || empty( $status_key ) ) {
		return '';
	}
	$status_key = ( 'wc-' === substr( $status_key, 0, 3 ) ) ? $status_key : sprintf( 'wc-%s', $status_key );
	return $status_key;
}

function hf_get_subscriptions( $args ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
			'subscriptions_per_page' => 10,
			'paged'                  => 1,
			'offset'                 => 0,
			'orderby'                => 'start_date',
			'order'                  => 'DESC',
			'customer_id'            => 0,
			'product_id'             => 0,
			'variation_id'           => 0,
			'order_id'               => 0,
			'subscription_status'    => 'any',
			'meta_query_relation'    => 'AND',
		)
	);

	if ( 0 !== $args['order_id'] && 'shop_order' !== get_post_type( $args['order_id'] ) ) {
		return array();
	}

	if ( ! in_array( $args['subscription_status'], array( 'any', 'trash' ) ) ) {
		$args['subscription_status'] = hf_sanitize_subscription_status_key( $args['subscription_status'] );
	}

	$query_args = array(
		'post_type'      => 'hf_shop_subscription',
		'post_status'    => $args['subscription_status'],
		'posts_per_page' => $args['subscriptions_per_page'],
		'paged'          => $args['paged'],
		'offset'         => $args['offset'],
		'order'          => $args['order'],
		'fields'         => 'ids',
		'meta_query'     => array(),
	);

	if ( 0 != $args['order_id'] && is_numeric( $args['order_id'] ) ) {
		$query_args['post_parent'] = $args['order_id'];
	}

	switch ( $args['orderby'] ) {
		case 'status' :
			$query_args['orderby'] = 'post_status';
			break;
		case 'start_date' :
			$query_args['orderby'] = 'date';
			break;
		case 'end_date' :
			$query_args = array_merge( $query_args, array(
				'orderby'   => 'meta_value',
				'meta_key'  => hf_get_date_meta_key( $args['orderby'] ),
				'meta_type' => 'DATETIME',
			) );
			$query_args['meta_query'][] = array(
				'key'     => hf_get_date_meta_key( $args['orderby'] ),
				'value'   => 'EXISTS',
				'type'    => 'DATETIME',
			);
			break;
		default :
			$query_args['orderby'] = $args['orderby'];
			break;
	}

	if ( 0 != $args['customer_id'] && is_numeric( $args['customer_id'] ) ) {
		$query_args['meta_query'][] = array(
			'key'     => '_customer_user',
			'value'   => $args['customer_id'],
			'type'    => 'numeric',
			'compare' => ( is_array( $args['customer_id'] ) ) ? 'IN' : '=',
		);
	};

	if ( ( 0 != $args['product_id'] && is_numeric( $args['product_id'] ) ) || ( 0 != $args['variation_id'] && is_numeric( $args['variation_id'] ) ) ) {
		$query_args['post__in'] = hf_get_subscriptions_for_product( array( $args['product_id'], $args['variation_id'] ) );
	}

	if ( ! empty( $query_args['meta_query'] ) ) {
		$query_args['meta_query']['relation'] = $args['meta_query_relation'];
	}

	$query_args = apply_filters( 'hf_get_subscription_query_args', $query_args, $args );

	$subscription_post_ids = get_posts( $query_args );

	$subscriptions = array();

	foreach ( $subscription_post_ids as $post_id ) {
		$subscriptions[ $post_id ] = hf_get_subscription( $post_id );
	}
	return apply_filters( 'woocommerce_got_subscriptions', $subscriptions, $args );
}

function hf_get_subscriptions_for_product( $product_ids, $fields = 'ids' ) {
    
	global $wpdb;

	if ( is_array( $product_ids ) ) {
		$ids_for_query = implode( "', '", array_map( 'absint', array_unique( $product_ids ) ) );
	} else {
		$ids_for_query = absint( $product_ids );
	}

	$subscription_ids = $wpdb->get_col( "
		SELECT DISTINCT order_items.order_id FROM {$wpdb->prefix}woocommerce_order_items as order_items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta ON order_items.order_item_id = itemmeta.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
		WHERE posts.post_type = 'hf_shop_subscription'
			AND itemmeta.meta_value IN ( '" . $ids_for_query . "' )
			AND itemmeta.meta_key   IN ( '_variation_id', '_product_id' )"
	);

	$subscriptions = array();

	foreach ( $subscription_ids as $post_id ) {
		$subscriptions[ $post_id ] = ( 'ids' !== $fields ) ? hf_get_subscription( $post_id ) : $post_id;
	}
	return apply_filters( 'hf_subscription_for_product', $subscriptions, $product_ids, $fields );
}

function hf_get_line_items_with_a_trial( $subscription_id ) {

	$subscription = ( is_object( $subscription_id ) ) ? $subscription_id : hf_get_subscription( $subscription_id );
	$trial_items  = array();

	foreach ( $subscription->get_items() as $line_item_id => $line_item ) {

		if ( isset( $line_item['has_trial'] ) ) {
			$trial_items[ $line_item_id ] = $line_item;
		}
	}
	return apply_filters( 'hf_subscription_trial_line_items', $trial_items, $subscription_id );
}


function hf_can_items_be_removed( $subscription ) {
	$allow_remove = false;

	if ( sizeof( $subscription->get_items() ) > 1 && $subscription->payment_method_supports( 'subscription_amount_changes' ) && $subscription->has_status( array( 'active', 'on-hold', 'pending' ) ) ) {
		$allow_remove = true;
	}
	return apply_filters( 'hf_can_items_be_removed', $allow_remove, $subscription );
}


function hf_get_order_items_product_id( $item_id ) {
    
	global $wpdb;

	$product_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta
		 WHERE order_item_id = %d
		 AND meta_key = '_product_id'",
		$item_id
	) );
	return $product_id;
}

function hf_get_canonical_product_id( $item_or_product ) {

	if ( is_a( $item_or_product, 'WC_Product' ) ) {
		$product_id = $item_or_product->get_id();
	} elseif ( is_a( $item_or_product, 'WC_Order_Item' ) ) { 
		$product_id = ( $item_or_product->get_variation_id() ) ? $item_or_product->get_variation_id() : $item_or_product->get_product_id();
	} else { 
		$product_id = ( ! empty( $item_or_product['variation_id'] ) ) ? $item_or_product['variation_id'] : $item_or_product['product_id'];
	}
	return $product_id;
}

function hf_get_subscription_ended_statuses() {
	return apply_filters( 'hf_subscription_ended_statuses', array( 'cancelled', 'trash', 'expired', 'switched', 'pending-cancel' ) );
}

function hf_is_view_subscription_page() {
    
	global $wp;
	return ( is_page( wc_get_page_id( 'myaccount' ) ) && isset( $wp->query_vars['view-subscription'] ) ) ? true : false;
}

function hf_help_tip( $tip, $allow_html = false ) {

	if ( function_exists( 'wc_help_tip' ) ) {

		$help_tip = wc_help_tip( $tip, $allow_html );

	} else {

		if ( $allow_html ) {
			$tip = wc_sanitize_tooltip( $tip );
		} else {
			$tip = esc_attr( $tip );
		}

		$help_tip = sprintf( '<img class="help_tip" data-tip="%s" src="%s/assets/images/help.png" height="16" width="16" />', $tip, esc_url( WC()->plugin_url() ) );
	}

	return $help_tip;
}

function hf_get_objects_property( $object, $property, $single = 'single', $default = null ) {

	$prefixed_key = hf_maybe_prefix_key( $property );
	$value = ! is_null( $default ) ? $default : ( ( 'single' == $single ) ? null : array() );
	switch ( $property ) {

		case 'name' :
			if ( HF_Subscriptions::is_woocommerce_prior_to( '3.0' ) ) {
				$value = $object->post->post_title;
			} else { 
				$value = $object->get_name();
			}
			break;

		case 'post' :
			if ( HF_Subscriptions::is_woocommerce_prior_to( '3.0' ) ) {
				$value = $object->post;
			} else { 
				if ( method_exists( $object, 'is_type' ) && $object->is_type( 'variation' ) ) {
					$value = get_post( $object->get_parent_id() );
				} else {
					$value = get_post( $object->get_id() );
				}
			}
			break;

		case 'post_status' :
			$value = hf_get_objects_property( $object, 'post' )->post_status;
			break;

		case 'parent_id' :
			if ( method_exists( $object, 'get_parent_id' ) ) { 
				$value = $object->get_parent_id();
			} else { 
				$value = $object->get_parent();
			}
			break;

		case 'variation_data' :
			if ( function_exists( 'wc_get_product_variation_attributes' ) ) { 
				$value = wc_get_product_variation_attributes( $object->get_id() );
			} else {
				$value = $object->$property;
			}
			break;

		case 'downloads' :
			if ( method_exists( $object, 'get_downloads' ) ) { 
				$value = $object->get_downloads();
			} else {
				$value = $object->get_files();
			}
			break;

		case 'order_version' :
		case 'version' :
			if ( method_exists( $object, 'get_version' ) ) {
				$value = $object->get_version();
			} else { 
				$value = $object->order_version;
			}
			break;

		case 'order_currency' :
		case 'currency' :
			if ( method_exists( $object, 'get_currency' ) ) {
				$value = $object->get_currency();
			} else { 
				$value = $object->get_order_currency();
			}
			break;

		case 'date_created' :
		case 'order_date' :
		case 'date' :
			if ( method_exists( $object, 'get_date_created' ) ) {
				$value = $object->get_date_created();
			} else {
				if ( '0000-00-00 00:00:00' != $object->post->post_date_gmt ) {
					$value = new WC_DateTime( $object->post->post_date_gmt, new DateTimeZone( 'UTC' ) );
					$value->setTimezone( new DateTimeZone( wc_timezone_string() ) );
				} else {
					$value = new WC_DateTime( $object->post->post_date, new DateTimeZone( wc_timezone_string() ) );
				}
			}
			break;

		case 'date_paid' :
			if ( method_exists( $object, 'get_date_paid' ) ) {
				$value = $object->get_date_paid();
			} else {
				if ( ! empty( $object->paid_date ) ) {
					$value = new WC_DateTime( $object->paid_date, new DateTimeZone( wc_timezone_string() ) );
				} else {
					$value = null;
				}
			}
			break;

		case 'cart_discount' :
			if ( method_exists( $object, 'get_total_discount' ) ) { 
				$value = $object->get_total_discount();
			} else { 
				$value = $object->cart_discount;
			}
			break;

		default :

			$function_name = 'get_' . $property;

			if ( is_callable( array( $object, $function_name ) ) ) {
				$value = $object->$function_name();
			} else {

				if ( method_exists( $object, 'get_meta' ) ) {
					if ( $object->meta_exists( $prefixed_key ) ) {
						if ( 'single' === $single ) {
							$value = $object->get_meta( $prefixed_key, true );
						} else {
							$value = wp_list_pluck( $object->get_meta( $prefixed_key, false ), 'value' );
						}
					}
				} elseif ( 'single' === $single && isset( $object->$property ) ) {
					$value = $object->$property;
				} elseif ( strtolower( $property ) !== 'id' && metadata_exists( 'post', hf_get_objects_property( $object, 'id' ), $prefixed_key ) ) {
					if ( 'single' === $single ) {
						$value = get_post_meta( hf_get_objects_property( $object, 'id' ), $prefixed_key, true );
					} else {
						$value = get_post_meta( hf_get_objects_property( $object, 'id' ), $prefixed_key, false );
					}
				}
			}
			break;
	}

	return $value;
}

function hf_set_objects_property( &$object, $key, $value, $save = 'save', $meta_id = '', $prefix_meta_key = 'prefix_meta_key' ) {

	$prefixed_key = hf_maybe_prefix_key( $key );

	if ( in_array( $prefixed_key, array( '_shipping_address_index', '_billing_address_index' ) ) ) {
		return;
	}

	$meta_setters_map = array(
		'_cart_discount'         => 'set_discount_total',
		'_cart_discount_tax'     => 'set_discount_tax',
		'_customer_user'         => 'set_customer_id',
		'_order_tax'             => 'set_cart_tax',
		'_order_shipping'        => 'set_shipping_total',
		'_sale_price_dates_from' => 'set_date_on_sale_from',
		'_sale_price_dates_to'   => 'set_date_on_sale_to',
	);

	if ( isset( $meta_setters_map[ $prefixed_key ] ) && is_callable( array( $object, $meta_setters_map[ $prefixed_key ] ) ) ) {
		$function = $meta_setters_map[ $prefixed_key ];
		$object->$function( $value );

	} elseif ( is_callable( array( $object, 'set' . $prefixed_key ) ) ) {

		if ( '_prices_include_tax' === $prefixed_key && ! is_bool( $value ) ) {
			$value = 'yes' === $value ? true : false;
		}

		$object->{ "set$prefixed_key" }( $value );

	} elseif ( is_callable( array( $object, 'set' . str_replace( '_order', '', $prefixed_key ) ) ) ) {
		$function_name = 'set' . str_replace( '_order', '', $prefixed_key );
		$object->$function_name( $value );

	} elseif ( is_callable( array( $object, 'update_meta_data' ) ) ) {
		$meta_key = ( 'prefix_meta_key' === $prefix_meta_key ) ? $prefixed_key : $key;
		$object->update_meta_data( $meta_key, $value, $meta_id );

	} elseif ( 'name' === $key ) {
		$object->post->post_title = $value;

	} else {
		$object->$key = $value;
	}

	if ( 'save' === $save ) {
		if ( is_callable( array( $object, 'save' ) ) ) { 
			$object->save();
		} elseif ( 'date_created' == $key ) { 
			wp_update_post( array( 'ID' => hf_get_objects_property( $object, 'id' ), 'post_date' => get_date_from_gmt( $value ), 'post_date_gmt' => $value ) );
		} elseif ( 'name' === $key ) { 
			wp_update_post( array( 'ID' => hf_get_objects_property( $object, 'id' ), 'post_title' => $value ) );
		} else {
			$meta_key = ( 'prefix_meta_key' === $prefix_meta_key ) ? $prefixed_key : $key;

			if ( ! empty( $meta_id ) ) {
				update_metadata_by_mid( 'post', $meta_id, $value, $meta_key );
			} else {
				update_post_meta( hf_get_objects_property( $object, 'id' ), $meta_key, $value );
			}
		}
	}
}

function hf_delete_objects_property( &$object, $key, $save = 'save', $meta_id = '' ) {

	$prefixed_key = hf_maybe_prefix_key( $key );

	if ( ! empty( $meta_id ) && method_exists( $object, 'delete_meta_data_by_mid' ) ) {
		$object->delete_meta_data_by_mid( $meta_id );
	} elseif ( method_exists( $object, 'delete_meta_data' ) ) {
		$object->delete_meta_data( $prefixed_key );
	} elseif ( isset( $object->$key ) ) {
		unset( $object->$key );
	}

	
	if ( 'save' === $save ) {
		if ( method_exists( $object, 'save' ) ) {
			$object->save();
		} elseif ( ! empty( $meta_id ) ) {
			delete_metadata_by_mid( 'post', $meta_id );
		} else {
			delete_post_meta( hf_get_objects_property( $object, 'id' ), $prefixed_key );
		}
	}
}

function hf_is_order( $order ) {

	if ( method_exists( $order, 'get_type' ) ) {
		$is_order = ( 'shop_order' === $order->get_type() );
	} else {
		$is_order = ( isset( $order->order_type ) && 'simple' === $order->order_type );
	}

	return $is_order;
}

function hf_product_deprecated_property_handler( $property, $product ) {

	$message_prefix = 'Product properties should not be accessed directly with WooCommerce 3.0+.';
	$function_name  = 'get_' . str_replace( 'subscription_', '', str_replace( 'subscription_period_', '', $property ) );
	$class_name     = get_class( $product );
	$value          = null;

	if ( in_array( $property, array( 'product_type', 'parent_product_type' ) ) || ( is_callable( array( 'HF_Subscriptions_Product', $function_name ) ) && false !== strpos( $property, 'subscription' ) ) ) {

		switch ( $property ) {
			case 'product_type':
				$value       = $product->get_type();
				$alternative = $class_name . '::get_type()';
				break;

			case 'parent_product_type':
				if ( $product->is_type( 'subscription_variation' ) ) {
					$value       = 'variation';
					$alternative = 'WC_Product_Variation::get_type()';
				} else {
					$value       = 'variable';
					$alternative = 'WC_Product_Variable::get_type()';
				}
				break;


			case 'max_variation_period':
			case 'max_variation_period_interval':
				$meta_key = '_' . $property;
				if ( '' === $product->get_meta( $meta_key ) ) {
					WC_Product_Variable::sync( $product->get_id() );
				}
				$value       = $product->get_meta( $meta_key );
				$alternative = $class_name . '::get_meta( ' . $meta_key . ' ) or hf_get_min_max_variation_data( $product )';
				break;

			default:
				$value       = call_user_func( array( 'HF_Subscriptions_Product', $function_name ), $product );
				$alternative = sprintf( 'HF_Subscriptions_Product::%s( $product )', $function_name );
				break;
		}

		hf_deprecated_argument( $class_name . '::$' . $property, '2.2.0', sprintf( '%s Use %s', $message_prefix, $alternative ) );
	}

	return $value;
}

function hf_get_coupon_property( $coupon, $property ) {

	$value = '';

	if ( HF_Subscriptions::is_woocommerce_prior_to( '3.0' ) ) {
		$value = $coupon->$property;
	} else {
		$property_to_getter_map = array(
			'type'                       => 'get_discount_type',
			'exclude_product_ids'        => 'get_excluded_product_ids',
			'expiry_date'                => 'get_date_expires',
			'exclude_product_categories' => 'get_excluded_product_categories',
			'customer_email'             => 'get_email_restrictions',
			'enable_free_shipping'       => 'get_free_shipping',
			'coupon_amount'              => 'get_amount',
		);

		switch ( true ) {
			case 'exists' == $property:
				$value = ( $coupon->get_id() > 0 ) ? true : false;
				break;
			case isset( $property_to_getter_map[ $property ] ) && is_callable( array( $coupon, $property_to_getter_map[ $property ] ) ):
				$function = $property_to_getter_map[ $property ];
				$value    = $coupon->$function();
				break;
			case is_callable( array( $coupon, 'get_' . $property ) ):
				$value = $coupon->{ "get_$property" }();
				break;
		}
	}

	return $value;
}

function hf_set_coupon_property( &$coupon, $property, $value ) {

	if ( HF_Subscriptions::is_woocommerce_prior_to( '3.0' ) ) {
		$coupon->$property = $value;
	} else {
		$property_to_setter_map = array(
			'type'                       => 'set_discount_type',
			'exclude_product_ids'        => 'set_excluded_product_ids',
			'expiry_date'                => 'set_date_expires',
			'exclude_product_categories' => 'set_excluded_product_categories',
			'customer_email'             => 'set_email_restrictions',
			'enable_free_shipping'       => 'set_free_shipping',
			'coupon_amount'              => 'set_amount',
		);

		switch ( true ) {
			case 'individual_use' == $property:
				if ( ! is_bool( $value ) ) {
					$value = ( 'yes' === $value ) ? true : false;
				}

				$coupon->set_individual_use( $value );
				break;
			case isset( $property_to_setter_map[ $property ] ) && is_callable( array( $coupon, $property_to_setter_map[ $property ] ) ):
				$function = $property_to_setter_map[ $property ];
				$coupon->$function( $value );

				break;
			case is_callable( array( $coupon, 'set_' . $property ) ):
				$coupon->{ "set_$property" }( $value );
				break;
		}
	}
}