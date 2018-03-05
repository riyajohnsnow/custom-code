<?php
if (!defined('ABSPATH')) {
    exit;
}

// subscription information template
?>
<?php if (!empty($subscriptions)) : ?>
    <h2><?php esc_html_e('Subscription Information:', HF_Subscriptions::TEXT_DOMAIN); ?></h2>
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
        <thead>
            <tr>
                <th class="td" scope="col" style="text-align:left;"><?php esc_html_e('Subscription', HF_Subscriptions::TEXT_DOMAIN); ?></th>
                <th class="td" scope="col" style="text-align:left;"><?php echo esc_html_x('Start Date', 'table heading', HF_Subscriptions::TEXT_DOMAIN); ?></th>
                <th class="td" scope="col" style="text-align:left;"><?php echo esc_html_x('End Date', 'table heading', HF_Subscriptions::TEXT_DOMAIN); ?></th>
                <th class="td" scope="col" style="text-align:left;"><?php echo esc_html_x('Price', 'table heading', HF_Subscriptions::TEXT_DOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subscriptions as $subscription) : ?>
                <tr>
                    <td class="td" scope="row" style="text-align:left;"><a href="<?php echo esc_url(( $is_admin_email ) ? hf_get_edit_post_link($subscription->get_id()) : $subscription->get_view_order_url() ); ?>"><?php echo sprintf(esc_html_x('#%s', 'subscription number in email table. (eg: #106)', HF_Subscriptions::TEXT_DOMAIN), esc_html($subscription->get_order_number())); ?></a></td>
                    <td class="td" scope="row" style="text-align:left;"><?php echo esc_html(date_i18n(wc_date_format(), $subscription->get_time('date_created', 'site'))); ?></td>
                    <td class="td" scope="row" style="text-align:left;"><?php echo esc_html(( 0 < $subscription->get_time('end') ) ? date_i18n(wc_date_format(), $subscription->get_time('end', 'site')) : _x('When Cancelled', 'Used as end date for an indefinite subscription', HF_Subscriptions::TEXT_DOMAIN) ); ?></td>
                    <td class="td" scope="row" style="text-align:left;"><?php echo wp_kses_post($subscription->get_formatted_order_total()); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>