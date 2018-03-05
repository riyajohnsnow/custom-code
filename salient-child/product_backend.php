<?php
// Display Fields

/*add_action( 'woocommerce_product_options_general_product_data', 'woo_add_custom_general_fields' );

function woo_add_custom_general_fields(){
	global $woocommerce, $post;

	// Concept Unique Identifier
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_concept_unique_identifier', 
			'label'       => __( 'Concept Unique Identifier', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Atom_Unique_Identifier
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_atom_unique_identifier', 
			'label'       => __( 'Atom Unique Identifier', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Is_Preferred
	woocommerce_wp_select( 
		array( 
			'id'      => '_is_preferred', 
			'label'   => __( 'Is Preferred', 'woocommerce' ), 
			'options' => array(
				'true'   => __( 'true', 'woocommerce' ),
				'false'   => __( 'false', 'woocommerce' )
			)
		)
	);

	// Source_Asserted_Atom_Identifier
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_source_asserted_atom_identifier', 
			'label'       => __( 'Source Asserted Atom Identifier', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Source_Asserted_Concept_Identifier
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_source_asserted_concept_identifier', 
			'label'       => __( 'Source Asserted Concept Identifier', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Source_Asserted_Descriptor_Identifier
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_source_asserted_descriptor_identifier', 
			'label'       => __( 'Source Asserted Descriptor Identifier', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Source_Abbreviation
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_source_abbreviation', 
			'label'       => __( 'Source Abbreviation', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Term_Type
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_term_type', 
			'label'       => __( 'Term Type', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Source_String_Code
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_source_string_code', 
			'label'       => __( 'Source String Code', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// String_Name
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_string_name', 
			'label'       => __( 'String Name', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Source_Restriction_Level
	woocommerce_wp_text_input( 
		array( 
			'id'                => '_source_restriction_level', 
			'label'             => __( 'Source Restriction Level', 'woocommerce' ), 
			'placeholder'       => '', 
			'description'       => __( 'Enter the custom value here.', 'woocommerce' ),
			'type'              => 'number', 
			'custom_attributes' => array(
					'step' 	=> 'any',
					'min'	=> '0'
			) 
		)
	);

	// Suppressible_Flag
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_suppressible_flag', 
			'label'       => __( 'Suppressible Flag', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Content_View_Flag_1
	woocommerce_wp_text_input( 
		array( 
			'id'                => '_content_view_flag_1', 
			'label'             => __( 'Content View Flag 1', 'woocommerce' ), 
			'placeholder'       => '', 
			'description'       => __( 'Enter the custom value here.', 'woocommerce' ),
			'type'              => 'number', 
			'custom_attributes' => array(
					'step' 	=> 'any',
					'min'	=> '0'
			) 
		)
	);

	// Content_View_Flag_2
	woocommerce_wp_text_input( 
		array( 
			'id'                => '_content_view_flag_2', 
			'label'             => __( 'Content View Flag 2', 'woocommerce' ), 
			'placeholder'       => '', 
			'description'       => __( 'Enter the custom value here.', 'woocommerce' ),
			'type'              => 'number', 
			'custom_attributes' => array(
					'step' 	=> 'any',
					'min'	=> '0'
			) 
		)
	);

	// Semantic_Type_Unique_Identifier
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_semantic_type_unique_identifier', 
			'label'       => __( 'Semantic Type Unique Identifier', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Semantic_Type_Tree_Identifier
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_semantic_type_tree_identifier', 
			'label'       => __( 'Semantic Type Tree Identifier', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Semantic_Type_Name
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_semantic_type_name', 
			'label'       => __( 'Semantic Type Name', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

	// Attribute_Type_Identifier
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_attribute_type_identifier', 
			'label'       => __( 'Attribute Type Identifier', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the custom value here.', 'woocommerce' ) 
		)
	);

}

// Save Fields
add_action( 'woocommerce_process_product_meta', 'woo_add_custom_general_fields_save' );
function woo_add_custom_general_fields_save(){

	global $post;
	$post_id = $post->ID;

	//_concept_unique_identifier
	$_concept_unique_identifier = $_POST['_concept_unique_identifier'];
	if( !empty( $_concept_unique_identifier ) ){
		update_post_meta( $post_id, '_concept_unique_identifier', esc_attr( $_concept_unique_identifier ) );
	}

	//_atom_unique_identifier
	$_atom_unique_identifier = $_POST['_atom_unique_identifier'];
	if( !empty( $_atom_unique_identifier ) ){
		update_post_meta( $post_id, '_atom_unique_identifier', esc_attr( $_atom_unique_identifier ) );
	}

	// _is_preferred
	$_is_preferred = $_POST['_is_preferred'];
	if( !empty( $_is_preferred ) ){
		update_post_meta( $post_id, '_is_preferred', esc_attr( $_is_preferred ) );
	}

	//_source_asserted_atom_identifier
	$_source_asserted_atom_identifier = $_POST['_source_asserted_atom_identifier'];
	if( !empty( $_source_asserted_atom_identifier ) ){
		update_post_meta( $post_id, '_source_asserted_atom_identifier', esc_attr( $_source_asserted_atom_identifier ) );
	}

	//_source_asserted_concept_identifier
	$_source_asserted_concept_identifier = $_POST['_source_asserted_concept_identifier'];
	if( !empty( $_source_asserted_concept_identifier ) ){
		update_post_meta( $post_id, '_source_asserted_concept_identifier', esc_attr( $_source_asserted_concept_identifier ) );
	}

	//_source_asserted_descriptor_identifier 
	$_source_asserted_descriptor_identifier = $_POST['_source_asserted_descriptor_identifier'];
	if( !empty( $_source_asserted_descriptor_identifier ) ){
		update_post_meta( $post_id, '_source_asserted_descriptor_identifier', esc_attr( $_source_asserted_descriptor_identifier ) );
	}

	//_source_abbreviation 
	$_source_abbreviation = $_POST['_source_abbreviation'];
	if( !empty( $_source_abbreviation ) ){
		update_post_meta( $post_id, '_source_abbreviation', esc_attr( $_source_abbreviation ) );
	}

	//_term_type 
	$_term_type = $_POST['_term_type'];
	if( !empty( $_term_type ) ){
		update_post_meta( $post_id, '_term_type', esc_attr( $_term_type ) );
	}

	//_source_string_code 
	$_source_string_code = $_POST['_source_string_code'];
	if( !empty( $_source_string_code ) ){
		update_post_meta( $post_id, '_source_string_code', esc_attr( $_source_string_code ) );
	}

	//_string_name 
	$_string_name = $_POST['_string_name'];
	if( !empty( $_string_name ) ){
		update_post_meta( $post_id, '_string_name', esc_attr( $_string_name ) );
	}

	// _source_restriction_level
	$_source_restriction_level = $_POST['_source_restriction_level'];
	if( !empty( $_source_restriction_level ) ){
		update_post_meta( $post_id, '_source_restriction_level', esc_attr( $_source_restriction_level ) );
	}

	// _suppressible_flag
	$_suppressible_flag = $_POST['_suppressible_flag'];
	if( !empty( $_suppressible_flag ) ){
		update_post_meta( $post_id, '_suppressible_flag', esc_attr( $_suppressible_flag ) );
	}

	// _content_view_flag_1
	$_content_view_flag_1 = $_POST['_content_view_flag_1'];
	if( !empty( $_content_view_flag_1 ) ){
		update_post_meta( $post_id, '_content_view_flag_1', esc_attr( $_content_view_flag_1 ) );
	}

	// _content_view_flag_2
	$_content_view_flag_2 = $_POST['_content_view_flag_2'];
	if( !empty( $_content_view_flag_2 ) ){
		update_post_meta( $post_id, '_content_view_flag_2', esc_attr( $_content_view_flag_2 ) );
	}

	// _semantic_type_unique_identifier
	$_semantic_type_unique_identifier = $_POST['_semantic_type_unique_identifier'];
	if( !empty( $_semantic_type_unique_identifier ) ){
		update_post_meta( $post_id, '_semantic_type_unique_identifier', esc_attr( $_semantic_type_unique_identifier ) );
	}

	// _semantic_type_tree_identifier
	$_semantic_type_tree_identifier = $_POST['_semantic_type_tree_identifier'];
	if( !empty( $_semantic_type_tree_identifier ) ){
		update_post_meta( $post_id, '_semantic_type_tree_identifier', esc_attr( $_semantic_type_tree_identifier ) );
	}

	// _semantic_type_name
	$_semantic_type_name = $_POST['_semantic_type_name'];
	if( !empty( $_semantic_type_name ) ){
		update_post_meta( $post_id, '_semantic_type_name', esc_attr( $_semantic_type_name ) );
	}

	// _attribute_type_identifier
	$_attribute_type_identifier = $_POST['_attribute_type_identifier'];
	if( !empty( $_attribute_type_identifier ) ){
		update_post_meta( $post_id, '_attribute_type_identifier', esc_attr( $_attribute_type_identifier ) );
	}

}

*/


