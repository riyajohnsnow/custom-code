<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HF_Subscription_Admin_Meta_Boxes {


	public function __construct() {

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 25 );
		add_action( 'add_meta_boxes', array( $this, 'remove_meta_boxes' ), 35 );
                    
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'remove_meta_box_save' ), -1, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_subscription_scripts' ), 20 );

		add_action( 'woocommerce_process_shop_order_meta', 'HF_Meta_Box_Schedule::save', 10, 2 );
		add_action( 'woocommerce_process_shop_order_meta', 'HF_Meta_Box_Subscription_Data::save', 10, 2 );
                
                //Save subscription meta data 
                add_action( 'save_post', array( $this, 'save_meta_boxes' ), 1, 2 );

		add_filter( 'woocommerce_order_actions', array( $this, 'add_subscription_actions'), 10, 1 );

		add_action( 'woocommerce_order_action_hf_process_renewal', array( $this, 'process_renewal_action_request'), 10, 1 );
		add_action( 'woocommerce_order_action_hf_create_pending_renewal', array( $this, 'create_pending_renewal_action_request'), 10, 1 );

		add_filter( 'woocommerce_resend_order_emails_available', array( $this, 'remove_order_email_actions'), 0, 1 );

		add_action( 'woocommerce_order_action_hf_retry_renewal_payment', array( $this, 'process_retry_renewal_payment_action_request'), 10, 1 );
	}

        public function save_meta_boxes($post_id, $post = ''){
            
            	if ( in_array( $post->post_type, wc_get_order_types( 'order-meta-boxes' ) ) ) {
			do_action( 'woocommerce_process_shop_order_meta', $post_id, $post );
		}
        }


        public function add_meta_boxes() {
            
		global $post_ID;
                
		add_meta_box( 'hf-subscription-data', __( 'Subscription Data', HF_Subscriptions::TEXT_DOMAIN ), 'HF_Meta_Box_Subscription_Data::output', 'hf_shop_subscription', 'normal', 'high' );
		add_meta_box( 'hikeforce-subscription-schedule-box', __( 'Billing Schedule Data', HF_Subscriptions::TEXT_DOMAIN ), 'HF_Meta_Box_Schedule::output', 'hf_shop_subscription', 'side', 'default' );
		remove_meta_box( 'woocommerce-order-data', 'hf_shop_subscription', 'normal' );
		add_meta_box( 'subscription_renewal_orders', __( 'Related Orders', HF_Subscriptions::TEXT_DOMAIN ), 'HF_Meta_Box_Related_Orders::output', 'hf_shop_subscription', 'normal', 'low' );

		if ( 'shop_order' === get_post_type( $post_ID ) && hf_order_contains_subscription( $post_ID, 'any' ) ) {
			add_meta_box( 'subscription_renewal_orders', __( 'Related Orders', HF_Subscriptions::TEXT_DOMAIN ), 'HF_Meta_Box_Related_Orders::output', 'shop_order', 'normal', 'low' );
		}
	}

	public function remove_meta_boxes() {
		remove_meta_box( 'woocommerce-order-data', 'hf_shop_subscription', 'normal' );
	}

	public function remove_meta_box_save( $post_id, $post ) {

		if ( 'hf_shop_subscription' == $post->post_type ) {
			remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2 );
		}
	}

	public function enqueue_subscription_scripts() {
            
		global $post;
		$screen = get_current_screen();
		if ( 'hf_shop_subscription' == $screen->id ) {

			wp_register_script( 'hf-jstimezonedetect', HF_JS_URL.'admin/jstimezonedetect.min.js' );
			wp_register_script( 'hf-momentjs', HF_JS_URL.'admin/moment.min.js' );
			wp_enqueue_script( 'hf-subscription-meta-box', HF_JS_URL.'admin/subscription-meta-box.js', array( 'wc-admin-meta-boxes', 'hf-jstimezonedetect', 'hf-momentjs' ), WC_VERSION );

			wp_localize_script( 'hf-subscription-meta-box', 'hf_admin_meta_boxes', apply_filters( 'hf_subscription_admin_meta_boxes_script_parameters', array(
				'i18n_start_date_notice'         => __( 'Please enter a start date in the past.', HF_Subscriptions::TEXT_DOMAIN ),
				'i18n_past_date_notice'          => __( 'Please enter a date at least one hour into the future.', HF_Subscriptions::TEXT_DOMAIN ),
				'i18n_next_payment_start_notice' => __( 'Please enter a date after the trial end.', HF_Subscriptions::TEXT_DOMAIN ),
				'process_renewal_action_warning' => __( "Are you sure you want to process a renewal?\n\nThis will charge the customer and email them the renewal order (if emails are enabled).", HF_Subscriptions::TEXT_DOMAIN ),
				'payment_method'                 => hf_get_subscription( $post )->get_payment_method(),
				'search_customers_nonce'         => wp_create_nonce( 'search-customers' ),
			) ) );
		} else if ( 'shop_order' == $screen->id ) {
			wp_enqueue_script( 'hf-admin-meta-boxes-order', HF_JS_URL.'admin/order-renewal.js' );
			wp_localize_script( 'hf-admin-meta-boxes-order', 'hf_admin_order_meta_boxes', array(
				'retry_renewal_payment_action_warning' => __( "Are you sure you want to retry payment for this renewal order?\n\nThis will attempt to charge the customer and send renewal order emails.", HF_Subscriptions::TEXT_DOMAIN ),
				)
			);
		}
	}

	public function add_subscription_actions( $actions ) {
            
		global $theorder;
		if ( hf_is_subscription( $theorder ) && ! $theorder->has_status( hf_get_subscription_ended_statuses() ) ) {

			if ( $theorder->payment_method_supports( 'subscription_date_changes' ) && $theorder->has_status( 'active' ) ) {
				$actions['hf_process_renewal'] = esc_html__( 'Process renewal', HF_Subscriptions::TEXT_DOMAIN );
			}

			$actions['hf_create_pending_renewal'] = esc_html__( 'Create pending renewal order', HF_Subscriptions::TEXT_DOMAIN );

		} else if ( self::can_renewal_order_be_retried( $theorder ) ) {
			$actions['hf_retry_renewal_payment'] = esc_html__( 'Retry Renewal Payment', HF_Subscriptions::TEXT_DOMAIN );
		}
                rsort($actions);
		return $actions;
	}

	
	 
	public function process_renewal_action_request( $subscription ) {
            
		do_action( 'woocommerce_scheduled_subscription_payment', $subscription->get_id() );
		$subscription->add_order_note( __( 'Process renewal order action requested by admin.', HF_Subscriptions::TEXT_DOMAIN ), false, true );
	}

	
	public function create_pending_renewal_action_request( $subscription ) {

		$subscription->update_status( 'on-hold' );
		$renewal_order = hf_create_renewal_order( $subscription );
		if ( ! $subscription->is_manual() ) {
			$renewal_order->set_payment_method( wc_get_payment_gateway_by_order( $subscription ) );        
		}
		$subscription->add_order_note( __( 'Create pending renewal order requested by admin action.', HF_Subscriptions::TEXT_DOMAIN ), false, true );
	}

	
	public function remove_order_email_actions( $email_actions ) {
            
		global $theorder;
		if ( hf_is_subscription( $theorder ) ) {
			$email_actions = array();
		}
		return $email_actions;
	}

		 
	public function process_retry_renewal_payment_action_request( $order ) {

		if ( self::can_renewal_order_be_retried( $order ) ) {
			WC()->payment_gateways();
			do_action( 'woocommerce_scheduled_subscription_payment_' . hf_get_objects_property( $order, 'payment_method' ), $order->get_total(), $order );
		}
	}

	private static function can_renewal_order_be_retried( $order ) {

		$can_be_retried = false;

		if ( hf_order_contains_renewal( $order ) && $order->needs_payment() && '' != hf_get_objects_property( $order, 'payment_method' ) ) {
			$supports_date_changes          = false;
			$order_payment_gateway          = wc_get_payment_gateway_by_order( $order );
			$order_payment_gateway_supports = ( isset( $order_payment_gateway->id ) ) ? has_action( 'woocommerce_scheduled_subscription_payment_' . $order_payment_gateway->id ) : false;

			foreach ( hf_get_subscriptions_for_renewal_order( $order ) as $subscription ) {
				$supports_date_changes = $subscription->payment_method_supports( 'subscription_date_changes' );
				$is_automatic = ! $subscription->is_manual();
				break;
			}
			$can_be_retried = $order_payment_gateway_supports && $supports_date_changes && $is_automatic;
		}
		return $can_be_retried;
	}
}

new HF_Subscription_Admin_Meta_Boxes();