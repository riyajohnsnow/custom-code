<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// edit subscription schedule meta box

?>
<div class="wc-metaboxes-wrapper">
	<div id="billing-schedule">
		<?php if ( $the_subscription->can_date_be_updated( 'next_payment' ) ) : ?>
		<div class="billing-schedule-edit hf-date-input"><?php
			
			echo woocommerce_wp_select( array(
				'id'          => '_billing_interval',
				'class'       => 'billing_interval',
				'label'       => __( 'Recurring:', HF_Subscriptions::TEXT_DOMAIN ),
				'value'       => $the_subscription->get_billing_interval(),
				'options'     => hf_get_subscription_period_interval_strings(),
				)
			);

			echo woocommerce_wp_select( array(
				'id'          => '_billing_period',
				'class'       => 'billing_period',
				'label'       => __( 'Billing Period', HF_Subscriptions::TEXT_DOMAIN ),
				'value'       => $the_subscription->get_billing_period(),
				'options'     => hf_get_subscription_period_strings(),
				)
			);
			?>
			<input type="hidden" name="hf-lengths" id="hf-lengths" data-subscription_lengths="<?php echo esc_attr( hf_json_encode( hf_get_subscription_ranges() ) ); ?>">
		</div>
		<?php else : ?>
		<strong><?php esc_html_e( 'Recurring:', HF_Subscriptions::TEXT_DOMAIN ); ?></strong>
		<?php printf( '%s %s', esc_html( hf_get_subscription_period_interval_strings( $the_subscription->get_billing_interval() ) ), esc_html( hf_get_subscription_period_strings( 1, $the_subscription->get_billing_period() ) ) ); ?>
	<?php endif; ?>
	</div>

	<?php foreach ( hf_get_subscription_available_date_types() as $date_key => $date_label ) : ?>
		<?php $internal_date_key = hf_normalise_date_type_key( $date_key ) ?>
		<?php if ( false === hf_display_date_type( $date_key, $the_subscription ) ) : ?>
			<?php continue; ?>
		<?php endif;?>
	<div id="subscription-<?php echo esc_attr( $date_key ); ?>-date" class="date-fields">
		<strong><?php echo esc_html( $date_label ); ?>:</strong>
		<input type="hidden" name="<?php echo esc_attr( $date_key ); ?>_timestamp_utc" id="<?php echo esc_attr( $date_key ); ?>_timestamp_utc" value="<?php echo esc_attr( $the_subscription->get_time( $internal_date_key, 'gmt' ) ); ?>"/>
		<?php if ( $the_subscription->can_date_be_updated( $internal_date_key ) ) : ?>
			<?php echo wp_kses( hf_date_input( $the_subscription->get_time( $internal_date_key, 'site' ), array( 'name_attr' => $date_key ) ), array( 'input' => array( 'type' => array(), 'class' => array(), 'placeholder' => array(), 'name' => array(), 'id' => array(), 'maxlength' => array(), 'size' => array(), 'value' => array(), 'patten' => array() ), 'div' => array( 'class' => array() ), 'span' => array(), 'br' => array() ) ); ?>
		<?php else : ?>
			<?php echo esc_html( $the_subscription->get_date_to_display( $internal_date_key ) ); ?>
		<?php endif; ?>
	</div>
	<?php endforeach; ?>
	<p><?php esc_html_e( 'Timezone:', HF_Subscriptions::TEXT_DOMAIN ); ?> <span id="hf-timezone-em"><?php esc_html_e( 'Error: unable to find timezone of your browser.', HF_Subscriptions::TEXT_DOMAIN ); ?></span></p>
</div>