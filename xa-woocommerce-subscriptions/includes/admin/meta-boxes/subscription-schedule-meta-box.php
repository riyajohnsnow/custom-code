<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class HF_Meta_Box_Schedule {


	public static function output( $post ) {
		global $post, $the_subscription;

		if ( empty( $the_subscription ) ) {
			$the_subscription = hf_get_subscription( $post->ID );
		}
		include( 'subscription-scheduler.php' );
	}
	 
	public static function save( $post_id, $post ) {

		if ( 'hf_shop_subscription' == $post->post_type && ! empty( $_POST['hf_meta_nonce'] ) && wp_verify_nonce( $_POST['hf_meta_nonce'], 'woocommerce_save_data' ) ) {

                    
			if ( isset( $_POST['_billing_interval'] ) ) {
				update_post_meta( $post_id, '_billing_interval', $_POST['_billing_interval'] );
			}
                        
                        
                        if ( isset( $_POST['next_payment'] ) ) {
                                $next_payment_date = $_POST['next_payment'].' '. $_POST['next_payment_hour'].':'. $_POST['next_payment_minute'].':'.date("s");
				update_post_meta( $post_id, '_schedule_next_payment', $next_payment_date);
			}

			if ( ! empty( $_POST['_billing_period'] ) ) {
				update_post_meta( $post_id, '_billing_period', $_POST['_billing_period'] );
			}

			$subscription = hf_get_subscription( $post_id );

			$dates = array();

			foreach ( hf_get_subscription_available_date_types() as $date_type => $date_label ) {
				$date_key = hf_normalise_date_type_key( $date_type );

				if ( 'last_order_date_created' == $date_key ) {
					continue;
				}

				$utc_timestamp_key = $date_type . '_timestamp_utc';

				if ( 'date_created' === $date_key && empty( $_POST[ $utc_timestamp_key ] ) ) {
					$datetime = current_time( 'timestamp', true );
				} elseif ( isset( $_POST[ $utc_timestamp_key ] ) ) {
					$datetime = $_POST[ $utc_timestamp_key ];
				} else { 
					continue;
				}

				$dates[ $date_key ] = gmdate( 'Y-m-d H:i:s', $datetime );
			}
                        if(isset($next_payment_date)){
                            $dates['next_payment'] = $next_payment_date;
                        }
			try {
				$subscription->update_dates( $dates, 'gmt' );

				wp_cache_delete( $post_id, 'posts' );
			} catch ( Exception $e ) {
				hf_add_admin_notice( $e->getMessage(), 'error' );
			}

			$subscription->save();
		}
	}
}