<?php

if( ! class_exists('acs_field_gallery') ) :

class acs_field_gallery extends acs_field {
	
	
	/*
	*  __construct
	*
	*  This function will setup the field type data
	*
	*  @type	function
	*  @date	5/03/2014
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/
	
	function initialize() {
		
		// vars
		$this->name = 'gallery';
		$this->label = __("Gallery",'acs');
		$this->category = 'content';
		$this->defaults = array(
			'return_format'	=> 'array',
			'preview_size'	=> 'medium',
			'insert'		=> 'append',
			'library'		=> 'all',
			'min'			=> 0,
			'max'			=> 0,
			'min_width'		=> 0,
			'min_height'	=> 0,
			'min_size'		=> 0,
			'max_width'		=> 0,
			'max_height'	=> 0,
			'max_size'		=> 0,
			'mime_types'	=> '',
		);
		
		
		// actions
		add_action('wp_ajax_acs/fields/gallery/get_attachment',				array($this, 'ajax_get_attachment'));
		add_action('wp_ajax_nopriv_acs/fields/gallery/get_attachment',		array($this, 'ajax_get_attachment'));
		
		add_action('wp_ajax_acs/fields/gallery/update_attachment',			array($this, 'ajax_update_attachment'));
		add_action('wp_ajax_nopriv_acs/fields/gallery/update_attachment',	array($this, 'ajax_update_attachment'));
		
		add_action('wp_ajax_acs/fields/gallery/get_sort_order',				array($this, 'ajax_get_sort_order'));
		add_action('wp_ajax_nopriv_acs/fields/gallery/get_sort_order',		array($this, 'ajax_get_sort_order'));
		
	}
	
	/*
	*  input_admin_enqueue_scripts
	*
	*  description
	*
	*  @type	function
	*  @date	16/12/2015
	*  @since	5.3.2
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/
	
	function input_admin_enqueue_scripts() {
		
		// localize
		acs_localize_text(array(
		   	'Add Image to Gallery'		=> __('Add Image to Gallery', 'acs'),
			'Maximum selection reached'	=> __('Maximum selection reached', 'acs'),
	   	));
	}
	
	
	/*
	*  ajax_get_attachment
	*
	*  description
	*
	*  @type	function
	*  @date	13/12/2013
	*  @since	5.0.0
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/
	
	function ajax_get_attachment() {
		
		// Validate requrest.
		if( !acs_verify_ajax() ) {
			die();
		}
		
		// Get args.
   		$args = acs_request_args(array(
			'id'		=> 0,
			'field_key'	=> '',
		));
		
		// Cast args.
   		$args['id'] = (int) $args['id'];
		
		// Bail early if no id.
		if( !$args['id'] ) {
			die();
		}
		
		// Load field.
		$field = acs_get_field( $args['field_key'] );
		if( !$field ) {
			die();
		}
		
		// Render.
		$this->render_attachment( $args['id'], $field );
		die;
	}
	
	
	/*
	*  ajax_update_attachment
	*
	*  description
	*
	*  @type	function
	*  @date	13/12/2013
	*  @since	5.0.0
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/
	
	function ajax_update_attachment() {
		
		// validate nonce
		if( !wp_verify_nonce($_POST['nonce'], 'acs_nonce') ) {
		
			wp_send_json_error();
			
		}
		
		
		// bail early if no attachments
		if( empty($_POST['attachments']) ) {
		
			wp_send_json_error();
			
		}
		
		
		// loop over attachments
		foreach( $_POST['attachments'] as $id => $changes ) {
			
			if ( !current_user_can( 'edit_post', $id ) )
				wp_send_json_error();
				
			$post = get_post( $id, ARRAY_A );
		
			if ( 'attachment' != $post['post_type'] )
				wp_send_json_error();
		
			if ( isset( $changes['title'] ) )
				$post['post_title'] = $changes['title'];
		
			if ( isset( $changes['caption'] ) )
				$post['post_excerpt'] = $changes['caption'];
		
			if ( isset( $changes['description'] ) )
				$post['post_content'] = $changes['description'];
		
			if ( isset( $changes['alt'] ) ) {
				$alt = wp_unslash( $changes['alt'] );
				if ( $alt != get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
					$alt = wp_strip_all_tags( $alt, true );
					update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
				}
			}
			
			
			// save post
			wp_update_post( $post );
			
			
			/** This filter is documented in wp-admin/includes/media.php */
			// - seems off to run this filter AFTER the update_post function, but there is a reason
			// - when placed BEFORE, an empty post_title will be populated by WP
			// - this filter will still allow 3rd party to save extra image data!
			$post = apply_filters( 'attachment_fields_to_save', $post, $changes );
			
			
			// save meta
			acs_save_post( $id );
						
		}
		
		
		// return
		wp_send_json_success();
			
	}
	
	
	/*
	*  ajax_get_sort_order
	*
	*  description
	*
	*  @type	function
	*  @date	13/12/2013
	*  @since	5.0.0
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/
	
	function ajax_get_sort_order() {
		
		// vars
		$r = array();
		$order = 'DESC';
   		$args = acs_parse_args( $_POST, array(
			'ids'			=> 0,
			'sort'			=> 'date',
			'field_key'		=> '',
			'nonce'			=> '',
		));
		
		
		// validate
		if( ! wp_verify_nonce($args['nonce'], 'acs_nonce') ) {
		
			wp_send_json_error();
			
		}
		
		
		// reverse
		if( $args['sort'] == 'reverse' ) {
		
			$ids = array_reverse($args['ids']);
			
			wp_send_json_success($ids);
			
		}
		
		
		if( $args['sort'] == 'title' ) {
			
			$order = 'ASC';
			
		}
		
		
		// find attachments (DISTINCT POSTS)
		$ids = get_posts(array(
			'post_type'		=> 'attachment',
			'numberposts'	=> -1,
			'post_status'	=> 'any',
			'post__in'		=> $args['ids'],
			'order'			=> $order,
			'orderby'		=> $args['sort'],
			'fields'		=> 'ids'		
		));
		
		
		// success
		if( !empty($ids) ) {
		
			wp_send_json_success($ids);
			
		}
		
		
		// failure
		wp_send_json_error();
		
	}
	
	/**
	 * Renders the sidebar HTML shown when selecting an attachmemnt.
	 *
	 * @date	13/12/2013
	 * @since	5.0.0
	 *
	 * @param	int $id The attachment ID.
	 * @param	array $field The field array.
	 * @return	void
	 */	
	function render_attachment( $id, $field ) {
		// Load attachmenet data.
		$attachment = wp_prepare_attachment_for_js( $id );
		$compat = get_compat_media_markup( $id );
		
		// Get attachment thumbnail (video).
		if( isset($attachment['thumb']['src']) ) {
			$thumb = $attachment['thumb']['src'];
		
		// Look for thumbnail size (image).
		} elseif( isset($attachment['sizes']['thumbnail']['url']) ) {
			$thumb = $attachment['sizes']['thumbnail']['url'];
		
		// Use url for svg.
		} elseif( $attachment['type'] === 'image' ) {
			$thumb = $attachment['url'];
		
		// Default to icon.
		} else {
			$thumb = wp_mime_type_icon( $id );	
		}
		
		// Get attachment dimensions / time / size.
		$dimensions = '';
		if( $attachment['type'] === 'audio' ) {
			$dimensions = __('Length', 'acs') . ': ' . $attachment['fileLength'];	
		} elseif( !empty($attachment['width']) ) {
			$dimensions = $attachment['width'] . ' x ' . $attachment['height'];
		}
		if( !empty($attachment['filesizeHumanReadable']) ) {
			$dimensions .=  ' (' . $attachment['filesizeHumanReadable'] . ')';
		}
		
		?>
		<div class="acs-gallery-side-info">
			<img src="<?php echo esc_attr($thumb); ?>" alt="<?php echo esc_attr($attachment['alt']); ?>" />
			<p class="filename"><strong><?php echo esc_html($attachment['filename']); ?></strong></p>
			<p class="uploaded"><?php echo esc_html($attachment['dateFormatted']); ?></p>
			<p class="dimensions"><?php echo esc_html($dimensions); ?></p>
			<p class="actions">
				<a href="#" class="acs-gallery-edit" data-id="<?php echo esc_attr($id); ?>"><?php _e('Edit', 'acs'); ?></a>
				<a href="#" class="acs-gallery-remove" data-id="<?php echo esc_attr($id); ?>"><?php _e('Remove', 'acs'); ?></a>
			</p>
		</div>
		<table class="form-table">
			<tbody>
				<?php 
				
				// Render fields.
				$prefix = 'attachments[' . $id . ']';
				
				acs_render_field_wrap(array(
					//'key'		=> "{$field['key']}-title",
					'name'		=> 'title',
					'prefix'	=> $prefix,
					'type'		=> 'text',
					'label'		=> __('Title', 'acs'),
					'value'		=> $attachment['title']
				), 'tr');
				
				acs_render_field_wrap(array(
					//'key'		=> "{$field['key']}-caption",
					'name'		=> 'caption',
					'prefix'	=> $prefix,
					'type'		=> 'textarea',
					'label'		=> __('Caption', 'acs'),
					'value'		=> $attachment['caption']
				), 'tr');
				
				acs_render_field_wrap(array(
					//'key'		=> "{$field['key']}-alt",
					'name'		=> 'alt',
					'prefix'	=> $prefix,
					'type'		=> 'text',
					'label'		=> __('Alt Text', 'acs'),
					'value'		=> $attachment['alt']
				), 'tr');
				
				acs_render_field_wrap(array(
					//'key'		=> "{$field['key']}-description",
					'name'		=> 'description',
					'prefix'	=> $prefix,
					'type'		=> 'textarea',
					'label'		=> __('Description', 'acs'),
					'value'		=> $attachment['description']
				), 'tr');
				
				?>
			</tbody>
		</table>
		<?php
		
		// Display compat fields.
		echo $compat['item'];
	}
	
	/*
	*  render_field()
	*
	*  Create the HTML interface for your field
	*
	*  @param	$field - an array holding all the field's data
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/
	
	function render_field( $field ) {
		
		// Enqueue uploader assets.
		acs_enqueue_uploader();
		
		// Control attributes.
		$attrs = array(
			'id'				=> $field['id'],
			'class'				=> "acs-gallery {$field['class']}",
			'data-library'		=> $field['library'],
			'data-preview_size'	=> $field['preview_size'],
			'data-min'			=> $field['min'],
			'data-max'			=> $field['max'],
			'data-mime_types'	=> $field['mime_types'],
			'data-insert'		=> $field['insert'],
			'data-columns'		=> 4
		);
		
		// Set gallery height with deafult of 400px and minimum of 200px.
		$height = acs_get_user_setting('gallery_height', 400);
		$height = max( $height, 200 );
		$attrs['style'] = "height:{$height}px";
		
		// Load attachments.
		$attachments = array();
		if( $field['value'] ) {
			
			// Clean value into an array of IDs.
			$attachment_ids = array_map('intval', acs_array($field['value']));
			
			// Find posts in database (ensures all results are real).
			$posts = acs_get_posts(array(
				'post_type'					=> 'attachment',
				'post__in'					=> $attachment_ids,
				'update_post_meta_cache' 	=> true,
				'update_post_term_cache' 	=> false
			));
			
			// Load attatchment data for each post.
			$attachments = array_map('acs_get_attachment', $posts);
		}
		
		?>
<div <?php acs_esc_attr_e($attrs); ?>>
	<input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="" />
	<div class="acs-gallery-main">
		<div class="acs-gallery-attachments">
			<?php if( $attachments ): ?>
				<?php foreach( $attachments as $i => $attachment ): 
					
					// Vars
					$a_id = $attachment['ID'];
					$a_title = $attachment['title'];
					$a_type = $attachment['type'];
					$a_filename = $attachment['filename'];
					$a_class = "acs-gallery-attachment -{$a_type}";
					
					// Get thumbnail.
					$a_thumbnail = acs_get_post_thumbnail($a_id, $field['preview_size']);
					$a_class .= ($a_thumbnail['type'] === 'icon') ? ' -icon' : '';
					
					?>
					<div class="<?php echo esc_attr($a_class); ?>" data-id="<?php echo esc_attr($a_id); ?>">
						<input type="hidden" name="<?php echo esc_attr($field['name']); ?>[]" value="<?php echo esc_attr($a_id); ?>" />
						<div class="margin">
							<div class="thumbnail">
								<img src="<?php echo esc_url($a_thumbnail['url']); ?>" alt="" />
							</div>
							<?php if( $a_type !== 'image' ): ?>
								<div class="filename"><?php echo acs_get_truncated( $a_filename, 30 ); ?></div>	
							<?php endif; ?>
						</div>
						<div class="actions">
							<a class="acs-icon -cancel dark acs-gallery-remove" href="#" data-id="<?php echo esc_attr($a_id); ?>" title="<?php _e('Remove', 'acs'); ?>"></a>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<div class="acs-gallery-toolbar">
			<ul class="acs-hl">
				<li>
					<a href="#" class="acs-button button button-primary acs-gallery-add"><?php _e('Add to gallery', 'acs'); ?></a>
				</li>
				<li class="acs-fr">
					<select class="acs-gallery-sort">
						<option value=""><?php _e('Bulk actions', 'acs'); ?></option>
						<option value="date"><?php _e('Sort by date uploaded', 'acs'); ?></option>
						<option value="modified"><?php _e('Sort by date modified', 'acs'); ?></option>
						<option value="title"><?php _e('Sort by title', 'acs'); ?></option>
						<option value="reverse"><?php _e('Reverse current order', 'acs'); ?></option>
					</select>
				</li>
			</ul>
		</div>
	</div>
	<div class="acs-gallery-side">
		<div class="acs-gallery-side-inner">
			<div class="acs-gallery-side-data"></div>
			<div class="acs-gallery-toolbar">
				<ul class="acs-hl">
					<li>
						<a href="#" class="acs-button button acs-gallery-close"><?php _e('Close', 'acs'); ?></a>
					</li>
					<li class="acs-fr">
						<a class="acs-button button button-primary acs-gallery-update" href="#"><?php _e('Update', 'acs'); ?></a>
					</li>
				</ul>
			</div>
		</div>	
	</div>
</div>
		<?php
		
	}
	
	
	/*
	*  render_field_settings()
	*
	*  Create extra options for your field. This is rendered when editing a field.
	*  The value of $field['name'] can be used (like bellow) to save extra data to the $field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field	- an array holding all the field's data
	*/
	
	function render_field_settings( $field ) {
		
		// clear numeric settings
		$clear = array(
			'min',
			'max',
			'min_width',
			'min_height',
			'min_size',
			'max_width',
			'max_height',
			'max_size'
		);
		
		foreach( $clear as $k ) {
			
			if( empty($field[$k]) ) $field[$k] = '';
			
		}
		
		// return_format
		acs_render_field_setting( $field, array(
			'label'			=> __('Return Format','acs'),
			'instructions'	=> '',
			'type'			=> 'radio',
			'name'			=> 'return_format',
			'layout'		=> 'horizontal',
			'choices'		=> array(
				'array'			=> __("Image Array",'acs'),
				'url'			=> __("Image URL",'acs'),
				'id'			=> __("Image ID",'acs')
			)
		));
		
		// preview_size
		acs_render_field_setting( $field, array(
			'label'			=> __('Preview Size','acs'),
			'instructions'	=> '',
			'type'			=> 'select',
			'name'			=> 'preview_size',
			'choices'		=> acs_get_image_sizes()
		));
		
		// insert
		acs_render_field_setting( $field, array(
			'label'			=> __('Insert','acs'),
			'instructions'	=> __('Specify where new attachments are added','acs'),
			'type'			=> 'select',
			'name'			=> 'insert',
			'choices' 		=> array(
				'append'		=> __('Append to the end', 'acs'),
				'prepend'		=> __('Prepend to the beginning', 'acs')
			)
		));
		
		// library
		acs_render_field_setting( $field, array(
			'label'			=> __('Library','acs'),
			'instructions'	=> __('Limit the media library choice','acs'),
			'type'			=> 'radio',
			'name'			=> 'library',
			'layout'		=> 'horizontal',
			'choices' 		=> array(
				'all'			=> __('All', 'acs'),
				'uploadedTo'	=> __('Uploaded to post', 'acs')
			)
		));
		
		// min
		acs_render_field_setting( $field, array(
			'label'			=> __('Minimum Selection','acs'),
			'instructions'	=> '',
			'type'			=> 'number',
			'name'			=> 'min'
		));
		
		// max
		acs_render_field_setting( $field, array(
			'label'			=> __('Maximum Selection','acs'),
			'instructions'	=> '',
			'type'			=> 'number',
			'name'			=> 'max'
		));
		
		// min
		acs_render_field_setting( $field, array(
			'label'			=> __('Minimum','acs'),
			'instructions'	=> __('Restrict which images can be uploaded','acs'),
			'type'			=> 'text',
			'name'			=> 'min_width',
			'prepend'		=> __('Width', 'acs'),
			'append'		=> 'px',
		));
		
		acs_render_field_setting( $field, array(
			'label'			=> '',
			'type'			=> 'text',
			'name'			=> 'min_height',
			'prepend'		=> __('Height', 'acs'),
			'append'		=> 'px',
			'_append' 		=> 'min_width'
		));
		
		acs_render_field_setting( $field, array(
			'label'			=> '',
			'type'			=> 'text',
			'name'			=> 'min_size',
			'prepend'		=> __('File size', 'acs'),
			'append'		=> 'MB',
			'_append' 		=> 'min_width'
		));	
		
		
		// max
		acs_render_field_setting( $field, array(
			'label'			=> __('Maximum','acs'),
			'instructions'	=> __('Restrict which images can be uploaded','acs'),
			'type'			=> 'text',
			'name'			=> 'max_width',
			'prepend'		=> __('Width', 'acs'),
			'append'		=> 'px',
		));
		
		acs_render_field_setting( $field, array(
			'label'			=> '',
			'type'			=> 'text',
			'name'			=> 'max_height',
			'prepend'		=> __('Height', 'acs'),
			'append'		=> 'px',
			'_append' 		=> 'max_width'
		));
		
		acs_render_field_setting( $field, array(
			'label'			=> '',
			'type'			=> 'text',
			'name'			=> 'max_size',
			'prepend'		=> __('File size', 'acs'),
			'append'		=> 'MB',
			'_append' 		=> 'max_width'
		));	
		
		// allowed type
		acs_render_field_setting( $field, array(
			'label'			=> __('Allowed file types','acs'),
			'instructions'	=> __('Comma separated list. Leave blank for all types','acs'),
			'type'			=> 'text',
			'name'			=> 'mime_types',
		));
	}
	
	
	/*
	*  format_value()
	*
	*  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value (mixed) the value which was loaded from the database
	*  @param	$post_id (mixed) the $post_id from which the value was loaded
	*  @param	$field (array) the field array holding all the field options
	*
	*  @return	$value (mixed) the modified value
	*/
	
	function format_value( $value, $post_id, $field ) {
		
		// Bail early if no value.
		if( !$value ) {
			return false;
		}
		
		// Clean value into an array of IDs.
		$attachment_ids = array_map('intval', acs_array($value));
		
		// Find posts in database (ensures all results are real).
		$posts = acs_get_posts(array(
			'post_type'					=> 'attachment',
			'post__in'					=> $attachment_ids,
			'update_post_meta_cache' 	=> true,
			'update_post_term_cache' 	=> false
		));
		
		// Bail early if no posts found.
		if( !$posts ) {
			return false;
		}
		
		// Format values using field settings.
		$value = array();
		foreach( $posts as $post ) {
			
			// Return object.
			if( $field['return_format'] == 'object' ) {
				$item = $post;
				
			// Return array.		
			} elseif( $field['return_format'] == 'array' ) {
				$item = acs_get_attachment( $post );
				
			// Return URL.		
			} elseif( $field['return_format'] == 'url' ) {
				$item = wp_get_attachment_url( $post->ID );
			
			// Return ID.		
			} else {
				$item = $post->ID;
			}
			
			// Append item.
			$value[] = $item;
		}
		
		// Return.
		return $value;
	}
	
	
	/*
	*  validate_value
	*
	*  description
	*
	*  @type	function
	*  @date	11/02/2014
	*  @since	5.0.0
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/
	
	function validate_value( $valid, $value, $field, $input ){
		
		if( empty($value) || !is_array($value) ) {
		
			$value = array();
			
		}
		
		
		if( count($value) < $field['min'] ) {
		
			$valid = _n( '%s requires at least %s selection', '%s requires at least %s selections', $field['min'], 'acs' );
			$valid = sprintf( $valid, $field['label'], $field['min'] );
			
		}
		
				
		return $valid;
		
	}
	
	
	/*
	*  update_value()
	*
	*  This filter is appied to the $value before it is updated in the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value which will be saved in the database
	*  @param	$post_id - the $post_id of which the value will be saved
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$value - the modified value
	*/
	
	function update_value( $value, $post_id, $field ) {
		
		// Bail early if no value.
		if( empty($value) ) {
			return $value;
		}
		
		// Convert to array.
		$value = acs_array( $value );
		
		// Format array of values.
		// - ensure each value is an id.
		// - Parse each id as string for SQL LIKE queries.
		$value = array_map('acs_idval', $value);
		$value = array_map('strval', $value);
		
		// Return value.
		return $value;
		
	}	
}


// initialize
acs_register_field_type( 'acs_field_gallery' );

endif; // class_exists check

?>