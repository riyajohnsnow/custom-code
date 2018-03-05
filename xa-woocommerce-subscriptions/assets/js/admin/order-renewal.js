jQuery(document).ready(function () {
    jQuery('body.post-type-shop_order #post').submit(function () {
        if ('hf_retry_renewal_payment' == jQuery("body.post-type-shop_order select[name='wc_order_action']").val()) {
            return confirm(hf_admin_order_meta_boxes.retry_renewal_payment_action_warning);
        }
    });
});