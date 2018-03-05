<?php
/**
 * Functions used by plugins
 */
if ( ! class_exists( 'Hf_Dependencies' ) )
	require_once 'class-hf-dependencies.php';

/**
 * WC Detection
 */
if ( ! function_exists( 'hf_is_woocommerce_active' ) ) {
	function hf_is_woocommerce_active() {
		return Hf_Dependencies::woocommerce_active_check();
	}
}

add_action( 'admin_notices', 'show_notices_for_hf_subscription_plugin' );
function show_notices_for_hf_subscription_plugin() {

    $message = get_transient( 'hf_subscription_activation_error_message' );

    if ( ! empty( $message ) ) {
        echo "<div class='notice notice-error is-dismissible'>
            <p>$message</p>
        </div>";
    }
}