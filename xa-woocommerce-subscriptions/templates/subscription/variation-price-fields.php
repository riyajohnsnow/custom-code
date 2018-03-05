<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="variable_subscription_pricing variable_subscription_pricing_2_3 show_if_variable-subscription">
	<p class="form-row form-row-first form-field show_if_variable-subscription _subscription_price_field">
		<label for="variable_subscription_price[<?php echo esc_attr( $loop ); ?>]">
			<?php
			printf( esc_html__( 'Subscription price (%s)', HF_Subscriptions::TEXT_DOMAIN ), esc_html( get_woocommerce_currency_symbol() ) );
			?>
		</label>
		<input type="text" class="wc_input_price wc_input_subscription_price" name="variable_subscription_price[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( HF_Subscriptions_Product::get_price( $variation_product ) ); ?>" placeholder="<?php echo esc_attr_x( 'e.g. 89', 'example price', HF_Subscriptions::TEXT_DOMAIN ); ?>">
		
		<label for="variable_subscription_period_interval[<?php echo esc_attr( $loop ); ?>]" class="hf_hidden_label"><?php esc_html_e( 'Billing interval:', HF_Subscriptions::TEXT_DOMAIN ); ?></label>
		<select name="variable_subscription_period_interval[<?php echo esc_attr( $loop ); ?>]" class="wc_input_subscription_period_interval">
		<?php foreach ( hf_get_subscription_period_interval_strings() as $key => $value ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, HF_Subscriptions_Product::get_interval( $variation_product ) ); ?>><?php echo esc_html( $value ); ?></option>
		<?php endforeach; ?>
		</select>

		<label for="variable_subscription_period[<?php echo esc_attr( $loop ); ?>]" class="hf_hidden_label"><?php esc_html_e( 'Billing Period:', HF_Subscriptions::TEXT_DOMAIN ); ?></label>
		<select name="variable_subscription_period[<?php echo esc_attr( $loop ); ?>]" class="wc_input_subscription_period">
		<?php foreach ( hf_get_subscription_period_strings() as $key => $value ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $billing_period ); ?>><?php echo esc_html( $value ); ?></option>
		<?php endforeach; ?>
		</select>

	</p>
	<p class="form-row form-row-last show_if_variable-subscription _subscription_length_field">
		<label for="variable_subscription_length[<?php echo esc_attr( $loop ); ?>]">
			<?php esc_html_e( 'Subscription length', HF_Subscriptions::TEXT_DOMAIN ); ?>
			<?php echo hf_help_tip( __( 'Automatically expire the subscription after this length of time. This length is in addition to any free trial or amount of time provided before a synchronised first renewal date.', HF_Subscriptions::TEXT_DOMAIN ) ); ?>
		</label>
		<select name="variable_subscription_length[<?php echo esc_attr( $loop ); ?>]" class="wc_input_subscription_length">
		<?php foreach ( hf_get_subscription_ranges( $billing_period ) as $key => $value ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, HF_Subscriptions_Product::get_length( $variation_product ) ); ?>> <?php echo esc_html( $value ); ?></option>
		<?php endforeach; ?>
		</select>
	</p>
</div>