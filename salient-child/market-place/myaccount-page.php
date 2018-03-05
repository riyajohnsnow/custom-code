<?php
add_action('woocommerce_before_cart_table', 'woo_add_continue_shopping_button_to_cart');
function woo_add_continue_shopping_button_to_cart()
{

    if (is_checkout()) {
        ?>
        <p class="return-to-shop">
            <a class="button wc-backward"
               href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))); ?>">
                <?php _e('Return to shop', 'woocommerce') ?>
            </a>
        </p>
        <?php
    } else {
        ?>
        <!-- Riya: remove return shop button from single product page -->
        <!-- <p class="return-to-shop" >
            <a class="button wc-backward"
               href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))); ?>">
                <?php _e('Return to shop', 'woocommerce') ?>
            </a>
        </p> -->
        <?php
    }
}
/**
 * Bypass logout confirmation.
 */
function iconic_bypass_logout_confirmation() {
    global $wp;

    if ( isset( $wp->query_vars['customer-logout'] ) ) {
        wp_redirect( str_replace( '&amp;', '&', wp_logout_url( wc_get_page_permalink( 'myaccount' ) ) ) );
        exit;
    }
}
add_action( 'template_redirect', 'iconic_bypass_logout_confirmation' );
//Riya: after checkout put back to store and check order button
add_filter('woocommerce_thankyou', 'display_back_to_store_button');    // 2.1 +
function display_back_to_store_button()
{
?>
    <p class="custom-back-to-store">
        <a class="button wc-backward" href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))); ?>">
            <?php _e('Back to store', 'woocommerce') ?>
        </a>

        <a class="button wc-backward" href="<?php echo get_site_url()."/account/orders"; ?>">
            <?php _e('Check my orders', 'woocommerce') ?>
        </a>
    </p>
<?php

}
//Riya: changes in subscription tab in download user account
if ( ! function_exists( 'wc_display_item_downloads' ) ) {
    /**
     * Display item download links.
     *
     * @since  3.0.0
     * @param  WC_Order_Item $item Order Item.
     * @param  array         $args Arguments.
     * @return string|void
     */
    function wc_display_item_downloads( $item, $args = array() ) {

        $strings = array();
        $html    = '';
        $args    = wp_parse_args( $args, array(
            'before'    => '<ul class ="wc-item-downloads"><li>',
            'after'     => '</li></ul>',
            'separator' => '</li><li>',
            'echo'      => true,
            'show_url'  => false,
        ) );
        $product_id = $item['product_id'];
        $product = wc_get_product( $product_id );
        $download1 = $product->get_files();
      
        if ( is_object( $item ) && $item->is_type( 'line_item' )  ) {
            $i = 0;
            
            foreach ( $download1 as $download ) {
              
                if($download['id'] == '1'){
                    $name = "Full Dataset Download";
                    echo '<strong class="wc-item-download-label">' .$name. ':</strong> <a href="' .  $download['file'] . '"  class="dataset-csv csv-btn"><img src="http://202.47.116.116:8224/wp-content/uploads/icons/csv.svg"></a><br>';
                }
                if($download['id'] == '2'){
                    $name = "Data Dictonary Download";
                    echo '<strong class="wc-item-download-label">' .$name. ':</strong> <a href="' . esc_url( $download['file'] ) . '"  class="dataset-csv csv-btn"><img src="http://202.47.116.116:8224/wp-content/uploads/icons/pdf.svg"></a><br>';
                }
                if($download['id'] == '0'){
                    $name = "Sample Dataset Download";
                    echo '<strong class="wc-item-download-label">' .$name. ':</strong> <a href="' . esc_url( $download['file'] ) . '"  class="dataset-csv csv-btn"><img src="http://202.47.116.116:8224/wp-content/uploads/icons/csv.svg"></a><br>';
                }

            }

        }

        if ( $strings ) {
            $html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
        }

        $html = apply_filters( 'woocommerce_display_item_downloads', $html, $item, $args );

        if ( $args['echo'] ) {
            echo $html; // WPCS: XSS ok.
        } else {
            return $html;
        }
    }
}
/* disable bundle plugin update */
function filter_plugin_updates_wcpb( $value ) {
    unset( $value->response['wc-product-bundles/wcpb.php'] );
    return $value;
}
add_filter( 'site_transient_update_plugins', 'filter_plugin_updates_wcpb' );
/* disable subscription plugin update*/
function filter_plugin_updates_subscription( $value ) {
    unset( $value->response['xa-woocommerce-subscriptions/xa-woocommerce-subscriptions.php'] );
    return $value;
}
add_filter( 'site_transient_update_plugins', 'filter_plugin_updates_subscription' );

