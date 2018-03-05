<?php
if (!defined('ABSPATH')) {
    exit;
}

 global $post;

        $chosen_price = get_post_meta($post->ID, '_hf_subscription_price', true);
        $chosen_interval = get_post_meta($post->ID, '_subscription_period_interval', true);

        $price_tooltip = __('Choose the subscription price, billing interval and billing period.', HF_Subscriptions::TEXT_DOMAIN);

        if (!$chosen_period = get_post_meta($post->ID, '_subscription_period', true)) {
            $chosen_period = 'month';
        }

        echo '<div class="options_group subscription_pricing show_if_subscription">';

        ?><p class="form-field _subscription_price_fields _hf_subscription_price_field">
            <label for="_hf_subscription_price"><?php printf(esc_html__('Subscription price (%s)', HF_Subscriptions::TEXT_DOMAIN), esc_html(get_woocommerce_currency_symbol())); ?></label>
            <span class="wrap">
                <input type="text" id="_hf_subscription_price" name="_hf_subscription_price" class="wc_input_price wc_input_hf_subscription_price" placeholder="<?php echo esc_attr_x('e.g. 69', 'example price', HF_Subscriptions::TEXT_DOMAIN); ?>" step="any" min="0" value="<?php echo esc_attr($chosen_price); ?>" />
                <label for="_subscription_period_interval" class="hf_hidden_label"><?php esc_html_e('Subscription interval', HF_Subscriptions::TEXT_DOMAIN); ?></label>
                <select id="_subscription_period_interval" name="_subscription_period_interval" class="wc_input_subscription_period_interval">
        <?php foreach (hf_get_subscription_period_interval_strings() as $value => $label) { ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $chosen_interval, true) ?>><?php echo esc_html($label); ?></option>
                    <?php } ?>
                </select>
                <label for="_subscription_period" class="hf_hidden_label"><?php esc_html_e('Subscription period', HF_Subscriptions::TEXT_DOMAIN); ?></label>
                <select id="_subscription_period" name="_subscription_period" class="wc_input_subscription_period last" >
        <?php foreach (hf_get_subscription_period_strings() as $value => $label) { ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $chosen_period, true) ?>><?php echo esc_html($label); ?></option>
                    <?php } ?>
                </select>
            </span>
        <?php echo hf_help_tip($price_tooltip); ?>
        </p>
            <?php
            woocommerce_wp_select(array(
                'id' => '_subscription_length',
                'class' => 'wc_input_subscription_length select short',
                'label' => __('Subscription length', HF_Subscriptions::TEXT_DOMAIN),
                'options' => hf_get_subscription_ranges($chosen_period),
                'desc_tip' => true,
                'description' => __('Automatically expire the subscription after this length of time.', HF_Subscriptions::TEXT_DOMAIN),
                    )
            );
            ?>

            <?php
            do_action('hf_subscription_product_options_pricing');
            wp_nonce_field('hf_subscription_meta', '_hfnonce');
            echo '</div>';
            echo '<div class="show_if_subscription clear"></div>';