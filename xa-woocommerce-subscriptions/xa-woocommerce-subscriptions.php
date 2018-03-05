<?php
/**
 * Plugin Name: HF WooCommerce Subscriptions
 * Plugin URI: https://wordpress.org/plugins/xa-woocommerce-subscriptions/
 * Description: Sell products with recurring payments in your WooCommerce Store.
 * Author: markhf
 * Author URI: 
 * Text Domain: xa-woocommerce-subscription
 * Version: 1.1.1
 * Domain Path: /i18n/lang
 * WC tested up to: 3.2.5

 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('HF_JS_URL')) {
    define('HF_JS_URL', plugin_dir_url(__FILE__) . 'assets/js/');
}
if (!defined('HF_CSS_URL')) {
    define('HF_CSS_URL', plugin_dir_url(__FILE__) . 'assets/css/');
}

if (!defined('HF_MAIN_URL')) {
    define('HF_MAIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('HF_MAIN_PATH')) {
    define('HF_MAIN_PATH', plugin_dir_path(__FILE__));
}

/*
if (!defined('HF_DEBUG')) {
    define('HF_DEBUG', TRUE);
}
*/

// Required functions
if (!function_exists('hf_is_woocommerce_active')) {
    require_once( 'hf-includes/hf-functions.php' );
}


// WC active check
if (!hf_is_woocommerce_active()) {
        $error_message = __('HF Subscription Plugin not activated. WooCommerce is required.', 'xa-woocommerce-subscription');
        set_transient('hf_subscription_activation_error_message', $error_message, 120);
        if (!function_exists('deactivate_plugins')) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }    
            deactivate_plugins(plugin_basename(__FILE__));
        
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
        return;
} else {
    set_transient('hf_subscription_activation_error_message', '', 120);
}

                
class HF_Subscriptions {

    public static $name = 'subscription';

    const TEXT_DOMAIN = 'hf-woocommerce-subscription';
    const ACTIVATION_TRANSIENT = 'hf_subscription_activated';
    const VERSION = '1.1.1';
    const PLUGN_BASE_PATH = __FILE__;

    public static $cache;
    private static $scheduler;
    

    public function __construct() {

        $this->include_common();
        $this->init_hooks();
        
        self::$cache = HF_Cache_Manager::get_instance();
        $task_scheduler = apply_filters('hf_subscription_scheduler', 'HF_Action_Scheduler');
        self::$scheduler = new $task_scheduler();
    }