/* Email sending on subscription cancel to the customer */
function get_email_body_for_subsciption_cancel($subscription_id) {
    $subscription = wc_get_order($subscription_id); // Or: new WC_Subscription($subscription_id); 
        // Iterating through subscription items
        foreach( $subscription->get_items() as $item_id => $product_subscription ){
            // Get the name
            $product_name = $product_subscription->get_name();
        }
    $current_user = wp_get_current_user();
     
    $body = 'Dear ' .esc_html( $current_user->user_login );
    $body .= '<p>Your John Snow Labs subscription to <b>'.$product_name.'</b> has been canceled. Please note that a cancelation of this subscription stops future renewal charges but does not result in a refund of your order.</p>';
    $body .= '<p>Access to the dataset(s) included in the canceled subscription will still be possible until the day the current subscription expires. </p>';
    $body .= '<p>If you want to take advantage of this subscription at a later point in time you can simply reactivate it yourself .</p>';
    $body .= '<p>We hope your decision to discontinue your subscription is only temporary and that you will consider renewing your subscription in the future!</p>';
    $body .= 'Thank you!</p>';
    $body .= 'John Snow Labs Team</p>';
    return $body;
}

/* Email sending for Order cancel */
function get_email_body_for_order_cancel($order_id){
    $ordered = wc_get_order($order_id); // Or: new WC_Subscription($subscription_id); 
    // Iterating through subscription items
    foreach( $ordered->get_items() as $item_id => $product_subscription ){
        // Get the name
        $product_name = $product_subscription->get_name();
    }
    $current_user = wp_get_current_user();
     
    $body = 'Dear ' .esc_html( $current_user->user_login );
    $body .= '<p>Your order containing the following John Snow Labs subscriptions:<b>'.$product_name.'</b> has been canceled.</p>';
    // $body .= '<p>Access to the dataset(s) included in the canceled subscription will still be possible until the day the current subscription expires. </p>';
    $body .= '<p>We hope your decision to cancel your order is only temporary and that you will consider subscribing to John Snow Labs data products in the future!</p>';
    $body .= 'Thank you!</p>';
    $body .= 'John Snow Labs Team</p>';
    return $body;
}

add_action('init','function_cancelled_sub_email');
function function_cancelled_sub_email(){

    if(isset($_REQUEST['subscription_id'])){
        $subscription_id = $_REQUEST['subscription_id'];
        $order = new WC_Order( $subscription_id );
        $to = $order->billing_email;
        $message = ob_get_contents();
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $message .= get_email_body_for_subsciption_cancel($subscription_id);
        ob_end_clean();
        wp_mail($to, 'Subscription Cancelled', $message ,$headers);    
    }
    elseif(isset($_REQUEST['order_id']))
    {

        if(is_checkout()){
            $order_id = $_REQUEST['order_id'];
            $subscription_id = $order_id + 1;
            if($subscription_id){
                $sub_order = new WC_Order( $subscription_id );
                $to_email = $sub_order->billing_email;
                $message_sub = ob_get_contents();
                $headers_sub = array('Content-Type: text/html; charset=UTF-8');
                $message_sub .= get_email_body_for_subsciption_cancel($subscription_id);
                ob_end_clean();
                wp_mail($to_email, 'Subscription Cancelled', $message_sub ,$headers_sub);
            }    
            $order = new WC_Order( $order_id );
            $to = $order->billing_email;
            $message = ob_get_contents();
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $message .= get_email_body_for_order_cancel($order_id);
            ob_end_clean();
            wp_mail($to, 'Order Cancelled', $message ,$headers);

        }else{

            if(isset($_REQUEST['cancel_order']) && $_REQUEST['cancel_order'] == true ){
                
                $order_id = $_REQUEST['order_id'];
                $subscription_id = $order_id + 1;
                if($subscription_id){
                    $sub_order = new WC_Order( $subscription_id );
                    $to_email = $sub_order->billing_email;
                    $message_sub = ob_get_contents();
                    $headers_sub = array('Content-Type: text/html; charset=UTF-8');
                    $message_sub .= get_email_body_for_subsciption_cancel($subscription_id);
                    ob_end_clean();
                    // wp_mail($to_email, 'Subscription Cancelled', $message_sub ,$headers_sub);
                }    
                $order = new WC_Order( $order_id );
                $to = $order->billing_email;
                $message = ob_get_contents();
                $headers = array('Content-Type: text/html; charset=UTF-8');
                $message .= get_email_body_for_order_cancel($order_id);
                ob_end_clean();
                wp_mail($to, 'Order Cancelled', $message ,$headers);  
            }
        }
    }
}

/* On Order hold set cancel button for cancel the order */
add_filter( 'woocommerce_valid_order_statuses_for_cancel', 'custom_valid_order_statuses_for_cancel', 10, 2 );
function custom_valid_order_statuses_for_cancel( $statuses, $order ){

    // Set HERE the order statuses where you want the cancel button to appear
    $custom_statuses    = array( 'on-hold' );

    // Set HERE the delay (in days)
    $duration = 20; // 3 days

    // UPDATE: Get the order ID and the WC_Order object
    if( isset($_GET['order_id']))
        $order = wc_get_order( absint( $_GET['order_id'] ) );

    $delay = $duration*24*60*60; // (duration in seconds)
    $date_created_time  = strtotime($order->get_date_created()); // Creation date time stamp
    $date_modified_time = strtotime($order->get_date_modified()); // Modified date time stamp
    $now = strtotime("now"); // Now  time stamp

    // Using Creation date time stamp
    if ( ( $date_created_time + $delay ) >= $now ) return $custom_statuses;
    else return $statuses;
}
 