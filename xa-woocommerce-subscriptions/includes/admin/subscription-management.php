<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} 

if ( class_exists( 'HF_Admin_Post_Types' ) ) {
	return new HF_Admin_Post_Types();
}


class HF_Admin_Post_Types {


	public function __construct() {

		add_filter( 'manage_edit-hf_shop_subscription_columns', array( $this, 'hf_shop_subscription_columns' ) );
		add_filter( 'manage_edit-hf_shop_subscription_sortable_columns', array( $this, 'hf_shop_subscription_sortable_columns' ) );
		add_action( 'manage_hf_shop_subscription_posts_custom_column', array( $this, 'render_hf_shop_subscription_columns' ), 2 );

		add_filter( 'bulk_actions-edit-hf_shop_subscription', array( $this, 'remove_bulk_actions' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'render_bulk_actions_script' ) );
		add_action( 'load-edit.php', array( $this, 'parse_bulk_actions' ) );
		add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );

		// subscription order filter
		add_filter( 'request', array( $this, 'request_query' ) );

		// subscription search
		add_filter( 'get_search_query', array( $this, 'hf_shop_subscription_search_label' ) );
		add_filter( 'query_vars', array( $this, 'add_custom_query_var' ) );
		add_action( 'parse_query', array( $this, 'hf_shop_subscription_search_custom_fields' ) );

		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );

		add_action( 'restrict_manage_posts', array( $this, 'restrict_by_product' ) );
		add_action( 'restrict_manage_posts', array( $this, 'restrict_by_payment_method' ) );

		add_action( 'list_table_primary_column', array( $this, 'list_table_primary_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'hf_shop_subscription_row_actions' ), 10, 2 );
	}



	public function posts_clauses( $pieces, $query ) {
		global $wpdb;

		if ( ! is_admin() || ! isset( $query->query['post_type'] ) || 'hf_shop_subscription' !== $query->query['post_type'] ) {
			return $pieces;
		}

		if ( $this->is_db_user_privileged() ) {
			$pieces = self::posts_clauses_high_performance( $pieces );
		} else {
			$pieces = self::posts_clauses_low_performance( $pieces );
		}

		$order = strtoupper( $query->query['order'] );

		$pieces['fields'] .= ', COALESCE(lp.last_payment, o.post_date_gmt, 0) as lp';
		$pieces['orderby'] = "CAST(lp AS DATETIME) {$order}";

		return $pieces;
	}

	public function is_db_user_privileged() {
            
		$permissions = $this->get_special_database_privileges();
		return ( in_array( 'CREATE TEMPORARY TABLES', $permissions ) && in_array( 'INDEX', $permissions ) && in_array( 'DROP', $permissions ) );
	}


	public function get_special_database_privileges() {
            
		global $wpdb;
		$permissions = $wpdb->get_col( "SELECT PRIVILEGE_TYPE FROM information_schema.user_privileges WHERE GRANTEE = CONCAT( '''', REPLACE( CURRENT_USER(), '@', '''@''' ), '''' ) AND PRIVILEGE_TYPE IN ('CREATE TEMPORARY TABLES', 'INDEX', 'DROP')" );
		return $permissions;
	}

	private function posts_clauses_low_performance( $pieces ) {
            
		global $wpdb;
		$pieces['join'] .= "LEFT JOIN
				(SELECT
					MAX( p.post_date_gmt ) as last_payment,
					pm.meta_value
				FROM {$wpdb->postmeta} pm
				LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_subscription_renewal'
				GROUP BY pm.meta_value) lp
			ON {$wpdb->posts}.ID = lp.meta_value
			LEFT JOIN {$wpdb->posts} o on {$wpdb->posts}.post_parent = o.ID";

		return $pieces;
	}

	private function posts_clauses_high_performance( $pieces ) {
            
		global $wpdb;
		$session = wp_get_session_token();
		$table_name = substr( "{$wpdb->prefix}tmp_{$session}_lastpayment", 0, 64 );
		$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS {$table_name}" );

		$wpdb->query(
			"CREATE TEMPORARY TABLE {$table_name} (id INT, INDEX USING BTREE (id), last_payment DATETIME) AS
			 SELECT pm.meta_value as id, MAX( p.post_date_gmt ) as last_payment FROM {$wpdb->postmeta} pm
			 LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_subscription_renewal'
			 GROUP BY pm.meta_value" );

		$pieces['join'] .= "LEFT JOIN {$table_name} lp
			ON {$wpdb->posts}.ID = lp.id
			LEFT JOIN {$wpdb->posts} o on {$wpdb->posts}.post_parent = o.ID";

		return $pieces;
	}


	public function restrict_by_product() {
            
		global $typenow;
		if ( 'hf_shop_subscription' !== $typenow ) {
			return;
		}

		$product_id = '';
		$product_string = '';

		if ( ! empty( $_GET['_hf_product'] ) ) {
			$product_id     = absint( $_GET['_hf_product'] );
			$product_string = wc_get_product( $product_id )->get_formatted_name();
		}

		HF_Select2::render( array(
			'class'       => 'wc-product-search',
			'name'        => '_hf_product',
			'placeholder' => esc_attr__( 'Search for a product&hellip;', HF_Subscriptions::TEXT_DOMAIN ),
			'action'      => 'woocommerce_json_search_products_and_variations',
			'selected'    => strip_tags( $product_string ),
			'value'       => $product_id,
			'allow_clear' => 'true',
		) );
	}


	public function remove_bulk_actions( $actions ) {

		if ( isset( $actions['edit'] ) ) {
			unset( $actions['edit'] );
		}
		return $actions;
	}

	public function render_bulk_actions_script() {

		$post_status = ( isset( $_GET['post_status'] ) ) ? $_GET['post_status'] : '';

		if ( 'hf_shop_subscription' !== get_post_type() || in_array( $post_status, array( 'cancelled', 'trash', 'wc-expired' ) ) ) {
			return;
		}

		$bulk_actions = apply_filters( 'hf_subscription_bulk_actions', array(
			'active'    => __( 'Activate', HF_Subscriptions::TEXT_DOMAIN ),
			'on-hold'   => __( 'Put on-hold', HF_Subscriptions::TEXT_DOMAIN ),
			'cancelled' => __( 'Cancel', HF_Subscriptions::TEXT_DOMAIN ),
		) );

		switch ( $post_status ) {
			case 'wc-active' :
				unset( $bulk_actions['active'] );
				break;
			case 'wc-on-hold' :
				unset( $bulk_actions['on-hold'] );
				break;
		}

		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				<?php
				foreach ( $bulk_actions as $action => $title ) {
					?>
					$('<option>')
						.val('<?php echo esc_attr( $action ); ?>')
						.text('<?php echo esc_html( $title ); ?>')
						.appendTo("select[name='action'], select[name='action2']" );
					<?php
				}
				?>
			});
		</script>
		<?php
	}


	public function parse_bulk_actions() {

		if ( ! isset( $_REQUEST['post_type'] ) || 'hf_shop_subscription' !== $_REQUEST['post_type'] || ! isset( $_REQUEST['post'] ) ) {
			return;
		}
		$action = '';
		if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
			$action = $_REQUEST['action'];
		} else if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
			$action = $_REQUEST['action2'];
		}

		switch ( $action ) {
			case 'active':
			case 'on-hold':
			case 'cancelled' :
				$new_status = $action;
				break;
			default:
				return;
		}

		$report_action = 'marked_' . $new_status;
		$changed = 0;
		$subscription_ids = array_map( 'absint', (array) $_REQUEST['post'] );
		$sendback_args = array(
			'post_type'    => 'hf_shop_subscription',
			$report_action => true,
			'ids'          => join( ',', $subscription_ids ),
			'error_count'  => 0,
		);

		foreach ( $subscription_ids as $subscription_id ) {
			$subscription = hf_get_subscription( $subscription_id );
			$order_note   = __( 'Subscription status changed by bulk edit:', HF_Subscriptions::TEXT_DOMAIN );

			try {

				if ( 'cancelled' == $action ) {
					$subscription->cancel_order( $order_note );
				} else {
					$subscription->update_status( $new_status, $order_note, true );
				}

				switch ( $action ) {
					case 'active' :
					case 'on-hold' :
					case 'cancelled' :
					case 'trash' :
						do_action( 'woocommerce_admin_changed_subscription_to_' . $action, $subscription_id );
						break;
				}

				$changed++;

			} catch ( Exception $e ) {
				$sendback_args['error'] = urlencode( $e->getMessage() );
				$sendback_args['error_count']++;
			}
		}

		$sendback_args['changed'] = $changed;
		$sendback = add_query_arg( $sendback_args, wp_get_referer() ? wp_get_referer() : '' );
		wp_safe_redirect( esc_url_raw( $sendback ) );

		exit();
	}


	public function bulk_admin_notices() {
		global $post_type, $pagenow;

		if ( 'edit.php' !== $pagenow || 'hf_shop_subscription' !== $post_type ) {
			return;
		}

		$subscription_statuses = hf_get_subscription_statuses();

		foreach ( $subscription_statuses as $slug => $name ) {

			if ( isset( $_REQUEST[ 'marked_' . str_replace( 'wc-', '', $slug ) ] ) ) {

				$number = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;

				$message = sprintf( _n( '%s subscription status changed.', '%s subscription statuses changed.', $number, HF_Subscriptions::TEXT_DOMAIN ), number_format_i18n( $number ) );
				echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';

				if ( ! empty( $_REQUEST['error_count'] ) ) {
					$error_msg = isset( $_REQUEST['error'] ) ? stripslashes( $_REQUEST['error'] ) : '';
					$error_count = isset( $_REQUEST['error_count'] ) ? absint( $_REQUEST['error_count'] ) : 0;
					$message = sprintf( _n( '%1$s subscription could not be updated: %2$s', '%1$s subscriptions could not be updated: %2$s', $error_count, HF_Subscriptions::TEXT_DOMAIN ), number_format_i18n( $error_count ), $error_msg );
					echo '<div class="error"><p>' . esc_html( $message ) . '</p></div>';
				}

				$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'error_count', 'marked_active' ), $_SERVER['REQUEST_URI'] );

				break;
			}
		}
	}

                                        
	public function hf_shop_subscription_columns( $existing_columns ) {

		$columns = array(
			'cb'                => '<input type="checkbox" />',
			'status'            => __( 'Status', HF_Subscriptions::TEXT_DOMAIN ),
			'order_title'       => __( 'Subscription', HF_Subscriptions::TEXT_DOMAIN ),
			'order_items'       => __( 'Items', HF_Subscriptions::TEXT_DOMAIN ),
			'recurring_total'   => __( 'Total', HF_Subscriptions::TEXT_DOMAIN ),
			'start_date'        => __( 'Start Date', HF_Subscriptions::TEXT_DOMAIN ),
			'next_payment_date' => __( 'Next Payment', HF_Subscriptions::TEXT_DOMAIN ),
			'last_payment_date' => __( 'Last Order Date', HF_Subscriptions::TEXT_DOMAIN ),
			'end_date'          => __( 'End Date', HF_Subscriptions::TEXT_DOMAIN ),
			'orders'            => __( 'Orders', HF_Subscriptions::TEXT_DOMAIN ),
		);

		return $columns;
	}

	public function render_hf_shop_subscription_columns( $column ) {
		global $post, $the_subscription, $wp_list_table;

		if ( empty( $the_subscription ) || $the_subscription->get_id() != $post->ID ) {
			$the_subscription = hf_get_subscription( $post->ID );
		}

		$column_content = '';

		switch ( $column ) {
			case 'status' :
				
				$column_content = sprintf( '<mark class="%s">%s</mark>', sanitize_title( $the_subscription->get_status() ), hf_get_subscription_status_name( $the_subscription->get_status() ) );

				$post_type_object = get_post_type_object( $post->post_type );
				$actions = array();
				$action_url = add_query_arg(
					array(
						'post'     => $the_subscription->get_id(),
						'_wpnonce' => wp_create_nonce( 'bulk-posts' ),
					)
				);

				if ( isset( $_REQUEST['status'] ) ) {
					$action_url = add_query_arg( array( 'status' => $_REQUEST['status'] ), $action_url );
				}

				$all_statuses = array(
					'active'    => __( 'Reactivate', HF_Subscriptions::TEXT_DOMAIN ),
					'on-hold'   => __( 'Suspend', HF_Subscriptions::TEXT_DOMAIN ),
					'cancelled' => __( 'Cancel', HF_Subscriptions::TEXT_DOMAIN ),
					'trash'     => __( 'Trash', HF_Subscriptions::TEXT_DOMAIN ),
					'deleted'   => __( 'Delete Permanently', HF_Subscriptions::TEXT_DOMAIN ),
				);

				foreach ( $all_statuses as $status => $label ) {

					if ( $the_subscription->can_be_updated_to( $status ) ) {

						if ( in_array( $status, array( 'trash', 'deleted' ) ) ) {

							if ( current_user_can( $post_type_object->cap->delete_post, $post->ID ) ) {

								if ( 'trash' == $post->post_status ) {
									$actions['untrash'] = '<a title="' . esc_attr( __( 'Restore this item from the Trash', HF_Subscriptions::TEXT_DOMAIN ) ) . '" href="' . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ) . '">' . __( 'Restore', HF_Subscriptions::TEXT_DOMAIN ) . '</a>';
								} elseif ( EMPTY_TRASH_DAYS ) {
									$actions['trash'] = '<a class="submitdelete" title="' . esc_attr( __( 'Move this item to the Trash', HF_Subscriptions::TEXT_DOMAIN ) ) . '" href="' . get_delete_post_link( $post->ID ) . '">' . __( 'Trash', HF_Subscriptions::TEXT_DOMAIN ) . '</a>';
								}

								if ( 'trash' == $post->post_status || ! EMPTY_TRASH_DAYS ) {
									$actions['delete'] = '<a class="submitdelete" title="' . esc_attr( __( 'Delete this item permanently', HF_Subscriptions::TEXT_DOMAIN ) ) . '" href="' . get_delete_post_link( $post->ID, '', true ) . '">' . __( 'Delete Permanently', HF_Subscriptions::TEXT_DOMAIN ) . '</a>';
								}
							}
						} else {

							if ( 'pending-cancel' === $the_subscription->get_status() ) {
								$label = __( 'Cancel Now', HF_Subscriptions::TEXT_DOMAIN );
							}

							$actions[ $status ] = sprintf( '<a href="%s">%s</a>', add_query_arg( 'action', $status, $action_url ), $label );

						}
					}
				}

				if ( 'pending' === $the_subscription->get_status() ) {
					unset( $actions['active'] );
					unset( $actions['trash'] );
				} elseif ( ! in_array( $the_subscription->get_status(), array( 'cancelled', 'pending-cancel', 'expired', 'switched', 'suspended' ) ) ) {
					unset( $actions['trash'] );
				}

				$actions = apply_filters( 'hf_subscription_list_table_actions', $actions, $the_subscription );

				$column_content .= $wp_list_table->row_actions( $actions );

				$column_content = apply_filters( 'hf_subscription_list_table_column_status_content', $column_content, $the_subscription, $actions );
				break;

			case 'order_title' :

				$customer_tip = '';

				if ( $address = $the_subscription->get_formatted_billing_address() ) {
					$customer_tip .= __( 'Billing:', HF_Subscriptions::TEXT_DOMAIN ) . ' ' . esc_html( $address );
				}

				if ( $the_subscription->get_billing_email() ) {
					$customer_tip .= '<br/><br/>' . sprintf( __( 'Email: %s', HF_Subscriptions::TEXT_DOMAIN ), esc_attr( $the_subscription->get_billing_email() ) );
				}

				if ( $the_subscription->get_billing_phone() ) {
					$customer_tip .= '<br/><br/>' . sprintf( __( 'Tel: %s', HF_Subscriptions::TEXT_DOMAIN ), esc_html( $the_subscription->get_billing_phone() ) );
				}

				if ( ! empty( $customer_tip ) ) {
					echo '<div class="tips" data-tip="' . esc_attr( $customer_tip ) . '">';
				}

				$username = '';

				if ( $the_subscription->get_user_id() && ( false !== ( $user_info = get_userdata( $the_subscription->get_user_id() ) ) ) ) {

					$username  = '<a href="user-edit.php?user_id=' . absint( $user_info->ID ) . '">';

					if ( $the_subscription->get_billing_first_name() || $the_subscription->get_billing_last_name() ) {
						$username .= esc_html( ucfirst( $the_subscription->get_billing_first_name() ) . ' ' . ucfirst( $the_subscription->get_billing_last_name() ) );
					} elseif ( $user_info->first_name || $user_info->last_name ) {
						$username .= esc_html( ucfirst( $user_info->first_name ) . ' ' . ucfirst( $user_info->last_name ) );
					} else {
						$username .= esc_html( ucfirst( $user_info->display_name ) );
					}

					$username .= '</a>';

				} elseif ( $the_subscription->get_billing_first_name() || $the_subscription->get_billing_last_name() ) {
					$username = trim( $the_subscription->get_billing_first_name() . ' ' . $the_subscription->get_billing_last_name() );
				}
				$column_content = sprintf( __( '%1$s#%2$s%3$s for %4$s', HF_Subscriptions::TEXT_DOMAIN ), '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $post->ID ) . '&action=edit' ) ) . '">', '<strong>' . esc_attr( $the_subscription->get_order_number() ) . '</strong>', '</a>', $username );
				$column_content .= '</div>';
				$column_content .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details', HF_Subscriptions::TEXT_DOMAIN ) . '</span></button>';

				break;
			case 'order_items' :
				
				$subscription_items = $the_subscription->get_items();
				switch ( count( $subscription_items ) ) {
					case 0 :
						$column_content .= '&ndash;';
						break;
					case 1 :
						foreach ( $subscription_items as $item ) {
							$column_content .= self::get_item_display( $item, $the_subscription );
						}
						break;
					default :
						$column_content .= '<a href="#" class="show_order_items">' . esc_html( apply_filters( 'woocommerce_admin_order_item_count', sprintf( _n( '%d item', '%d items', $the_subscription->get_item_count(), HF_Subscriptions::TEXT_DOMAIN ), $the_subscription->get_item_count() ), $the_subscription ) ) . '</a>';
						$column_content .= '<table class="order_items" cellspacing="0">';

						foreach ( $subscription_items as $item ) {
							$column_content .= self::get_item_display( $item, $the_subscription, 'row' );
						}

						$column_content .= '</table>';
						break;
				}
				break;

			case 'recurring_total' :
				$column_content .= esc_html( strip_tags( $the_subscription->get_formatted_order_total() ) );
				$column_content .= '<small class="meta">' . esc_html( sprintf( __( 'Via %s', HF_Subscriptions::TEXT_DOMAIN ), $the_subscription->get_payment_method_to_display() ) ) . '</small>';
				break;

			case 'start_date':
			case 'next_payment_date':
			case 'last_payment_date':
			case 'end_date':
				$date_type_map = array( 'start_date' => 'date_created', 'last_payment_date' => 'last_order_date_created' );
				$date_type     = array_key_exists( $column, $date_type_map ) ? $date_type_map[ $column ] : $column;

				if ( 0 == $the_subscription->get_time( $date_type, 'gmt' ) ) {
					$column_content .= '-';
				} else {
					$column_content .= sprintf( '<time class="%s" title="%s">%s</time>', esc_attr( $column ), esc_attr( date( __( 'Y/m/d g:i:s A', HF_Subscriptions::TEXT_DOMAIN ) , $the_subscription->get_time( $date_type, 'site' ) ) ), esc_html( $the_subscription->get_date_to_display( $date_type ) ) );

					if ( 'next_payment_date' == $column && $the_subscription->payment_method_supports( 'gateway_scheduled_payments' ) && ! $the_subscription->is_manual() && $the_subscription->has_status( 'active' ) ) {
						$column_content .= '<div class="woocommerce-help-tip" data-tip="' . esc_attr__( 'This date should be treated as an estimate only. The payment gateway for this subscription controls when payments are processed.', HF_Subscriptions::TEXT_DOMAIN ) . '"></div>';
					}
				}

				$column_content = $column_content;
				break;

			case 'orders' :
				$column_content .= $this->get_related_orders_link( $the_subscription );
				break;
		}

		echo wp_kses( apply_filters( 'hf_subscription_list_table_column_content', $column_content, $the_subscription, $column ), array( 'a' => array( 'class' => array(), 'href' => array(), 'data-tip' => array(), 'title' => array() ), 'time' => array( 'class' => array(), 'title' => array() ), 'mark' => array( 'class' => array(), 'data-tip' => array() ), 'small' => array( 'class' => array() ), 'table' => array( 'class' => array(), 'cellspacing' => array(), 'cellpadding' => array() ), 'tr' => array( 'class' => array() ), 'td' => array( 'class' => array() ), 'div' => array( 'class' => array(), 'data-tip' => array() ), 'br' => array(), 'strong' => array(), 'span' => array( 'class' => array(), 'data-tip' => array() ), 'p' => array( 'class' => array() ), 'button' => array( 'type' => array(), 'class' => array() ) ) );

	}

	public function hf_shop_subscription_sortable_columns( $columns ) {

		$sortable_columns = array(
			'order_title'       => 'ID',
			'recurring_total'   => 'order_total',
			'start_date'        => 'date',
			'next_payment_date' => 'next_payment_date',
			'last_payment_date' => 'last_payment_date',
			'end_date'          => 'end_date',
		);

		return wp_parse_args( $sortable_columns, $columns );
	}

	public function hf_shop_subscription_search_custom_fields( $wp ) {
            
		global $pagenow, $wpdb;
		if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['s'] ) || 'hf_shop_subscription' !== $wp->query_vars['post_type'] ) {
			return;
		}

		$search_fields = array_map( 'wc_clean', apply_filters( 'woocommerce_hf_shop_subscription_search_fields', array(
			'_order_key',
			'_billing_company',
			'_billing_address_1',
			'_billing_address_2',
			'_billing_city',
			'_billing_postcode',
			'_billing_country',
			'_billing_state',
			'_billing_email',
			'_billing_phone',
			'_shipping_address_1',
			'_shipping_address_2',
			'_shipping_city',
			'_shipping_postcode',
			'_shipping_country',
			'_shipping_state',
		) ) );

		$search_order_id = str_replace( 'Order #', '', $_GET['s'] );
		if ( ! is_numeric( $search_order_id ) ) {
			$search_order_id = 0;
		}

		$post_ids = array_unique( array_merge(
			$wpdb->get_col(
				$wpdb->prepare( "
					SELECT p1.post_id
					FROM {$wpdb->postmeta} p1
					INNER JOIN {$wpdb->postmeta} p2 ON p1.post_id = p2.post_id
					WHERE
						( p1.meta_key = '_billing_first_name' AND p2.meta_key = '_billing_last_name' AND CONCAT(p1.meta_value, ' ', p2.meta_value) LIKE '%%%s%%' )
					OR
						( p1.meta_key = '_shipping_first_name' AND p2.meta_key = '_shipping_last_name' AND CONCAT(p1.meta_value, ' ', p2.meta_value) LIKE '%%%s%%' )
					OR
						( p1.meta_key IN ('" . implode( "','", esc_sql( $search_fields ) ) . "') AND p1.meta_value LIKE '%%%s%%' )
					",
					esc_attr( $_GET['s'] ), esc_attr( $_GET['s'] ), esc_attr( $_GET['s'] )
				)
			),
			$wpdb->get_col(
				$wpdb->prepare( "
					SELECT order_id
					FROM {$wpdb->prefix}woocommerce_order_items as order_items
					WHERE order_item_name LIKE '%%%s%%'
					",
					esc_attr( $_GET['s'] )
				)
			),
			$wpdb->get_col(
				$wpdb->prepare( "
					SELECT p1.ID
					FROM {$wpdb->posts} p1
					INNER JOIN {$wpdb->postmeta} p2 ON p1.ID = p2.post_id
					INNER JOIN {$wpdb->users} u ON p2.meta_value = u.ID
					WHERE u.user_email LIKE '%%%s%%'
					AND p2.meta_key = '_customer_user'
					AND p1.post_type = 'hf_shop_subscription'
					",
					esc_attr( $_GET['s'] )
				)
			),
			array( $search_order_id )
		) );

		unset( $wp->query_vars['s'] );
		$wp->query_vars['hf_shop_subscription_search'] = true;
		$wp->query_vars['post__in'] = $post_ids;
	}

	
	public function hf_shop_subscription_search_label( $query ) {
		
                global $pagenow, $typenow;
		if ( 'edit.php' !== $pagenow ) { return $query;	}
		if ( 'hf_shop_subscription' !== $typenow ) { return $query; }
		if ( ! get_query_var( 'hf_shop_subscription_search' ) ) { return $query; }
		return wp_unslash( $_GET['s'] );
	}

	public function add_custom_query_var( $public_query_vars ) {
            
		$public_query_vars[] = 'sku';
		$public_query_vars[] = 'hf_shop_subscription_search';
		return $public_query_vars;
	}

	public function request_query( $vars ) {
            
		global $typenow;
		if ( 'hf_shop_subscription' === $typenow ) {

			if ( isset( $_GET['_customer_user'] ) && $_GET['_customer_user'] > 0 ) {
				$vars['meta_query'][] = array(
					'key'   => '_customer_user',
					'value' => (int) $_GET['_customer_user'],
					'compare' => '=',
				);
			}

			if ( isset( $_GET['_hf_product'] ) && $_GET['_hf_product'] > 0 ) {
				$subscription_ids = hf_get_subscriptions_for_product( $_GET['_hf_product'] );
				if ( ! empty( $subscription_ids ) ) {
					$vars['post__in'] = $subscription_ids;
				} else {
					$vars['post__in'] = array( 0 );
				}
			}

			if ( ! empty( $_GET['_payment_method'] ) ) {

				$payment_gateway_filter = ( 'none' == $_GET['_payment_method'] ) ? '' : $_GET['_payment_method'];

				$query_vars = array(
					'post_type'   => 'hf_shop_subscription',
					'posts_per_page' => -1,
					'post_status' => 'any',
					'fields'      => 'ids',
					'meta_query'  => array(
						array(
							'key'   => '_payment_method',
							'value' => $payment_gateway_filter,
						),
					),
				);

				if ( isset( $vars['post__in'] ) ) {
					$query_vars['post__in'] = $vars['post__in'];
				}
				$subscription_ids = get_posts( $query_vars );
				if ( ! empty( $subscription_ids ) ) {
					$vars['post__in'] = $subscription_ids;
				} else {
					$vars['post__in'] = array( 0 );
				}
			}

			if ( isset( $vars['orderby'] ) ) {
				switch ( $vars['orderby'] ) {
					case 'order_total' :
						$vars = array_merge( $vars, array(
							'meta_key' 	=> '_order_total',
							'orderby' 	=> 'meta_value_num',
						) );
					break;
					case 'last_payment_date' :
						add_filter( 'posts_clauses', array( $this, 'posts_clauses' ), 10, 2 );
						break;
					case 'trial_end_date' :
					case 'next_payment_date' :
					case 'end_date' :
						$vars = array_merge( $vars, array(
							'meta_key'     => sprintf( '_schedule_%s', str_replace( '_date', '', $vars['orderby'] ) ),
							'meta_type'    => 'DATETIME',
							'orderby'      => 'meta_value',
						) );
					break;
				}
			}

			if ( ! isset( $vars['post_status'] ) ) {
				$vars['post_status'] = array_keys( hf_get_subscription_statuses() );
			}
		}

		return $vars;
	}

        
	public function post_updated_messages( $messages ) {
		global $post, $post_ID;

		$messages['hf_shop_subscription'] = array(
			0 => '',
			1 => __( 'Subscription updated.', HF_Subscriptions::TEXT_DOMAIN ),
			2 => __( 'Custom field updated.', HF_Subscriptions::TEXT_DOMAIN ),
			3 => __( 'Custom field deleted.', HF_Subscriptions::TEXT_DOMAIN ),
			4 => __( 'Subscription updated.', HF_Subscriptions::TEXT_DOMAIN ),
			5 => isset( $_GET['revision'] ) ? sprintf( __( 'Subscription restored to revision from %s', HF_Subscriptions::TEXT_DOMAIN ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Subscription updated.', HF_Subscriptions::TEXT_DOMAIN ),
			7 => __( 'Subscription saved.', HF_Subscriptions::TEXT_DOMAIN ),
			8 => __( 'Subscription submitted.', HF_Subscriptions::TEXT_DOMAIN ),
			9 => sprintf( __( 'Subscription scheduled for: %1$s.', HF_Subscriptions::TEXT_DOMAIN ), '<strong>' . date_i18n( __( 'M j, Y @ G:i', HF_Subscriptions::TEXT_DOMAIN ), hf_date_to_time( $post->post_date ) ) . '</strong>' ),
			10 => __( 'Subscription draft updated.', HF_Subscriptions::TEXT_DOMAIN ),
		);

		return $messages;
	}

	public function get_related_orders_link( $the_subscription ) {
		return sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'edit.php?post_status=all&post_type=shop_order&_subscription_related_orders=' . absint( $the_subscription->get_id() ) ),
			count( $the_subscription->get_related_orders() )
		);
	}

	public static function restrict_by_payment_method() {
		global $typenow;

		if ( 'hf_shop_subscription' !== $typenow ) {
			return;
		}

		$selected_gateway_id = ( ! empty( $_GET['_payment_method'] ) ) ? $_GET['_payment_method'] : ''; ?>

		<select class="hf_payment_method_selector" name="_payment_method" id="_payment_method" class="first">
			<option value=""><?php esc_html_e( 'Any Payment Method', HF_Subscriptions::TEXT_DOMAIN ) ?></option>
			<option value="none" <?php echo esc_attr( 'none' == $selected_gateway_id ? 'selected' : '' ) . '>' . esc_html__( 'None', HF_Subscriptions::TEXT_DOMAIN ) ?></option>
		<?php

		foreach ( WC()->payment_gateways->get_available_payment_gateways() as $gateway_id => $gateway ) {
			echo '<option value="' . esc_attr( $gateway_id ) . '"' . ( $selected_gateway_id == $gateway_id  ? 'selected' : '' ) . '>' . esc_html( $gateway->title ) . '</option>';
		}?>
		</select> <?php
	}


	public function list_table_primary_column( $default, $screen_id ) {

		if ( 'edit-hf_shop_subscription' == $screen_id ) {
			$default = 'order_title';
		}

		return $default;
	}
	
	public function hf_shop_subscription_row_actions( $actions, $post ) {

		if ( 'hf_shop_subscription' == $post->post_type ) {
			$actions = array();
		}
		return $actions;
	}

	protected static function get_item_display( $item, $the_subscription, $element = 'div' ) {

		$_product       = apply_filters( 'woocommerce_order_item_product', $the_subscription->get_product_from_item( $item ), $item );
		$item_meta_html = self::get_item_meta_html( $item, $_product );

		if ( 'div' === $element ) {
			$item_html = self::get_item_display_div( $item, self::get_item_name_html( $item, $_product ), $item_meta_html );
		} else {
			$item_html = self::get_item_display_row( $item, self::get_item_name_html( $item, $_product, 'do_not_include_quantity' ), $item_meta_html );
		}

		return $item_html;
	}

	protected static function get_item_meta_html( $item, $_product ) {

		if ( HF_Subscriptions::is_woocommerce_prior_to( '3.0' ) ) {
			$item_meta      = hf_get_order_item_meta( $item, $_product );
			$item_meta_html = $item_meta->display( true, true );
		} else {
			$item_meta_html = wc_display_item_meta( $item, array(
				'before'    => '',
				'after'     => '',
				'separator' => '\n',
				'echo'      => false,
			) );
		}

		return $item_meta_html;
	}


	protected static function get_item_name_html( $item, $_product, $include_quantity = 'include_quantity' ) {

		$item_quantity  = absint( $item['qty'] );

		$item_name = '';

		if ( wc_product_sku_enabled() && $_product && $_product->get_sku() ) {
			$item_name .= $_product->get_sku() . ' - ';
		}

		$item_name .= apply_filters( 'woocommerce_order_item_name', $item['name'], $item, false );
		$item_name  = esc_html( $item_name );

		if ( 'include_quantity' === $include_quantity && $item_quantity > 1 ) {
			$item_name = sprintf( '%s &times; %s', absint( $item_quantity ), $item_name );
		}

		if ( $_product ) {
			$item_name = sprintf( '<a href="%s">%s</a>', get_edit_post_link( ( $_product->is_type( 'variation' ) ) ? hf_get_objects_property( $_product, 'parent_id' ) : $_product->get_id() ), $item_name );
		}

		return $item_name;
	}

	protected static function get_item_display_div( $item, $item_name, $item_meta_html ) {

		$item_html  = '<div class="order-item">';
		$item_html .= wp_kses( $item_name, array( 'a' => array( 'href' => array() ) ) );

		if ( $item_meta_html ) {
			$item_html .= hf_help_tip( $item_meta_html );
		}

		$item_html .= '</div>';

		return $item_html;
	}

	protected static function get_item_display_row( $item, $item_name, $item_meta_html ) {

		ob_start();
		?>
		<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_admin_order_item_class', '', $item ) ); ?>">
			<td class="qty"><?php echo absint( $item['qty'] ); ?></td>
			<td class="name">
				<?php

				echo wp_kses( $item_name, array( 'a' => array( 'href' => array() ) ) );

				if ( $item_meta_html ) {
					echo hf_help_tip( $item_meta_html );
				} ?>
			</td>
		</tr>
		<?php

		$item_html = ob_get_clean();

		return $item_html;
	}
}

new HF_Admin_Post_Types();