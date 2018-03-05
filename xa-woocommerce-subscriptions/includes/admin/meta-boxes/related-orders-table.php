<?php
if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="hf_subscription_related_orders">
    <table>
        <thead>
            <tr>
                <th><?php esc_html_e('Order#', HF_Subscriptions::TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Relation', HF_Subscriptions::TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Date', HF_Subscriptions::TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Status', HF_Subscriptions::TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Total', HF_Subscriptions::TEXT_DOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php do_action('hf_subscription_related_orders_meta_box_rows', $post); ?>
        </tbody>
    </table>
</div>