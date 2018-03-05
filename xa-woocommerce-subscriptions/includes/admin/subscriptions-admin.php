<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscriptions_Admin {

    // setting tab slug
    const PLUGIN_ID = 'hf_subscriptions';

    //subscription settings field prefix
    public static $option_prefix = 'hf_subscriptions';

    const TEXT_DOMAIN = 'hf-woocommerce-subscription';

    private static $found_related_orders = false;
    private static $saved_product_meta = false;

    public function __construct() {

        add_filter('product_type_selector', array($this, 'add_subscription_product_type_to_list'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'display_subscription_pricing_fields'));
        add_action('woocommerce_product_after_variable_attributes', array($this, 'display_variable_subscription_pricing_fields'), 10, 3);

        add_action('woocommerce_page_wc-status', array($this, 'clear_subscription_transients'));
        

        add_action('woocommerce_variable_product_bulk_edit_actions', array($this, 'variable_subscription_bulk_edit_actions'), 10);
        add_action('woocommerce_product_bulk_edit_save', array($this, 'bulk_edit_save_subscription_meta'), 10);

        add_action('woocommerce_process_product_meta_variable-subscription', array($this, 'process_product_meta_variable_subscription'));
        add_action('woocommerce_save_product_variation', array($this, 'save_product_variation'), 20, 2);

        add_action('hf_subscription_pre_update_status', array($this, 'check_customer_is_set'), 10, 3);
        add_action('product_variation_linked', array($this, 'set_variation_meta_defaults_on_bulk_add'));

        add_filter('woocommerce_settings_tabs_array', array($this, 'add_hf_subscription_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_hf_subscriptions', array($this, 'hf_subscription_settings_page'));
        add_action('woocommerce_update_options_' . self::PLUGIN_ID, array($this, 'save_subscription_settings'));
        add_action('woocommerce_admin_field_informational', array($this, 'add_info_admin_field'));

        add_filter('manage_users_columns', array($this, 'add_new_user_columns'), 11, 1);
        add_filter('manage_users_custom_column', array($this, 'new_user_column_values'), 11, 3);

        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles_scripts'));

        add_filter('posts_where', array($this, 'filter_orders'));
        
        add_action('save_post', array($this, 'save_subscription_meta_data'), 11);
        add_action('save_post', array($this, 'save_variable_subscription_meta_data'), 11);

        add_action('admin_notices', array($this, 'display_filter_message'));

        add_filter('set-screen-option', array($this, 'set_manage_subscriptions_screen_option'), 10, 3);

        add_shortcode('hikeforce_subscriptions', array($this, 'do_subscriptions_shortcode'));
        
        add_filter('woocommerce_system_status_report', array($this, 'hf_render_system_status_items'));

        add_filter('woocommerce_payment_gateways_setting_columns', array($this, 'payment_gateways_rewewal_column'));
        add_action('woocommerce_payment_gateways_setting_column_renewals', array($this, 'payment_gateways_rewewal_support'));
        add_action('woocommerce_payment_gateways_settings', array($this, 'add_recurring_payment_gateway_information'), 10, 1);

        add_action('woocommerce_admin_order_totals_after_refunded', array($this, 'maybe_attach_gettext_callback'), 10, 1);
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'maybe_unattach_gettext_callback'), 10, 1);
    }



    public function add_subscription_product_type_to_list($product_types) {

        $product_types['subscription'] = __('Simple subscription', HF_Subscriptions::TEXT_DOMAIN);
        $product_types['variable-subscription'] = __('Variable subscription', HF_Subscriptions::TEXT_DOMAIN);
        return $product_types;
    }

    public function display_subscription_pricing_fields() {

       include( HF_MAIN_PATH. 'templates/subscription/product-price-fields.php' );
    }

    public function display_variable_subscription_pricing_fields($loop, $variation_data, $variation) {
            
            global $thepostid;
            if (!function_exists('woocommerce_wp_text_input')) {
                require_once( WC()->plugin_path() . '/admin/post-types/writepanels/writepanels-init.php' );
            }

            if (!isset($thepostid)) {
                $thepostid = $variation->post_parent;
            }

            $variation_product = wc_get_product($variation);
            $billing_period = HF_Subscriptions_Product::get_period($variation_product);

            if (empty($billing_period)) {
                $billing_period = 'month';
            }

            include( HF_MAIN_PATH . 'templates/subscription/variation-price-fields.php' );
            wp_nonce_field('hf_subscription_variations', '_hfnonce_save_variations', false);
            do_action('hf_variable_subscription_pricing', $loop, $variation_data, $variation);
        }

    public function variable_subscription_bulk_edit_actions() {
            
            global $post;

            if (HF_Subscriptions_Product::is_subscription($post->ID)) :
                        ?>
            <optgroup label="<?php esc_attr_e('Subscription pricing', HF_Subscriptions::TEXT_DOMAIN); ?>">
                <option value="variable_subscription_period_interval"><?php esc_html_e('Subscription billing interval', HF_Subscriptions::TEXT_DOMAIN); ?></option>
                <option value="variable_subscription_period"><?php esc_html_e('Subscription period', HF_Subscriptions::TEXT_DOMAIN); ?></option>
                <option value="variable_subscription_length"><?php esc_html_e('Subscription length', HF_Subscriptions::TEXT_DOMAIN); ?></option>
            </optgroup>
        <?php
        endif;
    }

    public function save_subscription_meta_data($post_id) {

        if (empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_subscription_meta') || false === self::is_subscription_product_save_request($post_id, apply_filters('hf_subscription_product_types', array(HF_Subscriptions::$name)))) {
            return;
        }

        $subscription_price = isset($_REQUEST['_hf_subscription_price']) ? wc_format_decimal($_REQUEST['_hf_subscription_price']) : '';
        $sale_price = wc_format_decimal($_REQUEST['_sale_price']);

        update_post_meta($post_id, '_hf_subscription_price', $subscription_price);
        update_post_meta($post_id, '_regular_price', $subscription_price);
        update_post_meta($post_id, '_sale_price', $sale_price);

        $date_from = ( isset($_POST['_sale_price_dates_from']) ) ? hf_date_to_time($_POST['_sale_price_dates_from']) : '';
        $date_to = ( isset($_POST['_sale_price_dates_to']) ) ? hf_date_to_time($_POST['_sale_price_dates_to']) : '';
        $now = gmdate('U');
        if (!empty($date_to) && empty($date_from)) {
            $date_from = $now;
        }
        update_post_meta($post_id, '_sale_price_dates_from', $date_from);
        update_post_meta($post_id, '_sale_price_dates_to', $date_to);

        if (!empty($sale_price) && ( ( empty($date_to) && empty($date_from) ) || ( $date_from < $now && ( empty($date_to) || $date_to > $now ) ) )) {
            $price = $sale_price;
        } else {
            $price = $subscription_price;
        }

        update_post_meta($post_id, '_price', stripslashes($price));


        $subscription_fields = array(
            '_subscription_period',
            '_subscription_period_interval',
            '_subscription_length',
        );

        foreach ($subscription_fields as $field_name) {
            if (isset($_REQUEST[$field_name])) {
                update_post_meta($post_id, $field_name, stripslashes($_REQUEST[$field_name]));
            }
        }

        self::$saved_product_meta = true;
    }


    public function save_variable_subscription_meta_data($post_id) {

        if (empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_subscription_meta') || false === self::is_subscription_product_save_request($post_id, apply_filters('hf_subscription_variable_product_types', array('variable-subscription')))) {
            return;
        }

        self::$saved_product_meta = true;
    }
     
    public function bulk_edit_save_subscription_meta($product) {

        if (!$product->is_type('subscription')) {
            return;
        }
        $price_changed = false;
        $old_regular_price = $product->get_regular_price();
        $old_sale_price = $product->get_sale_price();

        if (!empty($_REQUEST['change_regular_price'])) {

            $change_regular_price = absint($_REQUEST['change_regular_price']);
            $regular_price = esc_attr(stripslashes($_REQUEST['_regular_price']));

            switch ($change_regular_price) {
                case 1 :
                    $new_price = $regular_price;
                    break;
                case 2 :
                    if (strstr($regular_price, '%')) {
                        $percent = str_replace('%', '', $regular_price) / 100;
                        $new_price = $old_regular_price + ( $old_regular_price * $percent );
                    } else {
                        $new_price = $old_regular_price + $regular_price;
                    }
                    break;
                case 3 :
                    if (strstr($regular_price, '%')) {
                        $percent = str_replace('%', '', $regular_price) / 100;
                        $new_price = $old_regular_price - ( $old_regular_price * $percent );
                    } else {
                        $new_price = $old_regular_price - $regular_price;
                    }
                    break;
            }

            if (isset($new_price) && $new_price != $old_regular_price) {
                $price_changed = true;
                hf_set_objects_property($product, 'regular_price', $new_price);
                hf_set_objects_property($product, 'subscription_price', $new_price);
            }
        }

        if (!empty($_REQUEST['change_sale_price'])) {

            $change_sale_price = absint($_REQUEST['change_sale_price']);
            $sale_price = esc_attr(stripslashes($_REQUEST['_sale_price']));

            switch ($change_sale_price) {
                case 1 :
                    $new_price = $sale_price;
                    break;
                case 2 :
                    if (strstr($sale_price, '%')) {
                        $percent = str_replace('%', '', $sale_price) / 100;
                        $new_price = $old_sale_price + ( $old_sale_price * $percent );
                    } else {
                        $new_price = $old_sale_price + $sale_price;
                    }
                    break;
                case 3 :
                    if (strstr($sale_price, '%')) {
                        $percent = str_replace('%', '', $sale_price) / 100;
                        $new_price = $old_sale_price - ( $old_sale_price * $percent );
                    } else {
                        $new_price = $old_sale_price - $sale_price;
                    }
                    break;
                case 4 :
                    if (strstr($sale_price, '%')) {
                        $percent = str_replace('%', '', $sale_price) / 100;
                        $new_price = $product->get_regular_price() - ( $product->get_regular_price() * $percent );
                    } else {
                        $new_price = $product->get_regular_price() - $sale_price;
                    }
                    break;
            }

            if (isset($new_price) && $new_price != $old_sale_price) {
                $price_changed = true;
                hf_set_objects_property($product, 'sale_price', $new_price);
            }
        }

        if ($price_changed) {
            hf_set_objects_property($product, 'sale_price_dates_from', '');
            hf_set_objects_property($product, 'sale_price_dates_to', '');

            if ($product->get_regular_price() < $product->get_sale_price()) {
                hf_set_objects_property($product, 'sale_price', '');
            }

            if ($product->get_sale_price()) {
                hf_set_objects_property($product, 'price', $product->get_sale_price());
            } else {
                hf_set_objects_property($product, 'price', $product->get_regular_price());
            }
        }
    }

    public function process_product_meta_variable_subscription($post_id) {

        if (!HF_Subscriptions_Product::is_subscription($post_id) || empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_subscription_meta')) {
            return;
        }

        $_POST['variable_regular_price'] = isset($_POST['variable_subscription_price']) ? $_POST['variable_subscription_price'] : 0;

        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            $variable_subscription = wc_get_product($post_id);
            $variable_subscription->variable_product_sync();
        } else {
            WC_Product_Variable::sync($post_id);
        }
    }

    public function save_product_variation($variation_id, $index) {

        if (!HF_Subscriptions_Product::is_subscription($variation_id) || empty($_POST['_hfnonce_save_variations']) || !wp_verify_nonce($_POST['_hfnonce_save_variations'], 'hf_subscription_variations')) {
            return;
        }

        if (isset($_POST['variable_subscription_price'][$index])) {
            $subscription_price = wc_format_decimal($_POST['variable_subscription_price'][$index]);
            update_post_meta($variation_id, '_hf_subscription_price', $subscription_price);
            update_post_meta($variation_id, '_regular_price', $subscription_price);
        }

        $subscription_fields = array(
            '_subscription_period',
            '_subscription_period_interval',
            '_subscription_length',
        );

        foreach ($subscription_fields as $field_name) {
            if (isset($_POST['variable' . $field_name][$index])) {
                update_post_meta($variation_id, $field_name, wc_clean($_POST['variable' . $field_name][$index]));
            }
        }
    }

    public function check_customer_is_set($old_status, $new_status, $subscription) {
        
        global $post;

        if (is_admin() && 'active' == $new_status && isset($_POST['hf_meta_nonce']) && wp_verify_nonce($_POST['hf_meta_nonce'], 'woocommerce_save_data') && isset($_POST['customer_user']) && !empty($post) && 'hf_shop_subscription' === $post->post_type) {
            $user = new WP_User(absint($_POST['customer_user']));
            if (0 === $user->ID) {
                throw new Exception(sprintf(__('Unable to change subscription status to "%s". Please assign a customer to the subscription to activate it.', HF_Subscriptions::TEXT_DOMAIN), $new_status));
            }
        }
    }

    public function set_variation_meta_defaults_on_bulk_add($variation_id) {

        if (!empty($variation_id)) {
            update_post_meta($variation_id, '_subscription_period', 'month');
            update_post_meta($variation_id, '_subscription_period_interval', '1');
            update_post_meta($variation_id, '_subscription_length', '0');
        }
    }

    
    public function clear_subscription_transients() {
        
        global $wpdb;
        if (empty($_GET['action']) || empty($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'debug_action')) {
            return;
        }

        if (wc_clean($_GET['action']) === 'clear_transients') {
            $transients_to_delete = array(
                'wc_report_subscription_by_product',
                'wc_report_subscription_by_customer',
                'wc_report_subscription_events_by_date',
            );

            $results = $wpdb->get_col("SELECT DISTINCT `option_name`
				FROM `$wpdb->options`
				WHERE `option_name` LIKE '%hf-related-orders-to-%' OR `option_name` LIKE '%hf-sub-ranges-%'");

            foreach ($results as $column) {
                $name = explode('transient_', $column, 2);
                $transients_to_delete[] = $name[1];
            }

            foreach ($transients_to_delete as $transient_to_delete) {
                delete_transient($transient_to_delete);
            }
        }
    }
    
    public function enqueue_styles_scripts() {
        
        global $post;

        $screen = get_current_screen();
        $is_woocommerce_screen = ( in_array($screen->id, array('product', 'edit-shop_order', 'shop_order', 'edit-hf_shop_subscription', 'hf_shop_subscription', 'users', 'woocommerce_page_wc-settings')) ) ? true : false;

        if ($is_woocommerce_screen) {
            $dependencies = array('jquery');
            $woocommerce_admin_script_handle = 'wc-admin-meta-boxes';
            $trashing_subscription_order_warning = __('Trashing this order will also trash the subscriptions purchased with the order.', HF_Subscriptions::TEXT_DOMAIN);

            if ($screen->id == 'product') {
                $dependencies[] = $woocommerce_admin_script_handle;
                $dependencies[] = 'wc-admin-product-meta-boxes';
                $dependencies[] = 'wc-admin-variation-meta-boxes';

                $script_params = array(
                    'ProductType' => HF_Subscriptions::$name,
                    'SingularLocalizedTrialPeriod' => hf_get_available_time_periods(),
                    'PluralLocalizedTrialPeriod' => hf_get_available_time_periods('plural'),
                    'LocalizedSubscriptionLengths' => hf_get_subscription_ranges(),                    
                    'BulkEditPeriodMessage' => __('Enter the new period, either day, week, month or year:', HF_Subscriptions::TEXT_DOMAIN),
                    'BulkEditLengthMessage' => __('Enter a new length (e.g. 5):', HF_Subscriptions::TEXT_DOMAIN),
                    'BulkEditIntervalMessage' => __('Enter a new interval as a single number (e.g. to charge every 2nd month, enter 2):', HF_Subscriptions::TEXT_DOMAIN),                    
                );
            } else if ('edit-shop_order' == $screen->id) {
                $script_params = array(
                    'BulkTrashWarning' => __("You are about to trash one or more orders which contain a subscription.\n\nTrashing the orders will also trash the subscriptions purchased with these orders.", HF_Subscriptions::TEXT_DOMAIN),
                    'TrashWarning' => $trashing_subscription_order_warning,
                );
            } else if ('shop_order' == $screen->id) {
                $dependencies[] = $woocommerce_admin_script_handle;
                $dependencies[] = 'wc-admin-order-meta-boxes';

                if (HF_Subscriptions::is_woocommerce_prior_to('2.6')) {
                    $dependencies[] = 'wc-admin-order-meta-boxes-modal';
                }

                $script_params = array(
                    'TrashWarning' => $trashing_subscription_order_warning,
                    'postId' => $post->ID,
                );
            } else if ('users' == $screen->id) {
                $script_params = array(
                    'DeleteUserWarning' => __("Warning: Deleting a user will also delete the user's subscriptions. The user's orders will remain but be reassigned to the 'Guest' user.\n\nDo you want to continue to delete this user and any associated subscriptions?", HF_Subscriptions::TEXT_DOMAIN),
                );
            }
            $script_params['ajaxURL'] = admin_url('admin-ajax.php');
            wp_enqueue_script('hikeforce_subscription_admin', HF_JS_URL.'admin/subscription-admin.js', $dependencies, filemtime(plugin_dir_path(HF_Subscriptions::PLUGN_BASE_PATH) . 'assets/js/admin/subscription-admin.js'));
            wp_localize_script('hikeforce_subscription_admin', 'HFSubscriptions_OBJ', apply_filters('hf_subscription_admin_script_parameters', $script_params));
        }

        if ($is_woocommerce_screen || 'edit-product' == $screen->id) {
            wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), HF_Subscriptions::VERSION);
            wp_enqueue_style('hikeforce_subscription_admin', HF_CSS_URL . 'subscription-admin.css', array('woocommerce_admin_styles'), HF_Subscriptions::VERSION);
        }
    }

    public static function add_new_user_columns($columns) {

        if (current_user_can('manage_woocommerce')) {
            $last_column = array_slice($columns, -1, 1, true);
            array_pop($columns);
            $columns['woocommerce_active_subscriber'] = __('Active subscriber', HF_Subscriptions::TEXT_DOMAIN);
            $columns += $last_column;
        }
        return $columns;
    }

    public function new_user_column_values($value, $column_name, $user_id) {

        if ('woocommerce_active_subscriber' == $column_name) {
            if (hf_user_has_subscription($user_id, '', 'active')) {
                $value = '<div class="status-enabled"></div>';
            } else {
                $value = '<div class="status-disabled">-</div>';
            }
        }
        return $value;
    }

    public static function add_manage_subscriptions_screen_options() {
        
        add_screen_option('per_page', array(
                    'label' => __('Subscriptions', HF_Subscriptions::TEXT_DOMAIN),
                    'default' => 10,
                    'option' => self::$option_prefix . '_admin_per_page',
                )
        );
    }

    public function set_manage_subscriptions_screen_option($status, $option, $value) {
        
        if (self::$option_prefix . '_admin_per_page' == $option) {
            return $value;
        }
        return $status;
    }

    public function save_subscription_settings() {

        if (empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_subscription_settings')) {
            return;
        }
        
        woocommerce_update_options(self::get_settings());
    }

    public static function hf_subscription_settings_page() {
        
        woocommerce_admin_fields(self::get_settings());
        wp_nonce_field('hf_subscription_settings', '_hfnonce', false);
    }

    public function add_hf_subscription_settings_tab($settings_tabs) {

        $settings_tabs[self::PLUGIN_ID] = __('HF Subscriptions', HF_Subscriptions::TEXT_DOMAIN);
        return $settings_tabs;
    }

    public static function add_default_settings() {
        
        foreach (self::get_settings() as $setting) {
            if (isset($setting['default'])) {
                add_option($setting['id'], $setting['default']);
            }
        }
    }

    public static function get_settings() {
        

        return apply_filters('hikeforce_subscription_settings', array(
            array(
                'name' => __('Manage Text', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'title',
                'desc' => '',
                'id' => self::$option_prefix . '_button_text',
            ),
            array(
                'name' => __('My Account Tab Title', HF_Subscriptions::TEXT_DOMAIN),
                'desc' => __('My Account Tab Title for subscription listing page.', HF_Subscriptions::TEXT_DOMAIN),
                'tip' => '',
                'id' => self::$option_prefix . '_subscription_tab_title',
                'css' => 'min-width:150px;',
                'default' => __('Subscriptions', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'text',
                'desc_tip' => true,
            ),
            array(
                'name' => __('My Account Tab Text', HF_Subscriptions::TEXT_DOMAIN),
                'desc' => __('My Account Tab Text for subscription listing page.', HF_Subscriptions::TEXT_DOMAIN),
                'tip' => '',
                'id' => self::$option_prefix . '_subscription_tab_text',
                'css' => 'min-width:150px;',
                'default' => __('Subscriptions', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'text',
                'desc_tip' => true,
            ),
            array(
                'name' => __('Add to Cart Button Text', HF_Subscriptions::TEXT_DOMAIN),
                'desc' => __('Add to Cart Button Text on product page.', HF_Subscriptions::TEXT_DOMAIN),
                'tip' => '',
                'id' => self::$option_prefix . '_add_to_cart_button_text',
                'css' => 'min-width:150px;',
                'default' => __('Subscribe', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'text',
                'desc_tip' => true,
            ),
            array(
                'name' => __('Place Order Button Text', HF_Subscriptions::TEXT_DOMAIN),
                'desc' => __('Place Order Button Text in checkout Page when an order contains a subscription.', HF_Subscriptions::TEXT_DOMAIN),
                'tip' => '',
                'id' => self::$option_prefix . '_order_button_text',
                'css' => 'min-width:150px;',
                'default' => __('Subscribe', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'text',
                'desc_tip' => true,
            ),
            array('type' => 'sectionend', 'id' => self::$option_prefix . '_button_text'),
            
            array(
                'name' => __('Other', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'title',
                'desc' => '',
                'id' => self::$option_prefix . '_other',
            ),
            
            array(
                'name' => __('Allow Mixed Checkout', HF_Subscriptions::TEXT_DOMAIN),
                'desc' => __('Allow subscription products and normal products to be purchased together.', HF_Subscriptions::TEXT_DOMAIN),
                'id' => self::$option_prefix . '_hf_allow_multiple_purchase',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array('type' => 'sectionend', 'id' => self::$option_prefix . '_other'),
                ));
    }


    public function add_info_admin_field($field_details) {

        if (isset($field_details['name']) && $field_details['name']) {
            echo '<h3>' . esc_html($field_details['name']) . '</h3>';
        }
        if (isset($field_details['desc']) && $field_details['desc']) {
            echo wp_kses_post(wpautop(wptexturize($field_details['desc'])));
        }
    }
    
    public static function add_subscription_url() {
        
        $add_subscription_url = admin_url('post-new.php?post_type=product&select_subscription=true');
        return $add_subscription_url;
    }

    public static function get_woocommerce_plugin_path() {

        $woocommerce_plugin_file = '';
        foreach (get_option('active_plugins', array()) as $plugin) {
            if (substr($plugin, strlen('/woocommerce.php') * -1) === '/woocommerce.php') {
                $woocommerce_plugin_file = $plugin;
                break;
            }
        }
        return $woocommerce_plugin_file;
    }

    public function filter_orders($where) {
        
        global $typenow, $wpdb;

        if (is_admin() && 'shop_order' == $typenow) {
            $related_orders = array();
            if (isset($_GET['_subscription_related_orders']) && $_GET['_subscription_related_orders'] > 0) {

                $subscription_id = absint($_GET['_subscription_related_orders']);
                $subscription = hf_get_subscription($subscription_id);

                if (!hf_is_subscription($subscription)) {
                    hf_add_admin_notice(sprintf(__('We can\'t find a subscription with ID #%d. Perhaps it was deleted?', HF_Subscriptions::TEXT_DOMAIN), $subscription_id), 'error');
                    $where .= " AND {$wpdb->posts}.ID = 0";
                } else {
                    self::$found_related_orders = true;
                    $where .= sprintf(" AND {$wpdb->posts}.ID IN (%s)", implode(',', array_map('absint', array_unique($subscription->get_related_orders('ids')))));
                }
            }
        }
        return $where;
    }

    public function display_filter_message() {

        global $wp_version;
        $query_arg = '_subscription_related_orders';

        if (isset($_GET[$query_arg]) && $_GET[$query_arg] > 0 && true === self::$found_related_orders) {

            $initial_order = new WC_Order(absint($_GET[$query_arg]));

            if (version_compare($wp_version, '4.2', '<')) {
                echo '<div class="updated"><p>';
                printf(
                        '<a href="%1$s" class="close-subscriptions-search">&times;</a>', esc_url(remove_query_arg($query_arg))
                );
                printf(esc_html__('Showing orders for %sSubscription %s%s', HF_Subscriptions::TEXT_DOMAIN), '<a href="' . esc_url(get_edit_post_link(absint($_GET[$query_arg]))) . '">', esc_html($initial_order->get_order_number()), '</a>');
                echo '</p>';
            } else {
                echo '<div class="updated dismiss-subscriptions-search"><p>';
                printf(esc_html__('Showing orders for %sSubscription %s%s', HF_Subscriptions::TEXT_DOMAIN), '<a href="' . esc_url(get_edit_post_link(absint($_GET[$query_arg]))) . '">', esc_html($initial_order->get_order_number()), '</a>');
                echo '</p>';
                printf(
                        '<a href="%1$s" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></a>', esc_url(remove_query_arg($query_arg))
                );
            }

            echo '</div>';
        }
    }



    // callback for the [hikeforce_subscriptions] shortcode that displays subscription names for a particular user.

    public function do_subscriptions_shortcode($attributes) {
        
        $attributes = wp_parse_args(
                $attributes, array(
            'user_id' => 0,
            'status' => 'active',
                )
        );
        $subscriptions = hf_get_users_subscriptions($attributes['user_id']);
        if (empty($subscriptions)) {
            return '<ul class="user-subscriptions no-user-subscriptions">
						<li>' . esc_html_x('No subscriptions found.', 'in [subscriptions] shortcode', HF_Subscriptions::TEXT_DOMAIN) . '</li>
					</ul>';
        }

        $list = '<ul class="user-subscriptions">';

        foreach ($subscriptions as $subscription) {
            if ('all' == $attributes['status'] || $subscription->has_status($attributes['status'])) {

                $shortcode_translate = sprintf(esc_html_x('Subscription %s', 'in [hikeforce_subscriptions] shortcode', HF_Subscriptions::TEXT_DOMAIN), $subscription->get_order_number());
                $list .= sprintf('<li><a href="%s">%s</a></li>', $subscription->get_view_order_url(), $shortcode_translate);
            }
        }
        $list .= '</ul>';
        return $list;
    }

    public function hf_render_system_status_items() {
        
        $debug_data = array();
        $is_hf_debug = defined('HF_DEBUG') ? HF_DEBUG : false;

        $debug_data['hf_debug'] = array(
            'name' => __('HF_DEBUG', HF_Subscriptions::TEXT_DOMAIN),
            'note' => ( $is_hf_debug ) ? __('Yes', HF_Subscriptions::TEXT_DOMAIN) : __('No', HF_Subscriptions::TEXT_DOMAIN),
            'success' => $is_hf_debug ? 0 : 1,
        );

        $debug_data = apply_filters('hf_system_status', $debug_data);
        ?>
        <table class="wc_status_table widefat" cellspacing="0">
            <thead>
                <tr>
                    <th colspan="3" data-export-label="subscriptions"><h2><?php esc_html_e('HF Subscriptions', HF_Subscriptions::TEXT_DOMAIN); ?>
        <?php echo hf_help_tip(__('This section shows any information about Hikeforce Subscriptions.', HF_Subscriptions::TEXT_DOMAIN)); ?>
                        </h2></th>
                </tr>
            </thead>
            <tbody>
        <?php
        foreach ($debug_data as $data) :
            $mark = ( isset($data['success']) && true == $data['success'] ) ? 'yes' : 'error';
            $mark_icon = 'yes' === $mark ? 'yes' : 'no-alt';
            ?>
                    <tr>
                        <td data-export-label="<?php echo esc_attr($data['name']) ?>"><?php echo esc_html($data['name']) ?>:</td>
                        <td class="help">&nbsp;</td>
                        <td>
                            <mark class="<?php echo esc_html($mark) ?>">
                                <span class="dashicons dashicons-<?php echo esc_html($mark_icon) ?>"></span> <?php echo wp_kses_data($data['note']); ?>
                            </mark>
                        </td>
                    </tr>
        <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public static function settings_tab_url() {

        $settings_tab_url = admin_url('admin.php?page=wc-settings&tab=' . self::PLUGIN_ID);
        return $settings_tab_url;
    }

    public function payment_gateways_rewewal_column($header) {

        $header_new = array_slice($header, 0, count($header) - 1, true) +
                array('renewals' => __('Auto Recurring Payments', HF_Subscriptions::TEXT_DOMAIN)) +
                array_slice($header, count($header) - 1, count($header) - ( count($header) - 1 ), true);
        return $header_new;
    }

    public function payment_gateways_rewewal_support($gateway) {

        echo '<td class="renewals">';
        if (( is_array($gateway->supports) && in_array('subscriptions', $gateway->supports) ) || $gateway->id == 'paypal') {
            $status_html = '<span class="status-enabled tips" data-tip="' . esc_attr__('Supports automatic renewal payments with the HikeForce Subscriptions plugin.', HF_Subscriptions::TEXT_DOMAIN) . '">' . esc_html__('Yes', HF_Subscriptions::TEXT_DOMAIN) . '</span>';
        } else {
            $status_html = '-';
        }
        $allowed_html = wp_kses_allowed_html('post');
        $allowed_html['span']['data-tip'] = true;
        echo wp_kses(apply_filters('woocommerce_payment_gateways_renewal_support_status_html', $status_html, $gateway), $allowed_html);
        echo '</td>';
    }


    public function maybe_attach_gettext_callback() {

        $screen = get_current_screen();
        if (is_object($screen) && 'hf_shop_subscription' == $screen->id) {
            add_filter('gettext', __CLASS__ . '::change_order_item_editable_text', 10, 3);
        }
    }

    public function maybe_unattach_gettext_callback() {

        $screen = get_current_screen();
        if (is_object($screen) && 'hf_shop_subscription' == $screen->id) {
            remove_filter('gettext', __CLASS__ . '::change_order_item_editable_text', 10, 3);
        }
    }

    public static function change_order_item_editable_text($translated_text, $text, $domain) {

        switch ($text) {
            case 'This order is no longer editable.':
                $translated_text = __('Subscription items can no longer be edited.', HF_Subscriptions::TEXT_DOMAIN);
                break;
            case 'To edit this order change the status back to "Pending"':
                $translated_text = __('This subscription is no longer editable because the payment gateway does not allow modification of recurring amounts.', HF_Subscriptions::TEXT_DOMAIN);
                break;
        }
        return $translated_text;
    }

    public function add_recurring_payment_gateway_information($settings) {
        
        $available_gateways_description = '';
        $recurring_payment_settings = array();

        if (!HF_Subscription_Payment_Gateways::one_gateway_supports('subscriptions')) {
            $available_gateways_description = sprintf(__('No payment gateways capable of processing automatic subscription payments are enabled.', HF_Subscriptions::TEXT_DOMAIN) );
        }
        if ($available_gateways_description) {
            $recurring_payment_settings = array(
                array(
                    'name' => __('Recurring Payments', HF_Subscriptions::TEXT_DOMAIN),
                    'desc' => $available_gateways_description,
                    'id' => HF_Subscriptions_Admin::$option_prefix . '_payment_gateways_available',
                    'type' => 'informational',
                )
            );
        }

        $insert_index = array_search(array(
            'type' => 'sectionend',
            'id' => 'payment_gateways_options',
                ), $settings
        );


        $checkout_settings = array();

        foreach ($settings as $key => $value) {

            $checkout_settings[$key] = $value;
            unset($settings[$key]);

            if ($key == $insert_index) {
                $checkout_settings = array_merge($checkout_settings, $recurring_payment_settings, $settings);
                break;
            }
        }

        return $checkout_settings;
    }

    private static function is_subscription_product_save_request($post_id, $product_types) {

        if (self::$saved_product_meta) {
            $is_subscription_product_save_request = false;
        } elseif (empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_subscription_meta')) {
            $is_subscription_product_save_request = false;
        } elseif (!isset($_POST['product-type']) || !in_array($_POST['product-type'], $product_types)) {
            $is_subscription_product_save_request = false;
        } elseif (empty($_POST['post_ID']) || $_POST['post_ID'] != $post_id) {
            $is_subscription_product_save_request = false;
        } else {
            $is_subscription_product_save_request = true;
        }

        return apply_filters('hf_admin_is_subscription_product_save_request', $is_subscription_product_save_request, $post_id, $product_types);
    }

}

new HF_Subscriptions_Admin();