// Data Set MetaBox
function custom_meta_box_markup()
{
    global $post;
    // How to use 'get_post_meta()' for multiple checkboxes as array?
    $postmeta = maybe_unserialize( get_post_meta( $post->ID, 'data_set_input', true ) );

    // Our associative array here. id = value
    $elements = array(
        '0'  => 'Curated by a human expert',
        '1' => 'Normalized',
        '2' => 'Big Data Optimized',
        '3' => 'Kept up to date',
        '4' => 'Enriched with Lat/Long information',
        '5' => 'Enriched with FIPS Codes',
        '6' => 'Enriched with NPI Codes'
    );
    // Loop through array and make a checkbox for each element
    foreach ( $elements as $id => $element) {
        // If the postmeta for checkboxes exist and
        // this element is part of saved meta check it.
        if ( is_array( $postmeta ) && in_array( $id, $postmeta ) ) {
            $checked = 'checked="checked"';
        } else {
            $checked = null;
        }
        ?>
        <ul class="product-data-set">
            <li><label class="data-set"><input  type="checkbox" name="data_set_input[]" value="<?php echo $id;?>" <?php echo $checked; ?> />
            <?php echo $element;?> </label></li>
        </ul>
        <?php
    }

}

function add_custom_meta_box()
{
    add_meta_box("demo-meta-box", "Data Set", "custom_meta_box_markup", "product", "side", "default", null);
    add_meta_box("media-meta-box", "Product Image", "product_image_upload", "product", "side", "default", null);
}