    public function include_common() {
        
        require_once( 'hf-base-functions.php' );
        require_once( 'includes/subscriptions-coupon.php' );
        require_once( 'includes/subscriptions-product.php' );
        require_once( 'includes/admin/subscriptions-admin.php' );
        require_once( 'includes/common/subscriptions-manager.php' );
        require_once( 'includes/common/subscriptions-cart.php' );
        require_once( 'includes/common/subscriptions-order.php' );
        require_once( 'includes/common/subscriptions-renewal-order.php' );
        require_once( 'includes/common/subscriptions-checkout.php' );
        require_once( 'includes/common/subscriptions-email.php' );
        
        require_once( 'includes/payment-gateways.php' );
        require_once( 'includes/libraries/action-scheduler/action-scheduler.php' );
        require_once( 'includes/libraries/abstract-cache-manager.php' );
        require_once( 'includes/common/cached-data-manager.php' );
        require_once( 'includes/libraries/abstract-scheduler.php' );
        require_once( 'includes/common/action-scheduler.php' );
        require_once( 'includes/common/cart-renewal.php' );
        require_once( 'includes/common/cart-resubscribe.php' );
        require_once( 'includes/common/cart-initial-payment.php' );
        require_once( 'includes/common/download-handler.php' );
        require_once( 'includes/fallback/post-meta-property.php' );
    }

                
    private function init_hooks() {

        add_action('init', array($this, 'register_subscription_order_types'), 6);
        add_action('init', array($this, 'register_post_status'), 9);
        add_action('init', array($this, 'maybe_activate_hf_subscriptions'));
        add_action('init', array($this, 'load_plugin_textdomain'), 3);
        
        add_filter('woocommerce_data_stores', array($this, 'add_data_stores'), 10, 1);
        add_filter('woocommerce_order_button_text', array($this, 'order_button_text'));
        add_action('woocommerce_subscription_add_to_cart', array($this, 'render_subscription_add_to_cart'), 30);
        add_action('woocommerce_variable-subscription_add_to_cart', array($this, 'variable_subscription_add_to_cart'), 30);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'maybe_empty_cart'), 10, 4);
        add_action('wp_head', array($this, 'enqueue_view_subscription_style'), 100);

        add_action('plugins_loaded', array($this, 'load_wc_dependant_classes'));
        add_action('plugins_loaded', array($this, 'redirect_cart_and_account_hooks'));
        add_action('plugins_loaded', array($this, 'load_frontend_classes'));
        add_action('plugins_loaded', array($this, 'load_admin_classes'));
        
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'hf_action_links'));
        add_filter('action_scheduler_queue_runner_batch_size', array($this, 'action_scheduler_multisite_batch_size'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_hikeforce_subscriptions'));
    }

    public function add_data_stores($data_stores) {

        $data_stores['subscription'] = 'HF_Subscription_Data_Store';
        $data_stores['product-variable-subscription'] = 'WC_Product_Variable_Data_Store_CPT';
        $data_stores['product-subscription_variation'] = 'WC_Product_Variation_Data_Store_CPT';

        return $data_stores;
    }

    public function register_subscription_order_types() {

        wc_register_order_type('hf_shop_subscription', apply_filters('woocommerce_register_post_type_hf_subscription', array(
            'labels' => array(
                'name' => __('HF Subscriptions', self::TEXT_DOMAIN),
                'singular_name' => __('Subscription', self::TEXT_DOMAIN),
                'add_new' => __('Add Subscription', self::TEXT_DOMAIN),
                'add_new_item' => __('Add New Subscription', self::TEXT_DOMAIN),
                'edit' => __('Edit', self::TEXT_DOMAIN),
                'edit_item' => __('Edit Subscription', self::TEXT_DOMAIN),
                'new_item' => __('New Subscription', self::TEXT_DOMAIN),
                'view' => __('View Subscription', self::TEXT_DOMAIN),
                'view_item' => __('View Subscription', self::TEXT_DOMAIN),
                'search_items' => __('Search Subscriptions', self::TEXT_DOMAIN),
                'not_found' => self::get_subscription_not_found_text(),
                'not_found_in_trash' => __('No Subscriptions found in trash', self::TEXT_DOMAIN),
                'parent' => __('Parent Subscriptions', self::TEXT_DOMAIN),
                'menu_name' => __('HF Subscriptions', self::TEXT_DOMAIN),
            ),
                'description' => __('This is where subscriptions are stored.', self::TEXT_DOMAIN),
                'public' => false,
                'show_ui' => true,
                'capability_type' => 'shop_order',
                'map_meta_cap' => true,
                'publicly_queryable' => false,
                'exclude_from_search' => true,
                'show_in_menu' => current_user_can('manage_woocommerce') ? 'woocommerce' : true,
                'hierarchical' => false,
                'show_in_nav_menus' => false,
                'rewrite' => false,
                'query_var' => false,
                'supports' => array('title', 'comments', 'custom-fields'),
                'has_archive' => false,
                'exclude_from_orders_screen' => true,
                'add_order_meta_boxes' => true,
                'exclude_from_order_count' => true,
                'exclude_from_order_views' => true,
                'exclude_from_order_webhooks' => true,
                'exclude_from_order_reports' => true,
                'exclude_from_order_sales_reports' => true,
                'class_name' => self::is_woocommerce_prior_to('3.0') ? 'HF_Subscription_Legacy' : 'HF_Subscription',
                )
            )
        );
    }

    private static function get_subscription_not_found_text() {
        
        if (true === apply_filters('hf_hf_subscription_not_empty', hf_check_subscriptions_exist())) {
            $not_found_text = __('No Subscriptions found', self::TEXT_DOMAIN);
        } else {
            $not_found_text = '<p>' . __('Subscriptions will appear here once purchased by a customer.', self::TEXT_DOMAIN) . '</p>';
            $not_found_text .= '<p>' . sprintf(__('%sMore about managing subscriptions &raquo;%s', self::TEXT_DOMAIN), '<a href="https://wordpress.org/plugins/xa-woocommerce-subscriptions/" target="_blank">', '</a>') . '</p>';
            $not_found_text .= '<p>' . sprintf(__('%sAdd a subscription product &raquo;%s', self::TEXT_DOMAIN), '<a href="' . esc_url(HF_Subscriptions_Admin::add_subscription_url()) . '">', '</a>') . '</p>';
        }
        return $not_found_text;
    }

    public function register_post_status() {

        $subscription_statuses = hf_get_subscription_statuses();
        $registered_statuses = apply_filters('hf_subscriptions_registered_statuses', array(
            'wc-active' => _nx_noop('Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'post status label including post count', self::TEXT_DOMAIN),
            'wc-expired' => _nx_noop('Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'post status label including post count', self::TEXT_DOMAIN),
            'wc-pending-cancel' => _nx_noop('Pending Cancellation <span class="count">(%s)</span>', 'Pending Cancellation <span class="count">(%s)</span>', 'post status label including post count', self::TEXT_DOMAIN),
        ));

        if (is_array($subscription_statuses) && is_array($registered_statuses)) {

            foreach ($registered_statuses as $status => $label_count) {

                register_post_status($status, array(
                    'label' => $subscription_statuses[$status],
                    'public' => false,
                    'exclude_from_search' => false,
                    'show_in_admin_all_list' => true,
                    'show_in_admin_status_list' => true,
                    'label_count' => $label_count,
                ));
            }
        }
    }


    public function enqueue_view_subscription_style() {
        if (is_account_page()) {
            echo "<style>.subscription_details .button {
                                margin-bottom: 2px;
                                width: 100%;
                                padding:0.618047em 1.41575em;
                                max-width: 200px;
                                text-align: center;
                    }</style>";
        }
    }

    // load the subscriptions.php template on the My Account page.

    public static function get_my_subscriptions_template() {

        $subscriptions = hf_get_users_subscriptions();
        $user_id = get_current_user_id();
        wc_get_template('myaccount/subscriptions.php', array('subscriptions' => $subscriptions, 'user_id' => $user_id), '', plugin_dir_path(__FILE__) . 'templates/');
    }


    public static function redirect_ajax_add_to_cart($fragments) {

        $data = array(
            'error' => true,
            'product_url' => WC()->cart->get_cart_url(),
        );
        return $data;
    }

    public function maybe_empty_cart($valid, $product_id, $quantity, $variation_id = '') {

        $is_subscription = HF_Subscriptions_Product::is_subscription($product_id);
        $cart_contains_subscription = HF_Subscription_Cart::cart_contains_subscription();
        $multiple_subscriptions_possible = HF_Subscription_Payment_Gateways::one_gateway_supports('multiple_subscriptions');
        $manual_renewals_enabled = true;
        $canonical_product_id = (!empty($variation_id) ) ? $variation_id : $product_id;

        if ($is_subscription && 'yes' != get_option(HF_Subscriptions_Admin::$option_prefix . '_hf_allow_multiple_purchase', 'no')) {

            WC()->cart->empty_cart();
        } elseif ($is_subscription && hf_cart_contains_renewal() && !$multiple_subscriptions_possible && !$manual_renewals_enabled) {

            self::remove_subscriptions_from_cart();

            self::add_notice(__('A subscription renewal has been removed from your cart. Multiple subscriptions can not be purchased at the same time.', self::TEXT_DOMAIN), 'notice');
        } elseif ($is_subscription && $cart_contains_subscription && !$multiple_subscriptions_possible && !$manual_renewals_enabled && !HF_Subscription_Cart::cart_contains_product($canonical_product_id)) {

            self::remove_subscriptions_from_cart();

            self::add_notice(__('A subscription has been removed from your cart. Due to payment gateway restrictions, different subscription products can not be purchased at the same time.', self::TEXT_DOMAIN), 'notice');
        } elseif ($cart_contains_subscription && 'yes' != get_option(HF_Subscriptions_Admin::$option_prefix . '_hf_allow_multiple_purchase', 'no')) {

            self::remove_subscriptions_from_cart();

            self::add_notice(__('A subscription has been removed from your cart. Products and subscriptions can not be purchased at the same time.', self::TEXT_DOMAIN), 'notice');

            if (HF_Subscriptions::is_woocommerce_prior_to('3.0.8')) {
                add_filter('add_to_cart_fragments', __CLASS__ . '::redirect_ajax_add_to_cart');
            } else {
                add_filter('woocommerce_add_to_cart_fragments', __CLASS__ . '::redirect_ajax_add_to_cart');
            }
        }

        return $valid;
    }

    public static function remove_subscriptions_from_cart() {

        foreach (WC()->cart->cart_contents as $cart_item_key => $cart_item) {
            if (HF_Subscriptions_Product::is_subscription($cart_item['data'])) {
                WC()->cart->set_quantity($cart_item_key, 0);
            }
        }
    }

    public function add_to_cart_redirect($url) {

        if (isset($_REQUEST['add-to-cart']) && is_numeric($_REQUEST['add-to-cart']) && HF_Subscriptions_Product::is_subscription((int) $_REQUEST['add-to-cart'])) {

            if ('yes' != get_option(HF_Subscriptions_Admin::$option_prefix . '_hf_allow_multiple_purchase', 'no')) {
                wc_clear_notices();
                $url = wc_get_checkout_url();
            } elseif ('yes' != get_option('woocommerce_cart_redirect_after_add') && self::is_woocommerce_prior_to('2.5')) {
                $url = remove_query_arg('add-to-cart');
            }
        }
        return $url;
    }

    public function order_button_text($button_text) {
        
        global $product;
        if (HF_Subscription_Cart::cart_contains_subscription()) {
            $button_text = get_option(HF_Subscriptions_Admin::$option_prefix . '_order_button_text', __('Subscribe', self::TEXT_DOMAIN));
            if(empty($button_text)){
               $button_text =  __('Subscribe', self::TEXT_DOMAIN);
            }
        }
        return $button_text;
    }

    public function render_subscription_add_to_cart() {
        
        wc_get_template('single-product/add-to-cart/subscription.php', array(), '', HF_MAIN_PATH . 'templates/');
    }

    public static function variable_subscription_add_to_cart() {
        
        global $product;
        wp_enqueue_script('wc-add-to-cart-variation');
        $get_variations = sizeof($product->get_children()) <= apply_filters('woocommerce_ajax_variation_threshold', 30, $product);
        
        wc_get_template('single-product/add-to-cart/variable-subscription.php', array(
                    'available_variations' => $get_variations ? $product->get_available_variations() : false,
                    'attributes' => $product->get_variation_attributes(),
                    'selected_attributes' => $product->get_default_attributes(),
                ), '', plugin_dir_path(__FILE__) . 'templates/');
    }

    public static function append_numeral_suffix($number) {

        if (strlen($number) > 1 && 1 == substr($number, -2, 1)) {
            $number_string = sprintf(__('%sth', self::TEXT_DOMAIN), $number);
        } else { 
            switch (substr($number, -1)) {
                case 1:
                    $number_string = sprintf(__('%sst', self::TEXT_DOMAIN), $number);
                    break;
                case 2:
                    $number_string = sprintf(__('%snd', self::TEXT_DOMAIN), $number);
                    break;
                case 3:
                    $number_string = sprintf(__('%srd', self::TEXT_DOMAIN), $number);
                    break;
                default:
                    $number_string = sprintf(__('%sth', self::TEXT_DOMAIN), $number);
                    break;
            }
        }
        return apply_filters('hf_alter_numeral_suffix', $number_string, $number);
    }

    public function maybe_activate_hf_subscriptions() {
        
        $is_active = get_option(HF_Subscriptions_Admin::$option_prefix . '_is_active', false);
        if (false == $is_active) {
            if (!get_term_by('slug', self::$name, 'product_type')) {
                wp_insert_term(self::$name, 'product_type');
            }
            if (!get_term_by('slug', 'variable-subscription', 'product_type')) {
                wp_insert_term(__('Variable Subscription', self::TEXT_DOMAIN), 'product_type');
            }

            add_option(HF_Subscriptions_Admin::$option_prefix . '_is_active', true);
            set_transient(self::ACTIVATION_TRANSIENT, true, 60 * 60);
            flush_rewrite_rules();
            do_action('hikeforce_subscriptions_activated');
        }
    }

    public function deactivate_hikeforce_subscriptions() {

        delete_option(HF_Subscriptions_Admin::$option_prefix . '_is_active');
        flush_rewrite_rules();
        do_action('hikeforce_hikeforce_deactivated');
    }

    public function load_plugin_textdomain() {

        $plugin_rel_path = dirname(plugin_basename(__FILE__)) . '/i18n/lang';
        load_plugin_textdomain(self::TEXT_DOMAIN, false, $plugin_rel_path);
    }

    
    public function load_frontend_classes() {
        require_once( 'includes/frontend-loader/subscription-template-loader.php' );
        require_once( 'includes/frontend-loader/subscription-account-view.php' );
        require_once( 'includes/frontend-loader/subscriptions-addresses.php' );
    }
     
    public function load_wc_dependant_classes() {

        require_once( 'includes/components/subscription.php' );
        require_once( 'includes/components/product-subscription.php' );
        require_once( 'includes/components/product-subscription-variation.php' );
        require_once( 'includes/components/product-variable-subscription.php' );

        require_once( 'includes/common/add-remove-item.php' );
        require_once( 'includes/common/user-change-status-handler.php' );
        require_once( 'includes/common/account-payment-tokens.php' );

        if (self::is_woocommerce_prior_to('3.0')) {

            require_once( 'includes/fallback/subscription-legacy.php' );
            require_once( 'includes/fallback/product-legacy.php' );
            require_once( 'includes/fallback/product-subscription-legacy.php' );
            require_once( 'includes/fallback/product-subscription-variation-legacy.php' );
            require_once( 'includes/fallback/product-variable-subscription-legacy.php' );
            
            if (!class_exists('WC_DateTime')) {
                require_once( 'includes/libraries/wc-datetime.php' );
            }
        } else {
            require_once( 'includes/libraries/subscription-data-store.php' );
        }
    }

    
    public function load_admin_classes() {
        
        require_once( 'includes/admin/subscription-management.php' );
        require_once( 'includes/admin/subscription-meta-boxes.php' );
        require_once( 'includes/admin/meta-boxes/subscription-related-orders-meta-box.php' );
        require_once( 'includes/admin/meta-boxes/subscription-data-meta-box.php' );
        require_once( 'includes/admin/meta-boxes/subscription-schedule-meta-box.php' );
    }

    
    public function redirect_cart_and_account_hooks() {

        add_filter('woocommerce_add_to_cart_redirect', array($this, 'add_to_cart_redirect'));
        if (self::is_woocommerce_prior_to('2.6')) {
            add_action('woocommerce_before_my_account', __CLASS__ . '::get_my_subscriptions_template');
        }
    }

    public static function get_product($product_id) {

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
        } else {
            $product = new WC_Product($product_id);
        }
        return $product;
    }

    public static function get_longest_possible_period($current_period, $new_period) {

        if (empty($current_period) || 'year' == $new_period) {
            $longest_period = $new_period;
        } elseif ('month' === $new_period && in_array($current_period, array('week', 'day'))) {
            $longest_period = $new_period;
        } elseif ('week' === $new_period && 'day' === $current_period) {
            $longest_period = $new_period;
        } else {
            $longest_period = $current_period;
        }
        return $longest_period;
    }

    public static function get_shortest_possible_period($current_period, $new_period) {

        if (empty($current_period) || 'day' == $new_period) {
            $shortest_period = $new_period;
        } elseif ('week' === $new_period && in_array($current_period, array('month', 'year'))) {
            $shortest_period = $new_period;
        } elseif ('month' === $new_period && 'year' === $current_period) {
            $shortest_period = $new_period;
        } else {
            $shortest_period = $current_period;
        }
        return $shortest_period;
    }

    public function hf_action_links($links) {

        $plugin_links = array(
            '<a href="' . HF_Subscriptions_Admin::settings_tab_url() . '">' . __('Settings', self::TEXT_DOMAIN) . '</a>',
            '<a href="https://wordpress.org/support/plugin/xa-woocommerce-subscriptions">' . __('Support', self::TEXT_DOMAIN) . '</a>',
            '<a href="https://wordpress.org/support/plugin/xa-woocommerce-subscriptions/reviews/">' . __('Review', self::TEXT_DOMAIN) . '</a>',
        );
        return array_merge($plugin_links, $links);
    }

    public static function is_woocommerce_prior_to($version) {

        if (!defined('WC_VERSION') || version_compare(WC_VERSION, $version, '<')) {
            $woocommerce_is_pre_version = true;
        } else {
            $woocommerce_is_pre_version = false;
        }
        return $woocommerce_is_pre_version;
    }

    public static function add_notice($message, $notice_type = 'success') {
        wc_add_notice($message, $notice_type);
    }

    public static function print_notices() {
        wc_print_notices();
    }

    public function action_scheduler_multisite_batch_size($batch_size) {

        if (is_multisite()) {
            $batch_size = 10;
        }
        return $batch_size;
    }

}

new HF_Subscriptions();