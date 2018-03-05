<?php
if (!defined('ABSPATH')) {
    exit;
}

// My Acount link and content alteration

class HF_Subscription_query extends WC_Query {

        public static $endpoint = 'subscriptions';
	public function __construct() {

		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'the_title', array( $this, 'change_endpoint_title' ), 11, 1 );

		if ( ! is_admin() ) {
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
			add_filter( 'woocommerce_get_breadcrumb', array( $this, 'add_breadcrumb' ), 10 );
			add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_items' ) );
			add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'render_endpoint_content' ) );
		}

		$this->init_query_vars();
	}

	public function init_query_vars() {
		$this->query_vars = array(
			'view-subscription' => get_option( 'woocommerce_myaccount_view_subscriptions_endpoint', 'view-subscription' ),
		);
		if ( ! HF_Subscriptions::is_woocommerce_prior_to( '2.6' ) ) {
			$this->query_vars['subscriptions'] = get_option( 'woocommerce_myaccount_subscriptions_endpoint', 'subscriptions' );
		}
	}

	public function add_breadcrumb( $crumbs ) {

		foreach ( $this->query_vars as $key => $query_var ) {
			if ( $this->is_query( $query_var ) ) {
				$crumbs[] = array( $this->get_endpoint_title( $key ) );
			}
		}
		return $crumbs;
	}

	public function change_endpoint_title( $title ) {

		if ( in_the_loop() && is_account_page() ) {
			foreach ( $this->query_vars as $key => $query_var ) {
				if ( $this->is_query( $query_var ) ) {
					$title = $this->get_endpoint_title( $key );
					remove_filter( 'the_title', array( $this, __FUNCTION__ ), 11 );
				}
			}
		}
		return $title;
	}


	public function get_endpoint_title( $endpoint ) {
            
		global $wp;
		switch ( $endpoint ) {
			case 'view-subscription':
				$subscription = hf_get_subscription( $wp->query_vars['view-subscription'] );
				$title        = ( $subscription ) ? sprintf( __( 'Subscription #%s', HF_Subscriptions::TEXT_DOMAIN ), $subscription->get_order_number() ) : '';
				break;
			case 'subscriptions':
				$title = get_option(HF_Subscriptions_Admin::$option_prefix . '_subscription_tab_title', 'Subscriptions');
                                if(empty($title)) $title = __('Subscriptions', HF_Subscriptions::TEXT_DOMAIN );
				break;
			default:
				$title = '';
				break;
		}
		return $title;
	}

	public function add_menu_items( $menu_items ) {
		
		if ( array_key_exists( 'orders', $menu_items ) ) {  
                        $title = get_option(HF_Subscriptions_Admin::$option_prefix . '_subscription_tab_text', 'Subscriptions');
                        if(empty($title)) $title = __('Subscriptions', HF_Subscriptions::TEXT_DOMAIN );
			$menu_items = hf_array_insert_after( 'orders', $menu_items, 'subscriptions', $title );
		} else {
                        $title = get_option(HF_Subscriptions_Admin::$option_prefix . '_subscription_tab_text', 'Subscriptions');
                        if(empty($title)) $title = __('Subscriptions', HF_Subscriptions::TEXT_DOMAIN );
			$menu_items['subscriptions'] = $title;
		}
		return $menu_items;
	}

	public function render_endpoint_content() {
                HF_Subscriptions::get_my_subscriptions_template();
	}

	protected function is_query( $query_var ) {
		global $wp;

		if ( is_main_query() && is_page() && isset( $wp->query_vars[ $query_var ] ) ) {
			$is_view_subscription_query = true;
		} else {
			$is_view_subscription_query = false;
		}
		return apply_filters( 'is_hf_subscription_query', $is_view_subscription_query, $query_var );
	}

}
new HF_Subscription_query();