add_action("add_meta_boxes", "add_custom_meta_box");

add_action( 'save_post', function() {
    global $post;
    $is_autosave = wp_is_post_autosave( $post->ID );
    $is_revision = wp_is_post_revision( $post->ID );
    $is_valid_nonce = ( isset( $_POST[ 'mam_nonce' ] ) && wp_verify_nonce( $_POST[ 'mam_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';

    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }

    // If the checkbox was not empty, save it as array in post meta
    if ( ! empty( $_POST['data_set_input'] ) ) {
        update_post_meta( $post->ID, 'data_set_input', $_POST['data_set_input'] );

        // Otherwise just delete it if its blank value.
    }

    if ( ! empty( $_POST['image_attachment_id'] ) ) {
        update_post_meta( $post->ID, 'cus_product_image', $_POST['image_attachment_id'] );

        // Otherwise just delete it if its blank value.
    }

});
function product_image_upload(){
	global $post;
	$image_id = get_post_meta($post->ID, 'cus_product_image' );
	// Save attachment ID
	if ( isset( $_POST['submit_image_selector'] ) && isset( $_POST['image_attachment_id'] ) ) :
        update_post_meta( $post->ID, 'cus_product_image', absint( $_POST['image_attachment_id'] ) );
	endif;
	wp_enqueue_media();
	?>
	<form method='post'>
		<div class="image-name">
			
		</div>
		<div class='image-preview-wrapper'>
			<img id='image-preview' src='<?php echo $image_id[0]; ?>' height='100'>
		</div>
		<input id="upload_image_button" type="button" class="button" value="<?php _e( 'Upload image' ); ?>" />
		<input type='hidden' name='image_attachment_id' id='image_attachment_id' value='<?php echo $image_id[0]; ?>'>
		<!-- <input type="submit" name="submit_image_selector" value="Save" class="button-primary"> -->
	</form><?php
}

add_action( 'admin_footer', 'media_selector_print_scripts' );

function media_selector_print_scripts() {
	global $post;
	 $my_saved_attachment_post_id = get_post_meta($post->ID, 'cus_product_image' );
	?><script type='text/javascript'>
		jQuery( document ).ready( function( $ ) {
			// Uploading files
			var file_frame;
			var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id
			var set_to_post_id = <?php echo $my_saved_attachment_post_id; ?>; // Set this
//            debugger
			jQuery('#upload_image_button').on('click', function( event ){
				event.preventDefault();
				// If the media frame already exists, reopen it.
				if ( file_frame ) {
					// Set the post ID to what we want
					file_frame.uploader.uploader.param( 'post_id', set_to_post_id );
					// Open frame
					file_frame.open();
					return;
				} else {
					// Set the wp.media post id so the uploader grabs the ID we want when initialised
					wp.media.model.settings.post.id = set_to_post_id;
				}
				// Create the media frame.
				file_frame = wp.media.frames.file_frame = wp.media({
					title: 'Select a image to upload',
					button: {
						text: 'Use this image',
					},
					multiple: false	// Set to true to allow multiple files to be selected
				});
				// When an image is selected, run a callback.
				file_frame.on( 'select', function() {
					// We set multiple to false so only get one image from the uploader
					attachment = file_frame.state().get('selection').first().toJSON();
					// Do something with attachment.id and/or attachment.url here
					$( '#image-preview' ).attr( 'src', attachment.url ).css( 'width', 'auto' );
					$( '#image_attachment_id' ).val( attachment.url );
					// Restore the main post ID
					wp.media.model.settings.post.id = wp_media_post_id;
				});
					// Finally, open the modal
					file_frame.open();
			});
			// Restore the main ID when the add media button is pressed
			jQuery( 'a.add_media' ).on( 'click', function() {
				wp.media.model.settings.post.id = wp_media_post_id;
			});
		});
	</script><?php
}