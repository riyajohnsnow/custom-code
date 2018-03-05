<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscription_Template_Loader {

    public function  __construct(){
        
        add_filter('wc_get_template', array($this, 'add_view_subscription_template'), 10, 5);
        add_action('woocommerce_account_view-subscription_endpoint', array($this, 'get_view_subscription_template'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'add_subscriptions_to_view_order_templates'), 10, 1 );
        add_action('hf_subscription_details_after_order_table', array($this, 'get_related_orders_template'), 10, 1 );
        add_filter('woocommerce_my_account_my_orders_actions', array($this,'maybe_remove_pay_action'), 10, 2 );
        add_action('woocommerce_thankyou', array($this,'subscription_thank_you_footer_message') );
    }

    public function add_view_subscription_template($located, $template_name, $args, $template_path, $default_path) {
        
        global $wp;
        if ('myaccount/my-account.php' == $template_name && !empty($wp->query_vars['view-subscription']) && HF_Subscriptions::is_woocommerce_prior_to('2.6')) {
            $located = wc_locate_template('myaccount/view-subscription.php', $template_path, plugin_dir_path(HF_Subscriptions::PLUGN_BASE_PATH) . 'templates/');
        }
        return $located;
    }

    public function get_view_subscription_template() {
        wc_get_template('myaccount/view-subscription.php', array(), '', plugin_dir_path(HF_Subscriptions::PLUGN_BASE_PATH) . 'templates/');
    }
    
    public function add_subscriptions_to_view_order_templates( $order_id ) {

		$template      = 'myaccount/related-subscriptions.php';
		$subscriptions = hf_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );

		if ( ! empty( $subscriptions ) ) {
			wc_get_template( $template, array( 'order_id' => $order_id, 'subscriptions' => $subscriptions ), '', plugin_dir_path( HF_Subscriptions::PLUGN_BASE_PATH ) . 'templates/' );
		}
    }
    public function get_related_orders_template( $subscription ) {

            $subscription_orders = $subscription->get_related_orders();
            if ( 0 !== count( $subscription_orders ) ) {
                    wc_get_template( 'myaccount/related-orders.php', array( 'subscription_orders' => $subscription_orders, 'subscription' => $subscription ), '', plugin_dir_path( HF_Subscriptions::PLUGN_BASE_PATH ) . 'templates/' );
            }
    }
    
    
    	public  function maybe_remove_pay_action( $actions, $order ) {

		if ( isset( $actions['pay'] ) && hf_order_contains_subscription( $order, array( 'any' ) ) ) {
			$subscriptions = hf_get_subscriptions_for_order( hf_get_objects_property( $order, 'id' ), array( 'order_type' => 'any' ) );

			foreach ( $subscriptions as $subscription ) {
				if ( hf_get_objects_property( $order, 'id' ) != $subscription->get_last_order() ) {
					unset( $actions['pay'] );
					break;
				}
			}
		}
		return $actions;
	}
        
        public function subscription_thank_you_footer_message( $order_id ) {

		if ( hf_order_contains_subscription( $order_id, 'any' ) ) {

			$subscription_count           = count( hf_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) ) );
			$thank_you_message            = '<p>' . _n( 'Your subscription will be activated when payment clears.', 'Your subscriptions will be activated when payment clears.', $subscription_count, HF_Subscriptions::TEXT_DOMAIN ) . '</p>';
			$my_account_subscriptions_url = get_permalink( wc_get_page_id( 'myaccount' ) );

			if ( ! HF_Subscriptions::is_woocommerce_prior_to( '2.6' ) ) {
				$my_account_subscriptions_url = wc_get_endpoint_url( 'subscriptions', '', wc_get_page_permalink( 'myaccount' ) );
			}
			$thank_you_message .= '<p>' . sprintf( _n( 'View the status of your subscription in %syour account%s.', 'View the status of your subscriptions in %syour account%s.', $subscription_count, HF_Subscriptions::TEXT_DOMAIN ), '<a href="' . $my_account_subscriptions_url . '">', '</a>' ) . '</p>';
			echo wp_kses( apply_filters( 'hf_subscription_thank_you_message', $thank_you_message, $order_id ), array( 'a' => array( 'href' => array(), 'title' => array() ), 'p' => array(), 'em' => array(), 'strong' => array() ) );
		}

	}
    
}

new HF_Subscription_Template_Loader();