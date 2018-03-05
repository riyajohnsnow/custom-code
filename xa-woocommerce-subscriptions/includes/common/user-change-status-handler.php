<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HF_User_Change_Status_Handler {

	public function __construct() {
		add_action( 'wp_loaded', __CLASS__ . '::maybe_change_users_subscription', 100 );
	}


	public static function maybe_change_users_subscription() {

		if ( isset( $_GET['change_subscription_to'] ) && isset( $_GET['subscription_id'] ) && isset( $_GET['_wpnonce'] )  ) {

			$user_id      = get_current_user_id();
			$subscription = hf_get_subscription( $_GET['subscription_id'] );
			$new_status   = $_GET['change_subscription_to'];

			if ( self::validate_request( $user_id, $subscription, $new_status, $_GET['_wpnonce'] ) ) {
				self::change_users_subscription( $subscription, $new_status );

				wp_safe_redirect( $subscription->get_view_order_url() );
				exit;
			}
		}
	}

	public static function change_users_subscription( $subscription, $new_status ) {
		$subscription = ( ! is_object( $subscription ) ) ? hf_get_subscription( $subscription ) : $subscription;
		$changed = false;

		switch ( $new_status ) {
			case 'active' :
				if ( ! $subscription->needs_payment() ) {
					$subscription->update_status( $new_status );
					$subscription->add_order_note( __( 'Subscription reactivated by the subscriber from their account page.', HF_Subscriptions::TEXT_DOMAIN ) );
					HF_Subscriptions::add_notice( __( 'Your subscription has been reactivated.', HF_Subscriptions::TEXT_DOMAIN ), 'success' );
					$changed = true;
				} else {
					HF_Subscriptions::add_notice( __( 'You can not reactivate that subscription until paying to renew it. Please contact us if you need assistance.', HF_Subscriptions::TEXT_DOMAIN ), 'error' );
				}
				break;
			case 'on-hold' :
				if ( hf_can_user_put_subscription_on_hold( $subscription ) ) {
					$subscription->update_status( $new_status );
					$subscription->add_order_note( __( 'Subscription put on hold by the subscriber from their account page.', HF_Subscriptions::TEXT_DOMAIN ) );
					HF_Subscriptions::add_notice( __( 'Your subscription has been put on hold.', HF_Subscriptions::TEXT_DOMAIN ), 'success' );
					$changed = true;
				} else {
					HF_Subscriptions::add_notice( __( 'You can not suspend that subscription - the suspension limit has been reached. Please contact us if you need assistance.', HF_Subscriptions::TEXT_DOMAIN ), 'error' );
				}
				break;
			case 'cancelled' :
				$subscription->cancel_order();
				$subscription->add_order_note( __( 'Subscription cancelled by the subscriber from their account page.', HF_Subscriptions::TEXT_DOMAIN ) );
				HF_Subscriptions::add_notice( __( 'Your subscription has been cancelled.', HF_Subscriptions::TEXT_DOMAIN ), 'success' );
				$changed = true;
				break;
		}

		if ( $changed ) {
			do_action( 'hf_customer_changed_subscription_to_' . $new_status, $subscription );
		}
	}

	public static function validate_request( $user_id, $subscription, $new_status, $wpnonce = '' ) {
		$subscription = ( ! is_object( $subscription ) ) ? hf_get_subscription( $subscription ) : $subscription;

		if ( ! hf_is_subscription( $subscription ) ) {
			HF_Subscriptions::add_notice( __( 'That subscription does not exist. Please contact us if you need assistance.', HF_Subscriptions::TEXT_DOMAIN ), 'error' );
			return false;

		} elseif ( ! empty( $wpnonce ) && wp_verify_nonce( $wpnonce, $subscription->get_id() . $subscription->get_status() ) === false ) {
			HF_Subscriptions::add_notice( __( 'Security error. Please contact us if you need assistance.', HF_Subscriptions::TEXT_DOMAIN ), 'error' );
			return false;

		} elseif ( ! user_can( $user_id, 'edit_hf_shop_subscription_status', $subscription->get_id() ) ) {
			HF_Subscriptions::add_notice( __( 'That doesn\'t appear to be one of your subscriptions.', HF_Subscriptions::TEXT_DOMAIN ), 'error' );
			return false;

		} elseif ( ! $subscription->can_be_updated_to( $new_status ) ) {
			HF_Subscriptions::add_notice( sprintf( __( 'That subscription can not be changed to %s. Please contact us if you need assistance.', HF_Subscriptions::TEXT_DOMAIN ), hf_get_subscription_status_name( $new_status ) ), 'error' );
			return false;
		}

		return true;
	}
}
new HF_User_Change_Status_Handler();