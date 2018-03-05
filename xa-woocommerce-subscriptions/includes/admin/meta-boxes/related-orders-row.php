<?php
if (!defined('ABSPATH')) {
    exit;
}

$order_post = hf_get_objects_property($order, 'post');
?>
<tr>
    <td>
        <a href="<?php echo esc_url(get_edit_post_link(hf_get_objects_property($order, 'id'))); ?>">
<?php echo sprintf(esc_html_x('#%s', 'hash before order number', HF_Subscriptions::TEXT_DOMAIN), esc_html($order->get_order_number())); ?>
        </a>
    </td>
    <td>
<?php echo esc_html(hf_get_objects_property($order, 'relationship')); ?>
    </td>
    <td>
        <?php
        $timestamp_gmt = hf_get_objects_property($order, 'date_created')->getTimestamp();
        if ($timestamp_gmt > 0) {
            $t_time = get_the_time(__('Y/m/d g:i:s A', HF_Subscriptions::TEXT_DOMAIN), $order_post);
            $date_to_display = hf_get_readable_time_diff($timestamp_gmt);
        } else {
            $t_time = $date_to_display = __('Unpublished', HF_Subscriptions::TEXT_DOMAIN);
        }
        ?>
        <abbr title="<?php echo esc_attr($t_time); ?>">
        <?php echo esc_html($date_to_display); ?>
        </abbr>
    </td>
    <td>
<?php echo esc_html(ucwords($order->get_status())); ?>
    </td>
    <td>
        <span class="amount"><?php echo wp_kses($order->get_formatted_order_total(), array('small' => array(), 'span' => array('class' => array()), 'del' => array(), 'ins' => array())); ?></span>
    </td>
</tr>