<?php
/*
* Plugin Name: PDF Image Generator
* Plugin URI: http://web.contempo.jp/weblog/tips/p1522
* Description: Generate automatically cover image of PDF by using ImageMagick. Insert PDF link with image into editor. Allow PDF to be set as featured image and to be used as image filetype.
* Author: Mizuho Ogino 
* Author URI: http://web.contempo.jp
* Version: 1.5.6
* License: http://www.gnu.org/licenses/gpl.html GPL v2 or later
* Text Domain: pdf-image-generator
* Domain Path: /languages
*/

if ( !class_exists( 'PIGEN' ) ) {
class PIGEN {


	public function __construct() {
		load_plugin_textdomain( 'pdf-image-generator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
		register_activation_hook( __FILE__, array( $this,'pigen_activate' ) );
		add_action( 'upgrader_process_complete', array( $this,'pigen_upgrader_process_complete' ), 10, 2 );
		add_action( 'admin_menu', array( $this,'pigen_admin_menu' ), 11, 0 );
		add_filter( 'add_attachment', array( $this,'pigen_attachment' ), 11, 1 );
		add_filter( 'media_send_to_editor', array( $this,'pigen_insert' ), 11, 3 );
		add_filter( 'delete_attachment', array( $this,'pigen_delete' ) );
		add_filter( 'wp_mime_type_icon', array( $this,'pigen_change_icon' ), 10, 3 );
		add_filter( 'wp_get_attachment_image_src', array( $this,'pigen_wp_get_attachment_image_src' ), 10, 4 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this,'pigen_wp_get_attachment_image_attributes' ), 10, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this,'pigen_wp_get_attachment_metadata' ), 10, 2 );
		add_filter( 'ajax_query_attachments_args', array( $this,'pigen_ajax_query_attachments_args' ), 11, 1 );
		add_action( 'admin_footer-post-new.php', array( $this,'pigen_override_filter_object' ) );
		add_action( 'admin_footer-post.php', array( $this,'pigen_override_filter_object' ) );
		add_filter( 'attachment_fields_to_edit', array( $this,'pigen_attachment_fields_to_edit' ), 11, 2 );
		add_filter( 'attachment_fields_to_save', array( $this,'pigen_attachment_fields_to_save' ), 11, 2 );
		add_filter( 'pre_get_posts', array( $this,'pigen_attachment_pre_get_posts' ), 10, 1 );
		add_action( 'save_post', array( $this,'pigen_save_post' ), 11, 1 );

	}


	public function pigen_activate() { // Check the server whether or not it has imageMagick enabled.
		$version = $this->pigen_imageMagick_ver();
		if ( $version ) {
			$verify_imagick = 'imageMagick';
			exec( 'which gs', $gs_arr, $gs_res ); 
			if ( $gs_res !== 0 ){ // no gs
				$version = $this->pigen_imagick_ver();
				if ( $version ) {
					$verify_imagick = 'imagick';
				} else {
					_e( 'Please install "GhostScript" before activate the plugin!', 'pdf-image-generator' );
					exit;
				}
			}
		} else {
			$version = $this->pigen_imagick_ver();
			if ( $version ) {
				$verify_imagick = 'imagick';
			} else {
				if ( function_exists( 'exec' ) ) {
					_e( 'Please install "ImageMagick" and "GhostScript" before activate!', 'pdf-image-generator' );
				} else {
					_e( 'Please enable "exec" or "imagick extension" before activate!', 'pdf-image-generator' );
				}
				exit;
			}
		}
		$this->pigen_update_options( $verify_imagick );
		return;
	}


	public function pigen_upgrader_process_complete( $upgrader_object, $options ) {
		$current_plugin_path_name = plugin_basename( __FILE__ );
		if ($options['action'] == 'update' && $options['type'] == 'plugin' && $options['plugins'] ){
			foreach( $options['plugins'] as $each_plugin ){
				if ( $each_plugin == $current_plugin_path_name ){
					$this->pigen_update_options( false );
					return;
				}
			}
		}
	}


	public function pigen_update_options( $verify_imagick ) {
		$opt = get_option( 'pigen_options' );
		if ( !$opt ) $opt = array();
		if ( !$verify_imagick ) {
			$verify_imagick = ( isset($opt['verify_imagick']) && $opt['verify_imagick'] ? $opt['verify_imagick'] : 'imageMagick' );
			if ( strpos( $verify_imagick, 'imagick' ) !== false ) {
				$version = $this->pigen_imagick_ver();
				$verify_imagick = 'imagick';
			}
			if ( empty( $version ) ){
				$version = $this->pigen_imageMagick_ver();
				$verify_imagick = 'imageMagick';
			}
		}
		$update_option = array( 
			'changeicon' => array_key_exists( 'changeicon', $opt ) ? $opt[ 'changeicon' ] : 'true',
			'featured' => array_key_exists( 'featured', $opt ) ? $opt[ 'featured' ] : 'true',
			'hidethumb' => array_key_exists( 'hidethumb', $opt ) ? $opt[ 'hidethumb' ] : 'true',
			'property' => array_key_exists( 'property', $opt ) ? $opt[ 'property' ] : 'true',
			'quality' => array_key_exists( 'quality', $opt ) ? $opt[ 'quality' ] : 80,
			'maxwidth' => array_key_exists( 'maxwidth', $opt ) ? $opt[ 'maxwidth' ] : 1024,
			'maxheight' => array_key_exists( 'maxheight', $opt ) ? $opt[ 'maxheight' ] : 1024,
			'default_size' => array_key_exists( 'default_size', $opt ) && $opt[ 'default_size' ] ? $opt[ 'default_size' ] : 'medium',
			'image_type' => array_key_exists( 'image_type', $opt ) && $opt[ 'image_type' ] ? $opt[ 'image_type' ] : 'jpg',
			'iccprofile' => array_key_exists( 'iccprofile', $opt ) ? $opt[ 'iccprofile' ] : 'true',
			'image_bgcolor' => array_key_exists( 'image_bgcolor', $opt ) && $opt[ 'image_bgcolor' ] ? $opt[ 'image_bgcolor' ] : 'white',
			'keepthumbs' => array_key_exists( 'keepthumbs', $opt ) ? $opt[ 'keepthumbs' ] : 'true',
			'verify_imagick' => $verify_imagick
		);
		update_option( 'pigen_options', $update_option );
	}


	function pigen_imageMagick_ver() {
		$version = 0;
		if ( function_exists( 'exec' ) ) {
			exec( 'convert -version', $arr, $res );
			if ( count( $arr ) > 2 ) {
				preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $arr[0], $v);
				if ( isset( $v[1] ) ) $version = $v[1];
			}
		}
		return $version;
	}


	function pigen_imagick_ver() { 
		$version = 0;
		if ( extension_loaded( 'imagick' ) ) {
			$im = new imagick();
			$v = $im->getVersion();
			preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $v['versionString'], $v);
			$version = isset( $v[1] ) ? $v[1] : 0;
		}
		return $version;
	}


	public function pigen_admin_menu() {
		$page_hook_suffix = add_options_page( __( 'PDF Image Generator Settings', 'pdf-image-generator' ), __( 'PDF IMG Generator', 'pdf-image-generator' ), 'manage_options', __FILE__, array( $this,'pigen_options' ) ); 
		add_action( 'admin_print_scripts-' . $page_hook_suffix, array( $this,'pigen_admin_scripts' ) );	
		add_post_type_support( 'attachment', 'thumbnail' );
	}


	public function pigen_admin_scripts() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'colorpicker_script', plugins_url( 'colorPicker.js', __FILE__ ), array( 'wp-color-picker' ), false, true );
	}


	public function pigen_options() { 
		if ( isset( $_POST[ 'pigen_options_nonce' ] ) && wp_verify_nonce( $_POST[ 'pigen_options_nonce' ], basename( __FILE__ ) ) ) { // Save options
			$update_options = array(
				'changeicon' => isset( $_POST[ 'pigen_changeicon' ] ) ? $_POST[ 'pigen_changeicon' ] : '',
				'featured' => isset( $_POST[ 'pigen_featured' ] ) ? $_POST[ 'pigen_featured' ] : '',
				'hidethumb' => isset( $_POST[ 'pigen_hidethumb' ] ) ? $_POST[ 'pigen_hidethumb' ] : '',
				'property' => isset( $_POST[ 'pigen_property' ] ) ? $_POST[ 'pigen_property' ] : '',
				'quality' => isset( $_POST[ 'pigen_quality' ] ) ? $_POST[ 'pigen_quality' ] : 80,
				'maxwidth' => isset( $_POST[ 'pigen_maxwidth' ] ) ? $_POST[ 'pigen_maxwidth' ] : 1024,
				'maxheight' => isset( $_POST[ 'pigen_maxheight' ] ) ? $_POST[ 'pigen_maxheight' ] : 1024,
				'default_size' => !empty( $_POST[ 'pigen_default_size' ] ) ? $_POST[ 'pigen_default_size' ] : 'medium',
				'image_type' => !empty( $_POST[ 'pigen_image_type' ] ) ? $_POST[ 'pigen_image_type' ] : 'jpg',
				'iccprofile' => isset( $_POST[ 'pigen_iccprofile' ] ) ? $_POST[ 'pigen_iccprofile' ] : '',
				'image_bgcolor' => !empty( $_POST[ 'pigen_image_bgcolor' ] ) ? $_POST[ 'pigen_image_bgcolor' ] : 'white',
				'keepthumbs' => isset( $_POST[ 'pigen_keepthumbs' ] ) ? $_POST[ 'pigen_keepthumbs' ] : '',
				'verify_imagick' => isset( $_POST[ 'pigen_verify_imagick' ] ) ? $_POST[ 'pigen_verify_imagick' ] : 'imageMagick'
			);
			update_option( 'pigen_options', $update_options );
			echo '<div class="updated fade"><p><strong>'. __( 'Options saved.', 'pdf-image-generator' ). '</strong></p></div>';
		}
		$opt = get_option( 'pigen_options' );
		echo '<div class="wrap">'."\n";

		if ( isset( $_GET[ 'run' ] ) ) {

			echo '<h2>' .__( 'Generate uploaded PDF thumbnails', 'pdf-image-generator' ). '</h2>'."\n";

			echo
				'<style type="text/css">'.
				'#pdf-list { padding:20px 0; } '.
				'#pdf-list .pdf { display:table; } '.
				'#pdf-list .pdf .img { display:table-cell; width:120px; padding:5px 0; vertical-align:middle; } '.
				'#pdf-list .pdf img { border:4px solid #999; max-width:100px; max-height:100px; height:auto; width:auto; } '.
				'#pdf-list .pdf.generated img { border-color:#28c; } '.
				'#pdf-list .pdf.generated strong { color:#28c; } '.
				'#pdf-list .pdf.regenerated img { border-color:#2cb; } '.
				'#pdf-list .pdf.regenerated strong { color:#2cb; } '.
				'#pdf-list .pdf.error img { border-color:#c22; } '.
				'#pdf-list .pdf.error strong { color:#c22; } '.
				'#pdf-list .pdf .txt { display:table-cell; font-size:12px; vertical-align:middle; } '.
				'@-webkit-keyframes spin { 0%{-webkit-transform:rotate(0deg);} 100%{-webkit-transform:rotate(720deg);} } '.
				'@keyframes spin{ 0%{transform:rotate(0deg);} 100%{transform:rotate(720deg);} } '.
				'#pdf-generating { position:fixed; right:10px; bottom:10px; z-index:2; -webkit-box-sizing:border-box; -moz-box-sizing:border-box; -ms-box-sizing:border-box; box-sizing:border-box; display:block; width:35px; height:35px; margin:auto; border-width:6px; border-style:solid; border-color:transparent #0095cc; border-radius:18px; -webkit-animation:spin 1.5s ease infinite; animation:spin 1.5s ease infinite;'.
				'</style>'."\n".
				'<div id="pdf-generating"></div>';

			echo '<div id="pdf-list">'."\n";

			$pigen_num = 0;
			global $wpdb;
			$pdfs = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE $wpdb->posts.post_type = 'attachment' AND $wpdb->posts.post_mime_type = 'application/pdf' " );
			if ( $pdfs ): foreach( $pdfs as $pdf ):
				$regenerate = ( $_GET[ 'run' ] == 'regenerate' ? true : false ); 
				$thumbnail_id = get_post_meta( $pdf->ID, '_thumbnail_id', true );
				if ( !$thumbnail_id || $regenerate ) {
					$return = $this->pigen_attachment( $pdf->ID );
					$thumbnail_id = get_post_meta( $pdf->ID, '_thumbnail_id', true );
					if ( $thumbnail_id && $return ){
						$pigen_num ++;
						$thumbnail = wp_get_attachment_image( $thumbnail_id, 'medium' );
						echo 
							'<div class="pdf ' .( $regenerate ? ' regenerated' : 'generated' ). '">'.
							'<p class="img">'.$thumbnail.'</p>'.
							'<p class="txt">PDF ID: <a href="'.get_edit_post_link( $pdf->ID ).'">'.$pdf->ID.'</a><br/>Thumb ID: <a href="'.get_edit_post_link( $thumbnail_id ).'">'.$thumbnail_id.'</a><br/><strong>' .( $regenerate ? 'The image was REGENERATED' : 'The new image was GENERATED' ). '</strong></p>'.
							'</div>';
							// echo "\n".get_attached_file( $thumbnail_id );
							// print_r(wp_get_attachment_metadata( $thumbnail_id, false ));
							// print_r(get_post( $thumbnail_id ));

					} else {
						echo 
							'<div class="pdf error">'.
							'<p class="txt">PDF ID: <a href="'.get_edit_post_link( $pdf->ID ).'">'.$pdf->ID.'</a><br/><strong>The image was not GENERATED!!</strong></p>'.
							'</div>';
					}
				} else {
					echo 
						'<div class="pdf">'.
						'<p class="img">'.get_the_post_thumbnail( $pdf->ID, 'medium' ).'</p>'.
						'<p class="txt">PDF ID: <a href="'.get_edit_post_link( $pdf->ID ).'">'.$pdf->ID.'</a><br/>An image already exists</p>'.
						'</div>';
				}
			endforeach; endif;
			echo '</div><!-- #pdf-list -->'."\n";
			echo '<style type="text/css">#pdf-generating { display:none; }</style>'."\n";
			if ( $pigen_num == 0 ) $pigen_num = 'No image'; elseif ( $pigen_num == 1 ) $pigen_num = '1 image'; else $pigen_num = $pigen_num.' images'; echo '<h3>'.$pigen_num.' generated.</h3>'."\n";
			echo '<p><a href="'.remove_query_arg( 'run', $_SERVER[ 'REQUEST_URI' ] ).'" class="button button-primary">' .__( 'Back to PDF Image Generator Settings', 'pdf-image-generator' ).'</a></p><br/>'."\n";

		} else {
			echo
				'<style type="text/css"> '.
				'#pigen-fields { margin:0; font-size:1em; } '.
				'#pigen-fields > div { padding:0; margin:10px 0; } '.
				'#pigen-fields > div + div { margin-top:25px; } '.
				'#pigen-fields p { clear:left; margin:5px 0 0; } '.
				'#pigen-fields p:after{ display:block; content:""; clear:left; } '.
				'#pigen-fields .float { float:left; clear:none; margin-right:25px; } '.
				'#pigen-fields p:not( .subfield ) label { font-size:1.2em; padding:5px 0; } '.
				'#pigen-fields p.subfield { margin:5px 0 0 28px; font-size:12px; line-height:32px; } '.
				'#pigen-fields p.subfield.note { line-height:1.5em; } '.
				'#pigen-fields input:not( [type="radio"] ):not( [type="checkbox"] ), #pigen-fields select { display:inline-block; font-size:16px; verticla-align:middle; padding:5px; line-height:20px; height:32px; margin:0 5px 0 0; } '.
				'#pigen-fields label { display:inline; verticla-align:middle; } '.
				'#pigen-fields input[type="radio"], #pigen-fields input[type="checkbox"] { display:inline-block; margin:0 5px 0 0; } '.
				'#pigen-fields input:disabled + label { color:#aaa; } '.
				'#pigen-fields .wp-color-result{ height:30px; margin:0 5px 0 0; display:inline-block; } '.
				'#pigen-fields .wp-color-result:after { line-height:30px; } '.
				'#pigen-fields .wp-color-picker { line-height:20px; margin:0; } '.
				'#pigen-fields .wp-picker-container input.button[type="button"] { display:inline-block; font-size:12px; } '.
				'#pigen-fields input + label { margin-left:5px; } '.
				'</style>'."\n".
				'<h2>' .__( 'PDF Image Generator Settings', 'pdf-image-generator' ).'</h2>'."\n".
				'<h3>' .__( 'Default plugin settings', 'pdf-image-generator' ). '</h3>'."\n".
				'<p>'.__( 'If you want to disable some functions, uncheck boxes below.', 'pdf-image-generator' ).'</p>'."\n".
				'<form action="" method="post">'."\n".
				"\t".'<fieldset id="pigen-fields">'."\n".
				"\t\t".'<legend class="screen-reader-text"><span>Default plugin settings</span></legend>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_changeicon" type="checkbox" id="pigen_changeicon" value="true" '.( isset( $opt[ 'changeicon' ] ) && $opt[ 'changeicon' ] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_changeicon">'.__( 'Display Generated Image instead of default wp mime-type icon', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t".'</div>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_featured" type="checkbox" id="pigen_featured" value="true" '.( isset( $opt[ 'featured' ] ) && $opt[ 'featured' ] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_featured">'.__( 'Allow to set PDF thumbnail as Featured Image', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t".'</div>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_hidethumb" type="checkbox" id="pigen_hidethumb" value="true" '.( isset( $opt[ 'hidethumb' ] ) && $opt[ 'hidethumb' ] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_hidethumb">'.__( 'Hide Generated Images themselves in the Media Library', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t\t".'<p class="subfield">'.__( 'When this checkbox is unchecked and a PDF is deleted, a Generated Image will NOT be deleted together.', 'pdf-image-generator' ). '</p>'."\n".
				"\t\t".'</div>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_property" type="checkbox" id="pigen_property" value="true" '.( isset( $opt[ 'property' ] ) && $opt[ 'property' ] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_property">'.__( 'Customize Generated Image properties', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t\t".'<p class="subfield note">'.__( 'In case of frequent HTTP error when uploading PDF, there maybe not enough memory.', 'pdf-image-generator' ).'<br/>'.__( 'By unchecking this checkbox, the following will be disabled, and it helps to reduce memory spending.', 'pdf-image-generator' ). '</p>'."\n".
				"\t\t\t".'<p class="subfield"><span class="float"><label for="pigen_maxwidth">'.__( 'Max Width' ).': <input name="pigen_maxwidth" type="number" id="pigen_maxwidth" value="'.( $opt[ 'maxwidth' ] ).'" onKeyup="this.value=this.value.replace( /[^0-9a-z]+/i,\'\' )" /> px</label></span><span class="float"><label for="pigen_maxheight">'.__( 'Max Height' ).': <input name="pigen_maxheight" type="number" id="pigen_maxheight" value="'.$opt[ 'maxheight' ].'" onKeyup="this.value=this.value.replace( /[^0-9a-z]+/i,\'\' )" /> px</label></span><span class="float"><label for="pigen_quality">'.__( 'Compression Quality', 'pdf-image-generator' ).': <input name="pigen_quality" type="number" min="1" max="100" id="pigen_quality" value="'.$opt[ 'quality' ].'" onKeyup="this.value=this.value.replace( /[^0-9a-z]+/i,\'\' )" /> ( 1 - 100 )</label></span></p>'."\n".
				"\t\t\t".'<p class="subfield note">'.__( 'The parameter will be calculated if 0 or blank is entered.', 'pdf-image-generator' ).'</p>'."\n".	
				"\t\t\t".'<p class="subfield"><span class="float">'.__( 'Select Generated Image File Type', 'pdf-image-generator' ).': </span><span class="float"><label for="pigen_image_type_jpg"><input type="radio" id="pigen_image_type_jpg" name="pigen_image_type" value="jpg" '.( isset( $opt[ 'image_type' ] ) && $opt[ 'image_type' ] === 'jpg' ? 'checked="checked"' : '' ).' />'.__( 'jpg' ).'</label></span><span class="float"><label for="pigen_image_type_png"><input type="radio" id="pigen_image_type_png" name="pigen_image_type" value="png" '.( isset( $opt[ 'image_type' ] ) && $opt[ 'image_type' ] === 'png' ? 'checked="checked"' : '' ).' />'.__( 'png' ).'</label></span></p>'."\n".
				"\t\t\t".'<p class="subfield"><label for="pigen_iccprofile"><input type="checkbox" id="pigen_iccprofile" name="pigen_iccprofile" value="true" '.( isset( $opt[ 'iccprofile' ] ) && $opt[ 'iccprofile' ] === 'true' ? 'checked="checked"' : '' ).' />'.__( 'Convert CMYK files to RGB.', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t\t".'<p class="subfield"><span class="float"><label for="pigen_image_bgcolor">'.__( 'Background Color' ).': <input class="pigen_color_picker" name="pigen_image_bgcolor" type="text" id="pigen_image_bgcolor" value="'.( isset( $opt[ 'image_bgcolor' ] ) ? $opt[ 'image_bgcolor' ] : '' ).'" /></label></span></p>'."\n".
				"\t\t".'</div>'."\n";

			$select = '';
			$sizes = apply_filters( 'image_size_names_choose', array( 
				'thumbnail'	=> __( 'Thumbnail' ),
				'medium'	=> __( 'Medium size' ),
				'large'		=> __( 'Large size' ),
				'fullsize'	=> __( 'Full Size' ),
				'title'		=> __( 'Default' ).' ( '.__( 'Title' ).' )',
				'url'		=> __( 'URL' ),
				'caption'	=> __( 'Caption' ),
			) );
			foreach ( $sizes as $slug => $name ) $select .= '<option '.( isset( $opt[ 'default_size' ] ) && $opt[ 'default_size' ] === $slug ? 'selected' : '' ).' value="'.esc_attr( $slug ).'">'. esc_html( $name ). '</option>'; 
			echo 
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><label for="pigen_default_size">'.__( 'Default insert size ( or type )', 'pdf-image-generator' ).': </label><select name="pigen_default_size" id="pigen_default_size">'.$select.'</select></p>'."\n".
				"\t\t\t".'<p class="subfield note">'.__( '"Default ( Title )" allows to insert default html.', 'pdf-image-generator' ).'<br/>'.__( 'If you are using a document viewer plugin like GDE, select it.', 'pdf-image-generator' ).'</p>'."\n".
				"\t\t".'</div>'."\n";

			$verify_imagick = ( isset( $opt[ 'verify_imagick' ] ) ? $opt[ 'verify_imagick' ] : 'imageMagick' );
			$imageMagick_ver = $this->pigen_imageMagick_ver();
			$imagick_ver = $this->pigen_imagick_ver();
			echo
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_keepthumbs" type="checkbox" id="pigen_keepthumbs" value="true" '.( isset( $opt[ 'keepthumbs' ] ) && $opt[ 'keepthumbs' ] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_keepthumbs">'.__( 'Keep Generated Images after the plugin is uninstalled', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t\t".'<p class="subfield">' .__( 'If the plugin is deactivated, Generated Image files will be handled as ordinary image files.', 'pdf-image-generator' ). '</p>'."\n".
				"\t\t".'</div>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><span class="float"><input type="radio" id="pigen_verify_imageMagick" name="pigen_verify_imagick" value="imageMagick" '.( $verify_imagick === 'imageMagick' ? 'checked="checked"' : '' ).( $imageMagick_ver ? '' : ' disabled' ).' /><label for="pigen_verify_imageMagick">'.__( 'Use imageMagick with exec function', 'pdf-image-generator' ).'</label></span><span class="float"><input type="radio" id="pigen_verify_imagick" name="pigen_verify_imagick" value="imagick" '.( $verify_imagick === 'imagick' ? 'checked="checked"' : '' ).( $imagick_ver ? '' : ' disabled' ).' /><label for="pigen_verify_imagick">'.__( 'Use imagick PHP Extension', 'pdf-image-generator' ).'</label></span></p>'."\n".
				"\t\t\t".'<p class="subfield note">'.sprintf( __( 'Your server using imageMagick %s', 'pdf-image-generator' ), $verify_imagick === 'imagick' ? $imagick_ver : $imageMagick_ver ).'</p>'."\n".
				"\t\t".'</div>'."\n".
				"\t".'</fieldset>'."\n".
				"\t".'<p class="submit"><input type="submit" name="Submit" class="button-primary" value="'.__( 'Save changes', 'pdf-image-generator' ).'" /></p>'."\n".
				"\t".'<input type="hidden" name="pigen_options_nonce" value="'.wp_create_nonce( basename( __FILE__ ) ).'" />'."\n".
				'</form>'."\n";
			echo
				'<h3 style="margin-top:40px;">' .__( 'Generate uploaded PDF thumbnails', 'pdf-image-generator' ). '</h3>'."\n".
				'<p>' .__( 'It allows you to generate images of any already-uploaded PDFs in the Media Library.', 'pdf-image-generator' ). '<br/>' .__( 'Please save changes before running the script.', 'pdf-image-generator' ). '<br/>' .__( 'If regenerating images, all existing thumbnails will be deleted.', 'pdf-image-generator' ). '</p>'."\n".
				'<p><a href="'.add_query_arg( 'run', 'generate', $_SERVER[ 'REQUEST_URI' ] ).'" class="button button-primary">' .__( 'Generate images of PDFs that have no thumbnail', 'pdf-image-generator' ). '</a></p>'."\n".
				'<p><a href="'.add_query_arg( 'run', 'regenerate', $_SERVER[ 'REQUEST_URI' ] ).'" class="button button-primary">' .__( 'Regenerate and replace images of all PDFs', 'pdf-image-generator' ). '</a></p>'."\n";
		}
		echo 
			'<h3 style="margin-top:40px;">' .__( 'Donate', 'pdf-image-generator' ).'</h3>'."\n".
			'<p>' .__( 'If you find this plugin useful and you want to support its future development, please consider making a donation.', 'pdf-image-generator' ). '</p>'."\n".
			'<p><a href="http://web.contempo.jp/donate?pigen" class="button button-primary" target="_blank">' .__( 'Donate via PayPal', 'pdf-image-generator' ).'</a></p>'."\n".
			'<p style="padding-top:30px; font-size:.9em;">' .__( 'If you are having problems with the plugin, see the plugin page on <a href="https://wordpress.org/plugins/pdf-image-generator/" target="_blank">the WordPress.org plugin directory</a>.', 'pdf-image-generator' ).'</p>'."\n".
			'</div><!-- .wrap -->'."\n".
			'<script type="text/javascript">jQuery( function( $ ) { '.
				'$( ".pigen_color_picker" ).wpColorPicker(); '.
			'} );</script>'."\n";
	}


	public function pigen_generate( $attachment_id ){ // Generate thumbnail from PDF
		set_time_limit( 0 );
		$opt = get_option( 'pigen_options' );
		$property = ( isset( $opt[ 'property' ] ) && $opt[ 'property' ] ? $opt[ 'property' ] : false );
		$iccprofile = ( $property && isset( $opt[ 'iccprofile' ] ) && $opt[ 'iccprofile' ] ? true : false );
		$image_type = ( $property && isset( $opt[ 'image_type' ] ) && $opt[ 'image_type' ] == 'png' ? 'png' : 'jpg' );
		$max_width = ( !empty( $opt[ 'maxwidth' ] ) ? ( int ) $opt[ 'maxwidth' ] : 1024 );
		$max_height = ( !empty( $opt[ 'maxheight' ] ) ? ( int ) $opt[ 'maxheight' ] : 1024 );
		$setReso = 128;
		if ( $property ){
			$setReso = ceil( max( $max_height, $max_width )*0.16 );
			if ( $setReso < 128 ) $setReso = 128;
			$quality = ( !empty( $opt[ 'quality' ] ) ? ( int ) $opt[ 'quality' ] : 80 );
			if ( $quality > 100 ) $quality = 100;
		}
		$image_bgcolor = ( $property && isset( $opt[ 'image_bgcolor' ] ) && $opt[ 'image_bgcolor' ] ? $opt[ 'image_bgcolor' ] : 'white' );
		$verify_imagick = ( isset( $opt[ 'verify_imagick' ] ) ? $opt[ 'verify_imagick' ] : '' );

		$file = get_attached_file( $attachment_id );
		$new_filename = sanitize_file_name( str_replace( '.pdf', '-pdf', basename( $file ) ) ).'.'.$image_type;
		$new_filename = wp_unique_filename( dirname( $file ), $new_filename );
		$new_filename = apply_filters( 'pigen_filter_convert_file_basename', $new_filename );
		$file_url = str_replace( basename( $file ), $new_filename, $file );

		if ( $verify_imagick == 'imagick' ) { // Imagick API
			$version = $this->pigen_imagick_ver();
			try { 
				$imagick = new imagick();
				$imagick->setResolution( $setReso, $setReso );
				$imagick->readimage( $file.'[0]' );
				if ( $property ) {
					$imagick->setCompressionQuality( $quality );
					$imagick->scaleImage( $max_width, $max_height, true );
				} 
				$imagick->setImageFormat( $image_type ); 
				$imagick->setImageBackgroundColor( $image_bgcolor );
				if ( method_exists( 'Imagick','setImageAlphaChannel' ) ) {
					if ( defined( 'Imagick::ALPHACHANNEL_REMOVE' ) ){ // Imagick::ALPHACHANNEL_REMOVE added in 3.2.0b2
						$imagick->setImageAlphaChannel( Imagick::ALPHACHANNEL_REMOVE );
					} else {
						$imagick->setImageAlphaChannel( 11 );
					}
				} 
				if ( method_exists( 'Imagick','mergeImageLayers' ) ){
					$imagick->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
				} else { // Imagick::flattenImages is deprecated in PHP 5.6
					$imagick = $imagick->flattenImages();
				} 
				$imagick = apply_filters( 'pigen_filter_convert_imagick', $imagick );
				$colorspace = $imagick->getImageColorspace();
				if ( $iccprofile && $colorspace == Imagick::COLORSPACE_CMYK ) {
					$plugin_dir_path = plugin_dir_path( __FILE__ );
					
					if ( version_compare( $version,'6.3.6' ) >= 0 ){
						$profiles = $imagick->getimageprofiles( '*', false );
						$has_icc_profile = ( array_search( 'icc', $profiles ) !== false );
						if ( $has_icc_profile === false ){
							$icc_cmyk = wp_remote_get( $plugin_dir_path.'iccprofiles/WebCoatedFOGRA28.icc' );
							$imagick->profileImage( 'icc', $icc_cmyk );
							unset( $icc_cmyk );
						}
					} 
					$icc_rgb = wp_remote_get( $plugin_dir_path.'iccprofiles/sRGB_ICC_v4_appearance_beta_displayclass.icc' );
					$imagick->profileImage( 'icc', $icc_rgb );
					unset( $icc_rgb ); 
					if( version_compare( phpversion(),'5.3' ) >= 0 ){ //PHP 5.3 hack for inverted colors
						$imagick->negateImage( false, Imagick::CHANNEL_ALL );
					} else { //Adjust gamma by 20% for 5.2.x
						$imagick->levelImage( 0, 2.0, $range[ 'quantumRangeString' ] );
					} 
					$imagick->setImageColorSpace( Imagick::COLORSPACE_RGB );
				}
				$imagick->stripImage();
				$imagick->writeImage( $file_url ); 
				$imagick->clear();
			} catch ( ImagickException $err ){
				error_log($err);
				$file_url = false;
			} catch ( Exception $err ){
				error_log($err);
				$file_url = false;
			}
		} else { // imageMagick
			$version = $this->pigen_imageMagick_ver();
			if ( version_compare( $version,'6.7.7' ) >= 0 ) {
				$density = "-density {$setReso} -set units PixelsPerInch"; 
				$alphaoff = "-alpha remove"; 
			} else {
				$alphaoff = "-flatten"; 
				$density = "-density 72"; 
			}
			if ( $property ) {
				$colorspace = "";
				if ( $iccprofile ){
					$get_color = exec( "identify -format '%[colorspace]' {$file}", $output, $return );
					if ( !$return && stripos( $get_color, 'cmyk' ) !== false ){ // Return non-zero upon an error
						$plugin_dir_path = plugin_dir_path( __FILE__ );
						$CMYK_icc = $plugin_dir_path.'iccprofiles/WebCoatedFOGRA28.icc';
						$RGB_icc = $plugin_dir_path.'iccprofiles/sRGB_ICC_v4_appearance_beta_displayclass.icc';
						$colorspace = "-strip -profile {$CMYK_icc} -profile {$RGB_icc} -colorspace sRGB"; // Convert CMYK to sRGB
					}
				}
				if ( $image_bgcolor != 'white' && preg_match( '/^#[a-f0-9]{6}$/i', $image_bgcolor ) ) $image_bgcolor = "\"$image_bgcolor\"";
				$imageMagick = "convert {$density} -quality {$quality} -background {$image_bgcolor} {$alphaoff} {$file}[0] -resize {$max_width}x{$max_height} {$colorspace} {$file_url}";
			} else {
				$imageMagick = "convert -density {$setReso} -background white {$file}[0] {$file_url}";
			}

			$imageMagick = apply_filters( 'pigen_filter_convert_imageMagick', $imageMagick, $file.'[0]', $file_url, $max_width, $max_height );
			exec( $imageMagick, $output, $return ); // Convert pdf to image
			if ( $return !== 0 ) {
				$file_url = false; // Return non-zero upon an error
				error_log( "convert is failed : {$file}[0]" );
			}
		}
		return $file_url;
	}


	public function pigen_attachment( $attachment_id ){ // Generate thumbnail from PDF
		if ( get_post_mime_type( $attachment_id ) === 'application/pdf' ){

			$thumbnail_id = get_post_meta( $attachment_id, '_thumbnail_id', true );
			if ( $thumbnail_id ){ // delete ex thumb 
				$ex_file = get_attached_file( $thumbnail_id );
				$meta = wp_get_attachment_metadata( $thumbnail_id );

				if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
					$uploadpath = wp_get_upload_dir();
					foreach ( $meta['sizes'] as $size => $sizeinfo ) {
						$intermediate_file = str_replace( basename( $ex_file ), $sizeinfo['file'], $ex_file );
						wp_delete_file( path_join( $uploadpath['basedir'], $intermediate_file ) );
					}
				};
				wp_delete_file( $ex_file );
			}
			$new_file = $this->pigen_generate( $attachment_id );
			if ( file_exists( $new_file ) ){ // new thumb
				$file_title = esc_attr( get_the_title( $attachment_id ) );
				$attachment = get_post( $attachment_id );
				$filetype = wp_check_filetype( basename( $new_file ), null );
				$new_thumb = array( 
					'post_type' => 'attachment',
					'post_mime_type' => $filetype['type'],
					'post_title' => $file_title,
					'post_excerpt' => $attachment->post_excerpt,
					'post_content' => $attachment->post_content,
					'post_parent' => $attachment_id,
					'guid' => dirname($attachment->guid). '/' .basename( $new_file )
				);
				if ( $thumbnail_id ){ // if regenerating, overwite ex thumb ID.
					$new_thumb['ID'] = $thumbnail_id;
					wp_update_post( $new_thumb );
 					update_attached_file( $thumbnail_id, $new_file );
				} else { // create new attachment
					$thumbnail_id = wp_insert_attachment( $new_thumb, $new_file );
					update_post_meta( $thumbnail_id, '_wp_attachment_image_alt', sprintf( __( 'thumbnail of %s', 'pdf-image-generator' ), $file_title ) );				
					update_post_meta( $attachment_id, '_thumbnail_id', $thumbnail_id );
				}
				$metadata = wp_generate_attachment_metadata( $thumbnail_id, $new_file );
				if ( !empty( $metadata ) && !is_wp_error( $metadata ) ) {
					wp_update_attachment_metadata( $thumbnail_id, $metadata );
				}

				$return = $thumbnail_id;
			} 
		}
		if ( empty( $return ) ) $return = false;
		return $return;
	}


	public function pigen_insert( $html, $attach_id, $attachment ) { // Insert thumbnail instead of PDF
		if ( $attach_id && get_post_mime_type ( $attach_id ) === 'application/pdf' ){
			$attach_image_id = get_post_meta( $attach_id, '_thumbnail_id', true );
			if ( !$attach_image_id ) return $html;
			$linkto = get_post_meta( $attach_id, '_pigen_attach_linkto', true );
			if ( $linkto === 'file' ) $attach_url = wp_get_attachment_url( $attach_id );
			elseif ( $linkto === 'image' ) $attach_url = wp_get_attachment_url( $attach_image_id );
			elseif ( $linkto === 'post' ) $attach_url = get_attachment_link( $attach_id );
			else $attach_url = '';
			$attach_title = isset( $attachment[ 'post_title' ] ) ? $attachment[ 'post_title' ] : '';
			$attach_caption = isset( $attachment[ 'post_excerpt' ] ) ? $attachment[ 'post_excerpt' ] : '';
			$size = get_post_meta( $attach_id, '_pigen_attach_size', true );
			$attach_output = '';
			$attach_image = '';
			if ( $size === 'url' ){
				$attach_output = $attach_url;
			} elseif ( $size === 'title' ){
				if ( strpos( $html,'href=' ) !== false && strpos( $html,'class=' ) === false ) { // WP default link
					$attach_output = $attach_title;
				} else {
					$html = $html; // Leave default html for other doc viewer plugins
				}
			} elseif ( $size === 'caption' ){
				if ( $attach_caption ){
					$attach_output = $attach_caption;
				} else {
					$attach_output = $attach_title;
				}
			} else {
				$attach_image = wp_get_attachment_image_src( $attach_image_id, $size );
				$align = get_post_meta( $attach_id, '_pigen_attach_align', true );
				if ( $attach_caption ) $align = ''; elseif ( !$align ) $align = 'none';
				$attach_output = '<img src="'. $attach_image[0] .'" alt="'.get_post_meta( $attach_image_id, '_wp_attachment_image_alt', true ).'" width="'. $attach_image[1] .'" height="'. $attach_image[2] .'" class="'.( $align ? 'align' .$align. ' ' : '' ). 'size-' .esc_attr( $size ). ' wp-image-'.$attach_image_id.' thumb-of-pdf" />';
				$attach_output = apply_filters( 'pigen_filter_attachment_output', $attach_output, $attach_image_id, $attach_image, $size, $align );
			} 
			if ( $attach_output ){
				$html = $attach_output;
				if ( $attach_url ) $html = '<a class="link-to-pdf'. ( $attach_image ? '' : ' textlink-to-pdf' ). '" href="'.$attach_url.'" rel="attachment wp-att-' .esc_attr( $attach_id ). '" title="'.esc_attr( $attach_title ).'" target="_blank">' .$html. '</a>';
				$html = apply_filters( 'pigen_filter_attachment_link', $html, $attach_id, $attach_url, $attach_output );
			}
			if ( $attach_image && $attach_caption ){
				$html = '[caption id="attachment_'.esc_attr( $attach_id ).'" align="align' .$align. '" width="'.$attach_image[1].'"]'.$html.' '.$attach_caption.'[/caption]';
			}
		}
		return $html;
	}


	public function pigen_delete( $attachment_id ) { // Delete thumbnail when PDF is deleted
		if ( get_post_mime_type ( $attachment_id ) === 'application/pdf' ){
			$thumbnail_id = get_post_meta( $attachment_id, '_thumbnail_id', true );
			$opt = get_option( 'pigen_options' );
			if ( $thumbnail_id ){
				if ( isset( $opt[ 'hidethumb' ] ) && $opt[ 'hidethumb' ] == 'true' ) {
					wp_delete_attachment( $thumbnail_id );
				} else {
					// wp_update_post( array( 'ID' => $attachment_id, 'guid' => '' ) );
					$post_parent = get_post( $attachment_id )->post_parent;
					if ( $post_parent ){
						wp_update_post( array( 'ID' => $thumbnail_id, 'post_parent' => $post_parent ) );
					}
				}
			}
		}
	}


	public function pigen_change_icon ( $icon, $mime, $attachment_id ){ // Display thumbnail instead of document.png
		$opt = get_option( 'pigen_options' );
		if ( isset( $opt[ 'changeicon' ] ) && $opt[ 'changeicon' ] === '' ) return $icon;
		if ( strpos( $_SERVER[ 'REQUEST_URI' ], '/wp-admin/upload.php' ) === false && $mime === 'application/pdf' ){
			$get_image = wp_get_attachment_image_src ( $attachment_id, 'medium' );
			if ( $get_image ) {
				$icon = $get_image[0];
			} 
		}
		return $icon;
	}


	public function pigen_wp_get_attachment_image_src( $image, $attachment_id, $size, $icon ) {
		if ( get_post_mime_type ( $attachment_id ) === 'application/pdf' ){
			$thumbnail_id = get_post_meta( $attachment_id, '_thumbnail_id', true );
			if ( $thumbnail_id ){
				$get_image = wp_get_attachment_image_src ( $thumbnail_id, $size );
				if ( $get_image ) {
					$image = array( $get_image[0], (int)$get_image[1], (int)$get_image[2] );
				}
			} 
		}
		return $image;
	}


	public function pigen_wp_get_attachment_image_attributes( $attr, $attachment ) {
		if ( $attachment->post_parent && get_post_mime_type( $attachment->post_parent ) === 'application/pdf' ){
			$attr[ 'class' ] .= ' thumb-of-pdf';
		}
		return $attr;
	}


	public function pigen_wp_get_attachment_metadata( $data, $post_id ) {
		if ( $data && $post_id && get_post_mime_type ( $post_id ) === 'application/pdf' ){
			$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
			if ( $thumbnail_id ){
				$data = serialize( get_post_meta( $thumbnail_id, '_wp_attachment_metadata', true ) );
			}
		}
		return $data;
	}


	public function pigen_ajax_query_attachments_args( $query ) { 

		global $wpdb;
		$opt = get_option( 'pigen_options' );
		if ( isset( $query[ 'post_mime_type' ] ) && $query[ 'post_mime_type' ] === 'image_n_pdf' ){
			$post_parent = ( isset( $query[ 'post_parent' ] ) && $query[ 'post_parent' ] ? '&post_parent='.$query[ 'post_parent' ] : '' );
			if ( isset( $opt[ 'featured' ] ) && $opt[ 'featured' ] === 'true' ){
				$query[ 'post_mime_type' ] = array( 'image','application/pdf' );
			}
		} 
		if ( isset( $opt[ 'hidethumb' ] ) && $opt[ 'hidethumb' ] === '' ){
			if ( isset( $query[ 'post_parent' ] ) && $query[ 'post_parent' ] ){
				$post__in = array();

				$results = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE $wpdb->posts.post_parent = ".$query[ 'post_parent' ]." AND $wpdb->posts.post_type = 'attachment' " );

				if ( $results ): foreach ( $results as $result ):
					$post__in[] = $result->ID;
					$thumbnail_id = get_post_meta( $result->ID, '_thumbnail_id', true );
					if ( get_post_mime_type ( $result->ID ) === 'application/pdf' && $thumbnail_id ) $post__in[] = $thumbnail_id;
				endforeach; endif;
				if ( $post__in ){
					$query[ 'post_parent' ] = false;
					$query[ 'post__in' ] = $post__in;
				}
			}
		} else { // Hide thumbnail files in the library.
			$post__not_in = array();
			$results = $wpdb->get_results( 
				"SELECT meta_value FROM $wpdb->posts, $wpdb->postmeta WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id AND $wpdb->posts.post_type = 'attachment' AND $wpdb->posts.post_mime_type = 'application/pdf' AND $wpdb->postmeta.meta_key = '_thumbnail_id' "
			);
			if ( $results ): foreach ( $results as $result ):
				$post__not_in[] = $result->meta_value;
			endforeach; endif;
			$query[ 'post__not_in' ] = $post__not_in;
		}
		return $query;

	}


	public function pigen_attachment_pre_get_posts( $query ) {
		global $pagenow;
		if ( !is_admin() || $pagenow !== 'upload.php' || $query->get( 'post_type' ) !== 'attachment' ) return;
		$opt = get_option( 'pigen_options' );
		if ( isset( $opt[ 'hidethumb' ] ) && $opt[ 'hidethumb' ] !== '' ){
			$post__not_in = array();
			global $wpdb;
			$results = $wpdb->get_results( 
				"SELECT meta_value FROM $wpdb->posts, $wpdb->postmeta WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id AND $wpdb->posts.post_type = 'attachment' AND $wpdb->posts.post_mime_type = 'application/pdf' AND $wpdb->postmeta.meta_key = '_thumbnail_id' "
			);
			if ( $results ): foreach ( $results as $result ):
				$post__not_in[] = $result->meta_value;
			endforeach; endif;
			if ( $post__not_in ) $query->set( 'post__not_in', $post__not_in );

		}
		return $query;
	}


	public function pigen_override_filter_object() { // Override relevant media manager javascript functions 
		$opt = get_option( 'pigen_options' );
		if ( isset( $opt[ 'featured' ] ) && $opt[ 'featured' ] === '' ) return;
?>
	<script type="text/javascript">
		wp.media.view.AttachmentFilters.Uploaded.prototype.createFilters = function() {
			var type = this.model.get( 'type' ),
				types = wp.media.view.settings.mimeTypes,
				text;
			if ( types && type ) text = types[ type ];
			this.filters = {
				all: {
					text: text || <?php echo '\''.__( 'All' ).'\''; ?>,
					props: {
						uploadedTo: null,
						orderby: 'date',
						order:   'DESC'
					},
					priority: 10
				},

				uploaded: {
					text: <?php echo '\''.__( 'Uploaded to this post' ).'\''; ?>,
					props: {
						uploadedTo: wp.media.view.settings.post.id,
						orderby: 'menuOrder',
						order:   'ASC'
					},
					priority: 20
				},

				unattached: {
					text: <?php echo '\''.__( 'Unattached' ).'\''; ?>,
					props: {
						uploadedTo: 0,
						orderby: 'menuOrder',
						order:   'ASC'
					},
					priority: 50
				}
			};
			if ( this.options.controller._state === 'featured-image' ){
				this.filters = {
					all: { 
						text: <?php echo '\''.__( 'Image' ).' & '.__( 'PDF' ).'\''; ?>,
						props: { 
							type: 'image_n_pdf', 
							uploadedTo: null, 
							orderby: 'date', 
							order: 'DESC' 
						}, 
						priority: 10 
					},

					image: { 
						text: <?php echo '\''.__( 'Image' ).'\''; ?>,
						props: { 
							type: 'image',
							uploadedTo: null,
							orderby: 'date',
							order: 'DESC'
						}, 
						priority: 20 
					},

					uploaded: { 
						text: <?php echo '\''.__( 'Uploaded to this post' ).'\''; ?>, 
						props: { 
							type: 'image_n_pdf', 
							uploadedTo: wp.media.view.settings.post.id, 
							orderby: 'menuOrder', 
							order: 'ASC' 
						}, 
						priority: 30 
					},

					unattached: { 
						text: <?php echo '\''.__( 'Unattached' ).'\''; ?>,
						props: { 
							status: null,
							uploadedTo: 0,
							type: null,
							orderby: 'menuOrder',
							order: 'ASC'
						},
						priority: 50
					},
				};
			}
		}; // End create filters
<?php 
	if ( isset( $_GET[ 'post' ] ) ):
		$post_id = $_GET[ 'post' ];
		$featuredimage = get_post_meta( $post_id, '_thumbnail_id', true );
		if ( $featuredimage ) {
			$pdf_id = get_post( $featuredimage )->post_parent;
			if ( $pdf_id && get_post_mime_type ( $pdf_id ) === 'application/pdf' ){
				echo "\t\t".'wp.media.view.settings.post.featuredImageId = '.$pdf_id.';'."\n";
			}
		}
	endif;
?>
		wp.media.view.Modal.prototype.on( 'open', function(){
			<!-- wp.media.frame.content.get().setImageAlphaChannel.selection.reset(); -->
			jQuery( '.media-modal' ).find( 'a.media-menu-item' ).click( function(){ 
				if ( jQuery( this ).html() === "<?php _e( 'Featured Image' ); ?>" ){
					jQuery( 'select.attachment-filters [value="uploaded"]' ).attr( 'selected', true ).parent().trigger( 'change' ); 
				}
			} );
		});
		wp.media.featuredImage.frame().on( 'open', function(){ 
			jQuery( '.media-modal' ).addClass( 'media-modal-fi' );
			jQuery( 'select.attachment-filters [value="uploaded"]' ).attr( 'selected', true ).parent().trigger( 'change' ); // Change the default view to "Uploaded to this post".
		} ).on( 'close', function(){ 
			jQuery( '.media-modal' ).removeClass( 'media-modal-fi' );
		} );

	</script>
<?php }


	public function pigen_attachment_fields_to_edit( $form_fields, $post ) {
		if ( $post->post_mime_type !== 'application/pdf' ) return $form_fields;
		if ( !get_post_meta( $post->ID, '_thumbnail_id', true ) ) return $form_fields;
		$val = get_post_meta( $post->ID, '_pigen_attach_linkto', true );
		if ( empty( $val ) ) {
			$val = get_option( 'image_default_link_type' ); 
			if ( !$val ) $val = 'file';
			update_post_meta( $post->ID, '_pigen_attach_linkto', $val );
		}
		$form_fields[ 'pigen_attach_linkto' ][ 'label' ] = __( 'Link To' );
		$form_fields[ 'pigen_attach_linkto' ][ 'input' ] = 'html';
		$form_fields[ 'pigen_attach_linkto' ][ 'html' ] = 
			'<select name="'. "attachments[{$post->ID}][pigen_attach_linkto]" .'">'.
			'<option ' .selected( $val, 'file', false ). ' value="file">'. __( 'PDF Media File', 'pdf-image-generator' ). '</option>'.
			'<option ' .selected( $val, 'image', false ). ' value="image">'. __( 'Image Media File', 'pdf-image-generator' ). '</option>'.
			'<option ' .selected( $val, 'post', false ). ' value="post">'. __( 'Attachment Page' ). '</option>'.
			'<option ' .selected( $val, 'none', false ). ' value="none">'. __( 'None' ). '</option>'.
			'</select>'. "\n";

		$val = get_post_meta( $post->ID, '_pigen_attach_size', true );
		if ( empty( $val ) ) {
			$opt = get_option( 'pigen_options' );
			if ( isset( $opt[ 'default_size' ] ) && $opt[ 'default_size' ] ) $val = $opt[ 'default_size' ];
			//if ( !$val ) $val = get_option( 'image_default_size' );
			if ( !$val ) $val = 'medium';
			update_post_meta( $post->ID, '_pigen_attach_size', $val );
		}
		$form_fields[ 'pigen_attach_size' ][ 'label' ] = __( 'Media' );
		$form_fields[ 'pigen_attach_size' ][ 'input' ] = 'html';
		$form_fields[ 'pigen_attach_size' ][ 'html' ] = '<select name="'. "attachments[{$post->ID}][pigen_attach_size]" .'">';
		$sizes = apply_filters( 'image_size_names_choose', array( 
			'thumbnail'	=> __( 'Thumbnail' ),
			'medium'	=> __( 'Medium size' ),
			'large'		=> __( 'Large size' ),
			'fullsize'	=> __( 'Full Size' ),
			'title'		=> __( 'Default' ).' ( '.__( 'Title' ).' )',
			'url'		=> __( 'URL' ),
			'caption'	=> __( 'Caption' ),
		) );
		foreach ( $sizes as $slug => $name ) : 
			if ( $slug == 'url' || $slug == 'title' || $slug == 'caption' ){
				$form_fields[ 'pigen_attach_size' ][ 'html' ] .= 
					'<option ' .selected( $val, $slug, false ). ' value="'.esc_attr( $slug ).'">'. esc_html( $name ). '</option>';
			} elseif ( $thumbdata = wp_get_attachment_image_src( $post->ID, $slug ) ){
				$form_fields[ 'pigen_attach_size' ][ 'html' ] .= 
					'<option ' .selected( $val, $slug, false ). ' value="'.esc_attr( $slug ).'">'. esc_html( $name ). ' &ndash; '.$thumbdata[1].' &times; '.$thumbdata[2].'</option>';
			}
		endforeach;
		$form_fields[ 'pigen_attach_size' ][ 'html' ] .= '</select>'. "\n";

		$val = get_post_meta( $post->ID, '_pigen_attach_align', true );
		if ( empty( $val ) ) {
			$val = get_option( 'image_default_align' );
			if ( !$val ) $val = 'none';
			update_post_meta( $post->ID, '_pigen_attach_align', $val );
		}
		$form_fields[ 'pigen_attach_align' ][ 'label' ] = __( 'Alignment' );
		$form_fields[ 'pigen_attach_align' ][ 'input' ] = 'html';
		$form_fields[ 'pigen_attach_align' ][ 'html' ] = 
			'<select name="'. "attachments[{$post->ID}][pigen_attach_align]" .'">'.
			'<option ' .selected( $val, 'left', false ). ' value="left">'. __( 'Left' ). '</option>'.
			'<option ' .selected( $val, 'center', false ). ' value="center">'. __( 'Center' ). '</option>'.
			'<option ' .selected( $val, 'right', false ). ' value="right">'. __( 'Right' ). '</option>'.
			'<option ' .selected( $val, 'none', false ). ' value="none">'. __( 'None' ). '</option>'.
			'</select>'. "\n".
			'<style type="text/css">.attachment-details[data-id="' .$post->ID. '"]:after { content:"' .__( 'Attachment Display Settings' ). '"; font-weight:bold; color:#777; padding:20px 0 0; text-transform:uppercase; clear:both; display:block; } .media-types-required-info, .attachment-display-settings, .attachment-compat, #post-body tr.compat-field-pigen_attach_linkto, #post-body tr.compat-field-pigen_attach_size, #post-body tr.compat-field-pigen_attach_align { display:none!important; } .media-modal-fi .attachment-details[data-id="' .$post->ID. '"]:after, .media-modal-fi tr.compat-field-pigen_attach_linkto, .media-modal-fi tr.compat-field-pigen_attach_size, .media-modal-fi tr.compat-field-pigen_attach_align { display:none!important; }</style>'."\n";
		return $form_fields;
	}


	public function pigen_attachment_fields_to_save( $post, $attachment ){
		if ( isset( $attachment[ 'pigen_attach_linkto' ] ) )
			update_post_meta( $post[ 'ID' ], '_pigen_attach_linkto', $attachment[ 'pigen_attach_linkto' ] );
		if ( isset( $attachment[ 'pigen_attach_size' ] ) ) 
			update_post_meta( $post[ 'ID' ], '_pigen_attach_size', $attachment[ 'pigen_attach_size' ] );
		if ( isset( $attachment[ 'pigen_attach_align' ] ) ) 
			update_post_meta( $post[ 'ID' ], '_pigen_attach_align', $attachment[ 'pigen_attach_align' ] );
		return $post;
	}


	public function pigen_save_post( $post_id ){
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;
		$opt = get_option( 'pigen_options' );
		if ( isset( $opt[ 'featured' ] ) && $opt[ 'featured' ] === 'true' ) {
			$featuredimage = get_post_meta( $post_id, '_thumbnail_id', true );
			if ( $featuredimage && get_post_mime_type ( $featuredimage ) === 'application/pdf' ){
				$new_featuredimage = get_post_meta( $featuredimage, '_thumbnail_id', true );
				if ( $new_featuredimage ){
					update_post_meta( $post_id, '_thumbnail_id', (int)$new_featuredimage );
				}
			}
		}
		return $post_id;
	}


}
new PIGEN();
}

