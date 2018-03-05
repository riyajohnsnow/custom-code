<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HF_Subscription_Legacy extends HF_Subscription {

	protected $schedule;
	protected $status_transition = false;
	protected $object_read = true;

	public function __construct( $subscription ) {
		parent::__construct( $subscription );
		$this->order_type = 'hf_shop_subscription';
		$this->schedule = new stdClass();
	}

	public function populate( $result ) {
		parent::populate( $result );
		if ( $this->post->post_parent > 0 ) {
			$this->order = wc_get_order( $this->post->post_parent );
		}
	}

	public function get_id() {
		return $this->id;
	}

	public function get_parent_id() {
		return $this->post->post_parent;
	}

	public function get_currency() {
		return $this->get_order_currency();
	}

	public function get_customer_note( $context = 'view' ) {
		return $this->customer_note;
	}

	public function get_prices_include_tax( $context = 'view' ) {
		return $this->prices_include_tax;
	}

	public function get_payment_method( $context = 'view' ) {
		return $this->payment_method;
	}

	public function get_payment_method_title( $context = 'view' ) {
		return $this->payment_method_title;
	}

	public function get_billing_first_name( $context = 'view' ) {
		return $this->billing_first_name;
	}

	public function get_billing_last_name( $context = 'view' ) {
		return $this->billing_last_name;
	}

	public function get_billing_company( $context = 'view' ) {
		return $this->billing_company;
	}

	public function get_billing_address_1( $context = 'view' ) {
		return $this->billing_address_1;
	}

	public function get_billing_address_2( $context = 'view' ) {
		return $this->billing_address_2;
	}
        
	public function get_billing_city( $context = 'view' ) {
		return $this->billing_city;
	}

	public function get_billing_state( $context = 'view' ) {
		return $this->billing_state;
	}

	public function get_billing_postcode( $context = 'view' ) {
		return $this->billing_postcode;
	}

	public function get_billing_country( $context = 'view' ) {
		return $this->billing_country;
	}

	public function get_billing_email( $context = 'view' ) {
		return $this->billing_email;
	}

	public function get_billing_phone( $context = 'view' ) {
		return $this->billing_phone;
	}

	public function get_shipping_first_name( $context = 'view' ) {
		return $this->shipping_first_name;
	}

	public function get_shipping_last_name( $context = 'view' ) {
		return $this->shipping_last_name;
	}

	public function get_shipping_company( $context = 'view' ) {
		return $this->shipping_company;
	}

	public function get_shipping_address_1( $context = 'view' ) {
		return $this->shipping_address_1;
	}

	public function get_shipping_address_2( $context = 'view' ) {
		return $this->shipping_address_2;
	}

	public function get_shipping_city( $context = 'view' ) {
		return $this->shipping_city;
	}

	public function get_shipping_state( $context = 'view' ) {
		return $this->shipping_state;
	}

	public function get_shipping_postcode( $context = 'view' ) {
		return $this->shipping_postcode;
	}

	public function get_shipping_country( $context = 'view' ) {
		return $this->shipping_country;
	}

	public function get_order_key( $context = 'view' ) {
		return $this->order_key;
	}

	public function get_date_created( $context = 'view' ) {

		if ( '0000-00-00 00:00:00' != $this->post->post_date_gmt ) {
			$datetime = new WC_DateTime( $this->post->post_date_gmt, new DateTimeZone( 'UTC' ) );
			$datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		} else {
			$datetime = new WC_DateTime( $this->post->post_date, new DateTimeZone( wc_timezone_string() ) );
		}

		if ( ! isset( $this->schedule->start ) ) {
			$this->schedule->start = hf_get_datetime_utc_string( $datetime );
		}

		return $datetime;
	}

	public function get_date_modified( $context = 'view' ) {

		if ( '0000-00-00 00:00:00' != $this->post->post_modified_gmt ) {
			$datetime = new WC_DateTime( $this->post->post_modified_gmt, new DateTimeZone( 'UTC' ) );
			$datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		} else {
			$datetime = new WC_DateTime( $this->post->post_modified, new DateTimeZone( wc_timezone_string() ) );
		}

		return $datetime;
	}

	protected function get_prop( $prop, $context = 'view' ) {

		if ( 'switch_data' == $prop ) {
			$prop = 'subscription_switch_data';
		}

		if ( 'requires_manual_renewal' === $prop ) {
			$value = get_post_meta( $this->get_id(), '_' . $prop, true );

			if ( 'false' === $value || '' === $value ) {
				$value = false;
			} else {
				$value = true;
			}
		} elseif ( ! isset( $this->$prop ) || empty( $this->$prop ) ) {
			$value = get_post_meta( $this->get_id(), '_' . $prop, true );
		} else {
			$value = $this->$prop;
		}

		return $value;
	}

	protected function get_date_prop( $date_type ) {

		$datetime = parent::get_date_prop( $date_type );

		if ( ! isset( $this->schedule->{$date_type} ) ) {
			if ( ! is_object( $datetime ) ) {
				$this->schedule->{$date_type} = 0;
			} else {
				$this->schedule->{$date_type} = hf_get_datetime_utc_string( $datetime );
			}
		}

		return hf_get_datetime_from( hf_date_to_time( $datetime ) );
	}

	public function set_id( $id ) {
		$this->id = absint( $id );
	}

	public function set_parent_id( $value ) {
		wp_update_post( array(	'ID'  => $this->id, 'post_parent' => $value) );
		$this->post->post_parent = $value;
		$this->order = null;
	}

	public function set_status( $new_status, $note = '', $manual_update = false ) {

		$old_status = $this->get_status();
		$prefix     = substr( $new_status, 0, 3 );
		$new_status = 'wc-' === $prefix ? substr( $new_status, 3 ) : $new_status;

		wp_update_post( array( 'ID' => $this->get_id(), 'post_status' => hf_maybe_prefix_key( $new_status, 'wc-' ) ) );
		$this->post_status = $this->post->post_status = hf_maybe_prefix_key( $new_status, 'wc-' );

		if ( $old_status !== $new_status ) {
			$this->status_transition = array(
				'from'   => ! empty( $this->status_transition['from'] ) ? $this->status_transition['from'] : $old_status,
				'to'     => $new_status,
				'note'   => $note,
				'manual' => (bool) $manual_update,
			);
		}

		return array(
			'from' => $old_status,
			'to'   => $new_status,
		);
	}

	protected function set_prop( $prop, $value ) {

		if ( 'switch_data' == $prop ) {
			$prop = 'subscription_switch_data';
		}

		$this->$prop = $value;
		if ( 'requires_manual_renewal' === $prop ) {
			if ( false === $value || '' === $value ) {
				$value = 'false';
			} else {
				$value = 'true';
			}
		}

		update_post_meta( $this->get_id(), '_' . $prop, $value );
	}

	protected function set_date_prop( $date_type, $value ) {
		$datetime = hf_get_datetime_from( $value );
		$date     = ! is_null( $datetime ) ? hf_get_datetime_utc_string( $datetime ) : 0;

		$this->set_prop( $this->get_date_prop_key( $date_type ), $date );
		$this->schedule->{$date_type} = $date;
	}

	protected function set_last_order_date( $date_type, $date = null ) {

		$last_order = $this->get_last_order( 'all' );
		if ( $last_order ) {
			$datetime = hf_get_datetime_from( $date );
			switch ( $date_type ) {
				case 'date_paid' :
					update_post_meta( $last_order->id, '_paid_date', ! is_null( $date ) ? $datetime->date( 'Y-m-d H:i:s' ) : '' );
					update_post_meta( $last_order->id, '_date_paid', ! is_null( $date ) ? $datetime->getTimestamp() : '' );
				break;

				case 'date_completed' :
					update_post_meta( $last_order->id, '_completed_date', ! is_null( $date ) ? $datetime->date( 'Y-m-d H:i:s' ) : '' );
					update_post_meta( $last_order->id, '_date_completed', ! is_null( $date ) ? $datetime->getTimestamp() : '' );
				break;

				case 'date_modified' :
					wp_update_post( array(
						'ID'                => $last_order->id,
						'post_modified'     => $datetime->date( 'Y-m-d H:i:s' ),
						'post_modified_gmt' => hf_get_datetime_utc_string( $datetime ),
					) );
				break;

				case 'date_created' :
					wp_update_post( array(
						'ID'            => $last_order->id,
						'post_date'     => $datetime->date( 'Y-m-d H:i:s' ),
						'post_date_gmt' => hf_get_datetime_utc_string( $datetime ),
					) );
				break;
			}
		}
	}

        public function save_dates() {
	}
        
	public function set_date_created( $date = null ) {
		global $wpdb;
		if ( ! is_null( $date ) ) {
			$datetime_string = hf_get_datetime_utc_string( hf_get_datetime_from( $date ) );
                        $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_date = %s, post_date_gmt = %s WHERE ID = %d", get_date_from_gmt( $datetime_string ), $datetime_string, $this->get_id() ) );
			$this->post->post_date     = get_date_from_gmt( $datetime_string );
			$this->post->post_date_gmt = $datetime_string;
		}
	}

	public function set_discount_total( $value ) {
		$this->set_total( $value, 'cart_discount' );
	}

	public function set_discount_tax( $value ) {
		$this->set_total( $value, 'cart_discount_tax' );
	}

	public function set_shipping_total( $value ) {
		$this->set_total( $value, 'shipping' );
	}

	public function set_shipping_tax( $value ) {
		$this->set_total( $value, 'shipping_tax' );
	}

	public function set_cart_tax( $value ) {
		$this->set_total( $value, 'tax' );
	}

	public function save() {
		$this->status_transition();
		return $this->get_id();
	}

	public function update_meta_data( $key, $value, $meta_id = '' ) {
		if ( ! empty( $meta_id ) ) {
			update_metadata_by_mid( 'post', $meta_id, $value, $key );
		} else {
			update_post_meta( $this->get_id(), $key, $value );
		}
	}

}