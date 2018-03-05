<?php
/**
 * Grouped product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/grouped.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @author      WooThemes
 * @package     WooCommerce/Templates
 * @version     3.0.7
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product, $post;

do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<div class="clear"></div>
<div class="bundle_product_wrapper tab">
    <div class="bundle_product_title">Datasets included in the Processes bundle</div>
    <div class="bundle_products_form">
        <form class="cart" method="post" enctype='multipart/form-data'>
            <div class="overflow-body">
                <table cellspacing="0" class="group_table">
                <tbody>
                    <!--Top row for select all and total price-->
                    <tr>
<!--                        <td>
                            <input type="checkbox" name="select_all" value="1" id="wc-selectall-checkbox" class="select-all"/>
                        </td>
                        <td class="label">
                            <label>Select All Products Inside Bundle</label>
                        </td>
                        <td class="price">
                            <?php
                            /*$total_bundle_price = 0;
                            foreach ( $grouped_products as $grouped_product ) {
                                $total_bundle_price  += $grouped_product->get_price();
                            }*/
                            ?>
                            <span class="woocommerce-Price-amount amount">
                                <span class="woocommerce-Price-currencySymbol">$</span>
                                <?php //echo $total_bundle_price; ?>
                            </span>
                        </td> -->
                    </tr>
                    <?php
                        $quantites_required = false;
                        $previous_post      = $post;
                        $totalPrice         = 0;
                        foreach ( $grouped_products as $grouped_product ) {
                            $post_object        = get_post( $grouped_product->get_id() );
                            $quantites_required = $quantites_required || ( $grouped_product->is_purchasable() && ! $grouped_product->has_options() );
                            $totalPrice += $grouped_product->get_price();
    
                            setup_postdata( $post = $post_object );
                            ?>
                            <tr id="product-<?php the_ID(); ?>" <?php post_class(); ?>>
                                <td style="display: none">
                                    <?php if ( ! $grouped_product->is_purchasable() || $grouped_product->has_options() ) : ?>
                                        <?php woocommerce_template_loop_add_to_cart(); ?>
    
                                    <?php elseif ( $grouped_product->is_sold_individually() ) : ?>
                                        <input type="checkbox" name="<?php echo esc_attr( 'quantity[' . $grouped_product->get_id() . ']' ); ?>" value="1"
                                               class="wc-grouped-product-add-to-cart-checkbox group_product_checkbox"
                                               data-price="<?php echo $grouped_product->get_price(); ?>" checked/>
    
                                    <?php else : ?>
                                        <?php
                                            /**
                                             * @since 3.0.0.
                                             */
                                            do_action( 'woocommerce_before_add_to_cart_quantity' );
    
                                            woocommerce_quantity_input( array(
                                                'input_name'  => 'quantity[' . $grouped_product->get_id() . ']',
                                                'input_value' => isset( $_POST['quantity'][ $grouped_product->get_id() ] ) ? wc_stock_amount( $_POST['quantity'][ $grouped_product->get_id() ] ) : 0,
                                                'min_value'   => apply_filters( 'woocommerce_quantity_input_min', 0, $grouped_product ),
                                                'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $grouped_product->get_max_purchase_quantity(), $grouped_product ),
                                            ) );
    
                                            /**
                                             * @since 3.0.0.
                                             */
                                            do_action( 'woocommerce_after_add_to_cart_quantity' );
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td class="label">
                                    <label for="product-<?php echo $grouped_product->get_id(); ?>">
                                        <?php echo $grouped_product->is_visible() ? '<a href="' . esc_url( apply_filters( 'woocommerce_grouped_product_list_link', get_permalink( $grouped_product->get_id() ), $grouped_product->get_id() ) ) . '">' . $grouped_product->get_name() . '</a>' : $grouped_product->get_name(); ?>
                                    </label>
                                </td>
                                <?php do_action( 'woocommerce_grouped_product_list_before_price', $grouped_product ); ?>
                                <td class="price">
                                    <?php
                                        echo $grouped_product->get_price_html();
                                        echo wc_get_stock_html( $grouped_product );
                                    ?>
                                </td>
                            </tr>
                            <?php
                        }
                        // Return data to original post.
                        setup_postdata( $post = $previous_post );
                    ?>
                </tbody>
            </table>
            </div>
    
            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" />
            <div class="bundle-form-footer">
                <div class="add-to-cart-btn">
            <?php if ( $quantites_required ) : ?>
    
                <?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

                <button type="submit" class="single_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
    
                <?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

            <?php endif ?>
                </div>

            <div class="total_group_price">
                <b>Total Price :</b>
                <span class="woocommerce-Price-currencySymbol">$</span>
                <span id="total_group_price"><?php echo $totalPrice; ?></span>
            </div>
            </div>
        </form>
    </div>
</div>
<script>
    jQuery(document).on('click', '#wc-selectall-checkbox', function (event) {
        jQuery(".group_product_checkbox").prop('checked', jQuery(this).prop("checked"));
        jQuery("#total_group_price").html(0.00);
        if(this.checked) jQuery(".group_product_checkbox").trigger("change");
    });
    jQuery(document).on('change', ".group_product_checkbox", function (event) {
        var totalPrice = parseInt(jQuery("#total_group_price").html());
        var productPrice = parseInt(jQuery(this).attr('data-price'));
        if(this.checked)  totalPrice += productPrice;
        else totalPrice -= productPrice;
        jQuery("#total_group_price").html(totalPrice);
    });
</script>
<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
