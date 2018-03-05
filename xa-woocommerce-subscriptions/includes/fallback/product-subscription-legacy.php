<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_Product_Subscription_Legacy extends WC_Product_Subscription {

	var $subscription_price;
	var $subscription_period;
	var $subscription_period_interval;
	var $subscription_length;

 
	public function __construct( $product ) {
		parent::__construct( $product );
		$this->product_type = 'subscription';

		$this->product_custom_fields = get_post_meta( $this->id );

		if ( ! empty( $this->product_custom_fields['_hf_subscription_price'][0] ) ) {
			$this->subscription_price = $this->product_custom_fields['_hf_subscription_price'][0];
		}

		if ( ! empty( $this->product_custom_fields['_subscription_period'][0] ) ) {
			$this->subscription_period = $this->product_custom_fields['_subscription_period'][0];
		}

		if ( ! empty( $this->product_custom_fields['_subscription_period_interval'][0] ) ) {
			$this->subscription_period_interval = $this->product_custom_fields['_subscription_period_interval'][0];
		}

		if ( ! empty( $this->product_custom_fields['_subscription_length'][0] ) ) {
			$this->subscription_length = $this->product_custom_fields['_subscription_length'][0];
		}


	}
}