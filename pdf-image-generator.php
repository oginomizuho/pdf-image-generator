<?php
/*
* Plugin Name: PDF Image Generator
* Plugin URI: http://web.contempo.jp/weblog/tips/p1522
* Description: Generate automatically cover image of PDF by using ImageMagick. Insert PDF link with image into editor. Allow PDF to be set as featured image and to be used as image filetype.
* Author: Mizuho Ogino 
* Author URI: http://web.contempo.jp
* Version: 1.4.4d
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
		add_action( 'admin_menu', array( $this,'pigen_admin_menu' ) );
		add_filter( 'add_attachment', array( $this,'pigen_attachment' ), 100 );
		add_filter( 'media_send_to_editor', array( $this,'pigen_insert' ), 100, 3 );
		add_filter( 'delete_attachment', array( $this,'pigen_delete' ) );
		add_filter( 'wp_mime_type_icon', array( $this,'pigen_change_icon' ), 10, 3 );
		add_filter( 'wp_get_attachment_image_src', array( $this,'pigen_wp_get_attachment_image_src' ), 10, 4 );
		add_filter( 'ajax_query_attachments_args', array( $this,'pigen_ajax_query_attachments_args' ), 100, 1 );
		add_action( 'admin_footer-post-new.php', array( $this,'pigen_override_filter_object' ) );
		add_action( 'admin_footer-post.php', array( $this,'pigen_override_filter_object' ) );
		add_filter( 'attachment_fields_to_edit', array( $this,'pigen_attachment_fields_to_edit' ), 100, 2 );
		add_filter( 'attachment_fields_to_save', array( $this,'pigen_attachment_fields_to_save' ), 100, 2 );
		add_filter( 'pre_get_posts', array( $this,'pigen_attachment_pre_get_posts' ), 10, 1 );
		add_action( 'save_post', array( $this,'pigen_save_post'), 100, 1 );
	}


	public function pigen_activate() { // Check the server whether or not it has imageMagick enabled.
		$version = $this->pigen_imageMagick_ver();
		if ( $version ) {
			$verify_imagick = 'imageMagick';
			exec( 'which gs', $gs_arr, $gs_res ); 
			if ( $gs_res !== 0 ){
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
				if ( function_exists('exec') ) {
					_e( 'Please install "ImageMagick" and "GhostScript" before activate!', 'pdf-image-generator' );
				} else {
					_e( 'Please enable "exec" or "imagick extension" before activate!', 'pdf-image-generator' );
				}
				exit;
			}
		}
		$opt = get_option( 'pigen_options' );
		$update_option = array( 
			'keepthumbs' => isset( $opt[ 'keepthumbs' ] ) ? $opt[ 'keepthumbs' ] : 'true',
			'changeicon' => isset( $opt[ 'changeicon' ] ) ? $opt[ 'changeicon' ] : 'true',
			'hidethumb' => isset( $opt[ 'hidethumb' ] ) ? $opt[ 'hidethumb' ] : 'true',
			'property' => isset( $opt[ 'property' ] ) ? $opt[ 'property' ] : 'true',
			'quality' => isset( $opt[ 'quality' ] ) ? $opt[ 'quality' ] : 80,
			'maxwidth' => isset( $opt[ 'maxwidth' ] ) ? $opt[ 'maxwidth' ] : 1024,
			'maxheight' => isset( $opt[ 'maxheight' ] ) ? $opt[ 'maxheight' ] : 1024,
			'default_size' => isset( $opt[ 'default_size' ] ) ? $opt[ 'default_size' ] : 'medium',
			'image_type' => ( isset( $opt[ 'image_type' ] ) ? $opt[ 'image_type' ] : 'jpg' ),
			'image_bgcolor' => ( isset( $opt[ 'image_bgcolor' ] ) ? $opt[ 'image_bgcolor' ] : 'white' ),
			'featured' => isset( $opt[ 'featured' ] ) ? $opt[ 'featured' ] : 'true',
			'verify_imagick' => $verify_imagick,
			'version' => $version
		);
		update_option( 'pigen_options', $update_option );
	}


	public function pigen_upgrader_process_complete( $upgrader_object, $options ) {
		$current_plugin_path_name = plugin_basename( __FILE__ );
		if ($options['action'] == 'update' && $options['type'] == 'plugin' ){
			foreach($options['plugins'] as $each_plugin){
				if ($each_plugin == $current_plugin_path_name ){
					$opt = get_option( 'pigen_options' );
					$verify_imagick = ( isset($opt['verify_imagick']) ? $opt['verify_imagick'] : 'imageMagick' );
					if ( strpos( $verify_imagick, 'imagick' ) !== false ) {
						$version = $this->pigen_imagick_ver();
						$verify_imagick = 'imagick';
					}
					if ( empty( $version ) ){
						$version = $this->pigen_imageMagick_ver();
						$verify_imagick = 'imageMagick';
					}
					$opt['version'] = $version;
					$opt['verify_imagick'] = $verify_imagick;
					update_option( 'pigen_options', $opt );
					return;
				}
			}
		}
	}


	function pigen_imageMagick_ver() {
		$version = false;
		if ( function_exists('exec') ) {
			exec( 'convert -version', $arr, $res );
			if ( $res === 0 && count($arr) > 2 ) {
				preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $arr[0], $v);
				$version = $v[1];
			}
		}
		return $version;
	}


	function pigen_imagick_ver() { 
		$version = false;
		if ( extension_loaded('imagick') ) {
			$im = new imagick();
			$v = $im->getVersion();
			preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $v['versionString'], $v);
			$version = $v[1];
		}
		return $version;
	}


	public function pigen_admin_menu() {
		$page_hook_suffix = add_options_page( __( 'PDF Image Generator Settings', 'pdf-image-generator' ), __( 'PDF IMG Generator', 'pdf-image-generator' ), 'administrator', __FILE__, array( $this,'pigen_options') );
		add_action('admin_print_scripts-' . $page_hook_suffix, array( $this,'pigen_admin_scripts') );
		if ( isset( $_GET['post'] ) && get_post_mime_type( $_GET['post'] ) === 'application/pdf') 
			add_meta_box( 'pigen_thumbnail', __( 'Thumbnail' ), array( $this, 'pigen_add_thumbnail_to_pdf_edit_page'), 'attachment', 'side', 'default' );
	}


	public function pigen_add_thumbnail_to_pdf_edit_page(){ 
		echo get_the_post_thumbnail( $pdf->ID, 'medium' ).'<style type="text/css"> #pigen_thumbnail { background:none; box-shadow:none; border:none; } #pigen_thumbnail .inside { padding:0; margin:0; } #pigen_thumbnail button, #pigen_thumbnail h2 { display:none; } #pigen_thumbnail img { max-width:100%; height:auto; vertical-align:bottom; box-shadow:0 1px 3px rgba(0,0,0,.2); }</style>';
	}


	public function pigen_admin_scripts() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'colorpicker_script', plugins_url( 'colorPicker.js', __FILE__ ), array( 'wp-color-picker' ), false, true );
	}


	public function pigen_options() { 
		if ( isset( $_POST['pigen_options_nonce'] ) && wp_verify_nonce( $_POST['pigen_options_nonce'], basename(__FILE__) ) ) { // save options
			$update_options = array( 
				'changeicon' => ( isset( $_POST[ 'pigen_changeicon' ] ) ? $_POST[ 'pigen_changeicon' ] : '' ),
				'featured' => ( isset( $_POST[ 'pigen_featured' ] ) ? $_POST[ 'pigen_featured' ] : '' ),
				'hidethumb' => ( isset( $_POST[ 'pigen_hidethumb' ] ) ? $_POST[ 'pigen_hidethumb' ] : '' ),
				'property' => ( isset( $_POST[ 'pigen_property' ] ) ? $_POST[ 'pigen_property' ] : '' ),
				'quality' => ( isset( $_POST[ 'pigen_quality' ] ) ? $_POST[ 'pigen_quality' ] : '' ),
				'maxwidth' => ( isset( $_POST[ 'pigen_maxwidth' ] ) ? $_POST[ 'pigen_maxwidth' ] : '' ),
				'maxheight' => ( isset( $_POST[ 'pigen_maxheight' ] ) ? $_POST[ 'pigen_maxheight' ] : '' ),
				'default_size' => ( !empty( $_POST[ 'pigen_default_size' ] ) ? $_POST[ 'pigen_default_size' ] : 'medium' ),
				'image_type' => ( !empty( $_POST[ 'pigen_image_type' ] ) ? $_POST[ 'pigen_image_type' ] : 'jpg' ),
				'image_bgcolor' => ( !empty( $_POST[ 'pigen_image_bgcolor' ] ) ? $_POST[ 'pigen_image_bgcolor' ] : 'white' ),
				'keepthumbs' => ( isset( $_POST[ 'pigen_keepthumbs' ] ) ? $_POST[ 'pigen_keepthumbs' ] : '' ),
				'verify_imagick' => ( isset( $_POST[ 'pigen_verify_imagick' ] ) ? $_POST[ 'pigen_verify_imagick' ] : 'imageMagick' ),
				'version' => ( isset( $_POST[ 'pigen_im_version' ] ) ? $_POST[ 'pigen_im_version' ] : '' )
			);
			update_option( 'pigen_options', $update_options );
			echo '<div class="updated fade"><p><strong>'. __('Options saved.', 'pdf-image-generator'). '</strong></p></div>';
		}
		$opt = get_option( 'pigen_options' );
		echo '<div class="wrap">'."\n";

		if ( isset($_GET['run']) ) {
			echo
				'<h2>' .__( 'Generate uploaded PDF thumbnails', 'pdf-image-generator' ). '</h2>'."\n".
				'<div id="pdf-list">'."\n".
				'<style type="text/css">#pdf-list { padding:20px 0; } #pdf-list .pdf { display:table; } #pdf-list .pdf .img { display:table-cell; width:120px; padding:5px 0; vertical-align:middle; } #pdf-list .pdf img { border:4px solid #999; max-width:100px; max-height:100px; height:auto; width:auto; } #pdf-list .pdf.generated img { border-color:#0bf; } #pdf-list .pdf.generated strong { color:#0bf; } #pdf-list .pdf.generated img { border-color:#f6d; } #pdf-list .pdf.generated strong { color:#f6d; } #pdf-list .pdf .txt { display:table-cell; font-size:12px; vertical-align:middle; }</style>'."\n";
			$pigen_num = 0;
			$pdfs = get_posts(array('post_type'=>'attachment','post_mime_type'=>'application/pdf','numberposts'=>-1));
			$_GET['run'] == 'regenerate' ? $regenerate = true : $regenerate = false; 
			if ( $pdfs ): foreach( $pdfs as $pdf ):
				$thumbnail_id = get_post_meta( $pdf->ID, '_thumbnail_id', true );
				if ( !$thumbnail_id || $regenerate ){
					$pigen_num ++;
					$this->pigen_attachment( $pdf->ID );
					echo 
						'<div class="pdf generated' .( $regenerate && $thumbnail_id ? ' regenerated' : '' ). '">'.
						'<p class="img">'.get_the_post_thumbnail( $pdf->ID, 'medium' ).'</p>'.
						'<p class="txt">ID: <a href="'.get_edit_post_link($pdf->ID).'">'.$pdf->ID.'</a> / <strong>' .( $regenerate && $thumbnail_id ? 'An image was REGENERATED' : 'A new image was GENERATED' ). '</strong></p>'.
						'</div>';
				} else {
					echo 
						'<div class="pdf">'.
						'<p class="img">'.get_the_post_thumbnail( $pdf->ID, 'medium' ).'</p>'.
						'<p class="txt">ID: '.$pdf->ID.' / An image already exists</p>'.
						'</div>';
				}
			endforeach; endif;
			echo '</div><!-- #pdf-list -->'."\n";
			if ( $pigen_num == 0 ) $pigen_num = 'No image'; elseif ( $pigen_num == 1 ) $pigen_num = '1 image'; else $pigen_num = $pigen_num.' images'; echo '<h3>'.$pigen_num.' generated.</h3>'."\n";
			echo '<p><a href="'.remove_query_arg( 'run', $_SERVER['REQUEST_URI'] ).'" class="button button-primary">' .__( 'Back to PDF Image Generator Settings', 'pdf-image-generator' ).'</a></p><br/>'."\n";

		} else {
			echo
				'<style type="text/css"> '.
				'#pigen-fields { margin:0; font-size:1em; } '.
				'#pigen-fields > div { padding:0; margin:10px 0; } '.
				'#pigen-fields > div + div { margin-top:25px; } '.
				'#pigen-fields p { clear:left; margin:5px 0 0; } '.
				'#pigen-fields p:after{ display:block; content:""; clear:left; } '.
				'#pigen-fields .float { float:left; clear:none; margin-right:25px; } '.
				'#pigen-fields p:not(.subfield) label { font-size:1.2em; padding:5px 0; } '.
				'#pigen-fields p.subfield { margin:5px 0 0 28px; font-size:12px; line-height:32px; } '.
				'#pigen-fields p.subfield.note { line-height:1.5em; } '.
				'#pigen-fields input:not([type="radio"]):not([type="checkbox"]), #pigen-fields select { display:inline-block; font-size:16px; verticla-align:middle; padding:5px; line-height:20px; height:32px; margin:0 5px 0 0; } '.
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
				"\t\t\t".'<p><input name="pigen_changeicon" type="checkbox" id="pigen_changeicon" value="true" '.( isset($opt['changeicon']) && $opt['changeicon'] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_changeicon">'.__( 'Display Generated Image instead of default wp mime-type icon', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t".'</div>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_featured" type="checkbox" id="pigen_featured" value="true" '.( isset($opt['featured']) && $opt['featured'] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_featured">'.__( 'Allow to set PDF thumbnail as Featured Image', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t".'</div>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_hidethumb" type="checkbox" id="pigen_hidethumb" value="true" '.( isset($opt['hidethumb']) && $opt['hidethumb'] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_hidethumb">'.__( 'Hide Generated Images themselves in the Media Library', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t\t".'<p class="subfield">'.__( 'When this checkbox is unchecked and a PDF is deleted, a Generated Image will NOT be deleted together.', 'pdf-image-generator' ). '</p>'."\n".
				"\t\t".'</div>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_property" type="checkbox" id="pigen_property" value="true" '.( isset($opt['property']) && $opt['property'] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_property">'.__( 'Customize Generated Image properties', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t\t".'<p class="subfield note">'.__( 'In case of frequent HTTP error when uploading PDF, there maybe not enough memory.', 'pdf-image-generator' ).'<br/>'.__( 'By unchecking this checkbox, the following will be disabled, and it helps to reduce memory spending.', 'pdf-image-generator' ). '</p>'."\n".
				"\t\t\t".'<p class="subfield"><span class="float"><label for="pigen_maxwidth">'.__( 'Max Width' ).': <input name="pigen_maxwidth" type="number" id="pigen_maxwidth" value="'.( $opt['maxwidth'] ).'" onKeyup="this.value=this.value.replace(/[^0-9a-z]+/i,\'\')" /> px</label></span><span class="float"><label for="pigen_maxheight">'.__( 'Max Height' ).': <input name="pigen_maxheight" type="number" id="pigen_maxheight" value="'.$opt['maxheight'].'" onKeyup="this.value=this.value.replace(/[^0-9a-z]+/i,\'\')" /> px</label></span><span class="float"><label for="pigen_quality">'.__( 'Compression Quality', 'pdf-image-generator' ).': <input name="pigen_quality" type="number" min="1" max="100" id="pigen_quality" value="'.$opt['quality'].'" onKeyup="this.value=this.value.replace(/[^0-9a-z]+/i,\'\')" /> (1 - 100)</label></span></p>'."\n".
				"\t\t\t".'<p class="subfield note">'.__( 'The parameter will be calculated if 0 or blank is entered.', 'pdf-image-generator' ).'</p>'."\n".	
				"\t\t\t".'<p class="subfield"><span class="float">'.__( 'Select Generated Image File Type', 'pdf-image-generator' ).': </span><span class="float"><label for="pigen_image_type_jpg"><input type="radio" id="pigen_image_type_jpg" name="pigen_image_type" value="jpg" '.( isset($opt['image_type']) && $opt['image_type'] === 'jpg' ? 'checked="checked"' : '' ).' />'.__( 'jpg' ).'</label></span><span class="float"><label for="pigen_image_type_png"><input type="radio" id="pigen_image_type_png" name="pigen_image_type" value="png" '.( isset($opt['image_type']) && $opt['image_type'] === 'png' ? 'checked="checked"' : '' ).' />'.__( 'png' ).'</label></span></p>'."\n".
				"\t\t\t".'<p class="subfield"><span class="float"><label for="pigen_image_bgcolor">'.__( 'Background Color' ).': <input class="pigen_color_picker" name="pigen_image_bgcolor" type="text" id="pigen_image_bgcolor" value="'.( isset($opt['image_bgcolor']) ? $opt['image_bgcolor'] : '' ).'" /></label></span></p>'."\n".
				"\t\t".'</div>'."\n";

			$select = '';
			$sizes = apply_filters( 'image_size_names_choose', array(
				'thumbnail'	=> __('Thumbnail'),
				'medium'	=> __('Medium size'),
				'large'		=> __('Large size'),
				'full'		=> __('Full Size'),
				'title'		=> __('Default').' ('.__('Title').')',
				'url'		=> __('URL'),
				'caption'	=> __('Caption'),
			));
			foreach ( $sizes as $slug => $name ) $select .= '<option '.( isset( $opt['default_size'] ) && $opt['default_size'] === $slug ? 'selected' : '' ).' value="'.esc_attr( $slug ).'">'. esc_html( $name ). '</option>'; 
			echo 
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><label for="pigen_default_size">'.__( 'Default insert size (or type)', 'pdf-image-generator' ).': </label><select name="pigen_default_size" id="pigen_default_size">'.$select.'</select></p>'."\n".
				"\t\t\t".'<p class="subfield note">'.__( '"Default (Title)" allows to insert default html.', 'pdf-image-generator' ).'<br/>'.__( 'If you are using a document viewer plugin like GDE, select it.', 'pdf-image-generator' ).'</p>'."\n".
				"\t\t".'</div>'."\n";

			$verify_imagick = ( isset($opt['verify_imagick']) ? $opt['verify_imagick'] : 'imageMagick' );
			$imageMagick_ver = $this->pigen_imageMagick_ver();
			$imagick_ver = $this->pigen_imagick_ver();
			echo
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><input name="pigen_keepthumbs" type="checkbox" id="pigen_keepthumbs" value="true" '.( isset($opt['keepthumbs']) && $opt['keepthumbs'] === 'true' ? 'checked="checked"' : '' ).' /><label for="pigen_keepthumbs">'.__( 'Keep Generated Images after the plugin is uninstalled', 'pdf-image-generator' ).'</label></p>'."\n".
				"\t\t\t".'<p class="subfield">' .__( 'If the plugin is deactivated, Generated Image files will be handled as ordinary image files.', 'pdf-image-generator' ). '</p>'."\n".
				"\t\t".'</div>'."\n".
				"\t\t".'<div>'."\n".
				"\t\t\t".'<p><span class="float"><input type="radio" id="pigen_verify_imageMagick" name="pigen_verify_imagick" value="imageMagick" '.( $verify_imagick === 'imageMagick' ? 'checked="checked"' : '' ).( $imageMagick_ver ? '' : ' disabled' ).' /><label for="pigen_verify_imageMagick">'.__( 'Use imageMagick with exec function', 'pdf-image-generator' ).'</label></span><span class="float"><input type="radio" id="pigen_verify_imagick" name="pigen_verify_imagick" value="imagick" '.( $verify_imagick === 'imagick' ? 'checked="checked"' : '' ).( $imagick_ver ? '' : ' disabled' ).' /><label for="pigen_verify_imagick">'.__( 'Use imagick PHP Extension', 'pdf-image-generator' ).'</label></span></p>'."\n".
				"\t\t\t".'<p class="subfield note">'.sprintf(__('Your server using imageMagick %s', 'pdf-image-generator'), $imagick_ver ? $imagick_ver : $imageMagick_ver ).'</p>'."\n".
				"\t\t\t".'<input type="hidden" name="pigen_im_version" value="'.( $imagick_ver ? $imagick_ver : $imageMagick_ver ).'" />'."\n".
				"\t\t".'</div>'."\n".
				"\t".'</fieldset>'."\n".
				"\t".'<p class="submit"><input type="submit" name="Submit" class="button-primary" value="'.__( 'Save changes', 'pdf-image-generator' ).'" /></p>'."\n".
				"\t".'<input type="hidden" name="pigen_options_nonce" value="'.wp_create_nonce(basename(__FILE__)).'" />'."\n".
				'</form>'."\n";
			echo
				'<h3 style="margin-top:40px;">' .__( 'Generate uploaded PDF thumbnails', 'pdf-image-generator' ). '</h3>'."\n".
				'<p>' .__( 'It allows you to generate images of any already-uploaded PDFs in the Media Library. <br/>Please save changes before running the script.', 'pdf-image-generator' ). '</p>'."\n".
				'<p><a href="'.add_query_arg( 'run', 'generate', $_SERVER['REQUEST_URI'] ).'" class="button button-primary">' .__( 'Generate images of PDFs that have no thumbnail', 'pdf-image-generator' ). '</a></p>'."\n".
				'<p><a href="'.add_query_arg( 'run', 'regenerate', $_SERVER['REQUEST_URI'] ).'" class="button button-primary">' .__( 'Regenerate and replace images of all PDFs', 'pdf-image-generator' ). '</a></p>'."\n";
		}
		echo 
			'<h3 style="margin-top:40px;">' .__( 'Donate', 'pdf-image-generator' ).'</h3>'."\n".
			'<p>' .__( 'If you find this plugin useful and you want to support its future development, please consider making a donation.', 'pdf-image-generator' ). '</p>'."\n".
			'<p><a href="http://web.contempo.jp/donate?pigen" class="button button-primary" target="_blank">' .__( 'Donate via PayPal', 'pdf-image-generator' ).'</a></p>'."\n".
			'<p style="padding-top:30px; font-size:.9em;">' .__( 'If you are having problems with the plugin, see the plugin page on <a href="https://wordpress.org/plugins/pdf-image-generator/" target="_blank">the WordPress.org plugin directory</a>.', 'pdf-image-generator' ).'</p>'."\n".
			'</div><!-- .wrap -->'."\n".
			'<script type="text/javascript">jQuery(function($) { '.
				'$(".pigen_color_picker").wpColorPicker(); '.
			'});</script>'."\n";
	}

	public function pigen_generate( $file ){ // Generate thumbnail from PDF
		set_time_limit(3000);
		$opt = get_option( 'pigen_options' );
		$property = ( isset($opt['property']) ? $opt['property'] : '' );
		$image_type = ( $property && isset($opt['image_type']) && $opt[ 'image_type' ] == 'png' ? 'png' : 'jpg' );
		$max_width = ( !empty($opt[ 'maxwidth' ]) ? (int) $opt[ 'maxwidth' ] : 1024 );
		$max_height = ( !empty($opt[ 'maxheight' ]) ? (int) $opt[ 'maxheight' ] : 1024 );
		$quality = ( !empty($opt[ 'quality' ]) ? (int) $opt[ 'quality' ] : 80 );
		$version = ( !empty($opt[ 'version' ]) ? $opt[ 'version' ] : '' );
		if ( $quality > 100 ) $quality = 100;
		$image_bgcolor = ( $property && isset($opt[ 'image_bgcolor' ]) ? $opt[ 'image_bgcolor' ] : 'white' );
		$verify_imagick = ( isset($opt[ 'verify_imagick' ]) ? $opt[ 'verify_imagick' ] : '' );
		$file_basename = str_replace( '.pdf', '-pdf', basename($file) ).'.'.$image_type;
		$file_basename = apply_filters( 'pigen_filter_convert_file_basename', $file_basename );
		$file_url = str_replace( basename($file), $file_basename, $file );
		if ( $verify_imagick == 'imagick' ) { // imagick API
			try { 
				$imagick = new imagick();
				if ( $property ) {
					$imagick->setResolution( 300, 300 );
					$imagick->readimage( $file.'[0]' );
					$imagick->setCompressionQuality( $quality );
					$imagick->scaleImage( $max_width, $max_height, true );
				} else {
					$imagick->readimage( $file.'[0]' );
					$imagick->setResolution( 72, 72 );
				}


				if ( version_compare($version,'6.3.8') < 0 ){
					$imagick->setImageBackgroundColor( $image_bgcolor );
					$imagick = $imagick->flattenImages();
					$imagick->setImageFormat( $image_type ); 
				} else {
					$imagick->setImageFormat( $image_type ); 
					$imagick = apply_filters( 'pigen_filter_convert_imagick', $imagick );
					$imagick->setImageBackgroundColor( $image_bgcolor );
					$imagick->setImageAlphaChannel(11);
					//$imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
					//Imagick::ALPHACHANNEL_REMOVE has been added in 3.2.0b2 (before the RC)
					$imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
				} 
				$colorspace = $imagick->getImageColorspace();
				if ($colorspace == Imagick::COLORSPACE_CMYK) {
					// if ( version_compare($version,'6.3.6') >= 0 ){
					// 	$profiles = $image->getImageProfiles('*', false); // get profiles
					// 	$has_icc_profile = (array_search('icc', $profiles) !== false);
					// 	if ($has_icc_profile === false){
					// 		$icc_cmyk = file_get_contents('/path/to/icc');
					// 		$image->profileImage('icc', $icc_cmyk);
					// 	}
					// } else {
						$plugins_url = plugins_url( '', __FILE__ );
						$imagick->stripImage();
						$icc_cmyk = file_get_contents( $plugins_url.'/iccprofiles/GenericCMYK.icm' );
						$imagick->profileImage('icm', $icc_cmyk);
						unset($icc_cmyk);
					// } 
					$icc_rgb = file_get_contents( $plugins_url.'/iccprofiles/sRGB_ICC_v4_appearance_beta_displayclass.icc' );
					$imagick->profileImage('icc', $icc_rgb);
					unset($icc_rgb); 

					$php_vs_arr = preg_split("/\./", phpversion());
					$php_vs = $php_vs_arr[0] . '.' . $php_vs_arr[1];
					if($php_vs < 5.3) { //ADJUST GAMMA BY 20% for 5.2.x
						$imagick->levelImage(0, 2.0, $range['quantumRangeString']);
					} else { //php 5.3 hack FOR INVERTED COLORS
						$imagick->negateImage(false, Imagick::CHANNEL_ALL);
					}
					$imagick->setImageColorSpace(Imagick::COLORSPACE_RGB);
				}
				$imagick->writeImage( $file_url ); 
				$imagick->clear();
				$imagick->destroy();
			} catch ( ImagickException $e ){
				$file_url = false;
			} catch ( Exception $e ){
				$file_url = false;
			}
		} else { // imageMagick
			if ( version_compare($version,'6.7.5') < 0 ) $alphaoff = "-flatten"; else $alphaoff = "-alpha remove"; 
			if ( version_compare($version,'6.7.7') < 0 ) $density = "-density 72"; else $density = "-density 300 -set units PixelsPerInch"; 

			$get_color = exec("identify -format '%[colorspace]' {$file}[0]", $output, $return);
			$colorspace = "";
			if (!$return ){ // return non-zero upon an error
				// if ( version_compare($version,'6.8.7.2' ) >= 0 {
				// 	$get_icc = exec("identify -format %[profile:icc] {$file}[0]", $output, $return);
				// }
				$plugins_url = plugins_url( '', __FILE__ );

				if( strpos($get_color,'cmyk') !== false || strpos($get_color,'CMYK') !== false ){
					$colorspace = "-strip -profile ".$plugins_url."/iccprofiles/GenericCMYK.icm -profile ".$plugins_url."/iccprofiles/sRGB_ICC_v4_appearance_beta_displayclass.icc -colorspace sRGB";
					$file_url = str_replace( '-pdf', '-pdf-v'.$version, $file_url );
				}
			}
			if ( $property ) {
				if ( $image_bgcolor !== 'white' && preg_match( '/^#[a-f0-9]{6}$/i', $image_bgcolor ) ) $image_bgcolor = "\"$image_bgcolor\"";
				$imageMagick = "convert {$density} -quality {$quality} -background {$image_bgcolor} {$alphaoff} {$file}[0] {$colorspace} -resize {$max_width}x{$max_height} {$file_url}";
			} else {
				$imageMagick = "convert -density 72 -background white {$alphaoff} {$file}[0] {$colorspace} {$file_url}";
			}

			$imageMagick = apply_filters( 'pigen_filter_convert_imageMagick', $imageMagick, $file.'[0]', $file_url, $max_width, $max_height );
			exec( $imageMagick , $output, $return ); // convert pdf to image
			if ( $return ) $file_url = false; // return non-zero upon an error
		}
		return $file_url;
	}


	public function pigen_attachment( $attachment_id ){ // Generate thumbnail from PDF
		if ( get_post_mime_type( $attachment_id ) === 'application/pdf' ){
			$file = get_attached_file( $attachment_id );
			$file_url = $this->pigen_generate( $file );
			if ( file_exists( $file_url ) ){
				$thumbnail_id = get_post_meta( $attachment_id, '_thumbnail_id', true );
				if ( !$thumbnail_id ){
					$file_title = esc_attr( get_the_title( $attachment_id ) );
					$attachment = get_post( $attachment_id );
					$thumbnail = array(
						'post_type' => 'attachment',
						'post_mime_type' => 'image/jpeg',
						'post_title' => $file_title,
						'post_excerpt' => $attachment->post_excerpt,
						'post_content' => $attachment->post_content,
						'post_parent' => $attachment_id
					);
					$thumbnail_id = wp_insert_attachment( $thumbnail, $file_url );
					update_post_meta( $thumbnail_id, '_wp_attachment_image_alt', 'thumbnail of '.$file_title );
				}
				update_post_meta( $attachment_id, '_thumbnail_id', $thumbnail_id );
				$metadata = wp_generate_attachment_metadata( $thumbnail_id, $file_url );
				if ( !empty( $metadata ) && ! is_wp_error( $metadata ) ) {
					wp_update_attachment_metadata( $thumbnail_id, $metadata );
				}
			}
		}
		return $attachment_id;
	}


	public function pigen_insert( $html, $attach_id, $attachment ) { // Insert thumbnail instead of PDF
		if ( $attach_id && get_post_mime_type ( $attach_id ) === 'application/pdf' ){
			$attach_image_id = get_post_meta( $attach_id, '_thumbnail_id', true );
			if ( !$attach_image_id ) return $html;
			$linkto = get_post_meta( $attach_id, '_pigen_attach_linkto', true );
			if ( $linkto === 'file' ) $attach_url = wp_get_attachment_url( $attach_id );
			elseif ( $linkto === 'post' ) $attach_url = get_attachment_link( $attach_id );
			else $attach_url = '';
			$attach_title = isset( $attachment['post_title'] ) ? $attachment['post_title'] : '';
			$attach_caption = isset( $attachment['post_excerpt'] ) ? $attachment['post_excerpt'] : '';
			$size = get_post_meta( $attach_id, '_pigen_attach_size', true );
			$attach_output = '';
			$attach_image = '';
			if ( $size === 'url' ){
				$attach_output = $attach_url;
			} elseif ( $size === 'title' ){
				if ( strpos($html,'href=') !== false && strpos($html,'class=') === false ) { // wp default link
					$attach_output = $attach_title;
				} else {
					$html = $html; // leave default html for other doc viewer plugins
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
					wp_delete_post( $thumbnail_id );
				} else {
					$post_parent = get_post( $attachment_id )->post_parent;
					if ( $post_parent ){
						$thumb = array();
						$thumb['ID'] = $thumbnail_id;
						$thumb['post_parent'] = $post_parent;
						wp_update_post( $thumb );
					}
				}
			}
		}
	}


	public function pigen_change_icon ( $icon, $mime, $attachment_id ){ // Display thumbnail instead of document.png
		$opt = get_option( 'pigen_options' );
		if ( isset( $opt[ 'changeicon' ] ) && $opt[ 'changeicon' ] === '' ) return $icon;
		if ( strpos( $_SERVER[ 'REQUEST_URI' ], '/wp-admin/upload.php' ) === false && $mime === 'application/pdf' ){
			$thumbnail = wp_get_attachment_image_src ( $attachment_id, 'medium' );
			if ( $thumbnail ) $icon = $thumbnail[0];
		}
		return $icon;
	}


	public function pigen_wp_get_attachment_image_src( $image, $attachment_id, $size, $icon ) {
		if ( get_post_mime_type ( $attachment_id ) === 'application/pdf' ){
			$thumbnail_id = get_post_meta( $attachment_id, '_thumbnail_id', true );
			if ( $thumbnail_id ){
				$get_image = wp_get_attachment_image_src ( $thumbnail_id, $size );
				$image = array( $get_image[0], $get_image[1], $get_image[2] );
			}
		}
		return $image;
	}


	public function pigen_ajax_query_attachments_args( $query ) { // Hide thumbnail files in the library.
		$opt = get_option( 'pigen_options' );
		if ( isset( $query[ 'post_mime_type' ] ) && $query['post_mime_type'] === 'image_n_pdf' ){
			$post_parent = ( isset( $query['post_parent'] ) && $query['post_parent'] ? '&post_parent='.$query['post_parent'] : '' );
			if ( isset( $opt[ 'featured' ] ) && $opt[ 'featured' ] === 'true' ){
				$query['post_mime_type'] = array('image','application/pdf');
			}
		} 
		if ( isset( $opt[ 'hidethumb' ] ) && $opt[ 'hidethumb' ] === '' ){
			if ( isset( $query['post_parent'] ) && $query['post_parent'] ){
				$post__in = array();
				$get_posts = get_posts( 'posts_per_page=-1&post_type=attachment&post_parent='.$query['post_parent'] );
				if ( $get_posts ): foreach ( $get_posts as $get ):
					$post__in[] = $get->ID;
					$thumbnail_id = get_post_meta( $get->ID, '_thumbnail_id', true );
					if ( get_post_mime_type( $get->ID ) == 'application/pdf' && $thumbnail_id ) $post__in[] = $thumbnail_id;
				endforeach; endif;
				if ( $post__in ){
					$query['post_parent'] = false;
					$query['post__in'] = $post__in;
				}
			}
			return $query;
		}
		$post__not_in = array();
		$get_posts = get_posts( 'posts_per_page=-1&post_type=attachment&post_mime_type=application/pdf&meta_key=_thumbnail_id' );
		if ( $get_posts ): foreach ( $get_posts as $get ):
			$post__not_in[] = get_post_meta( $get->ID, '_thumbnail_id', true );
		endforeach; endif;
		$query['post__not_in'] = $post__not_in;

		return $query;
	}


	public function pigen_attachment_pre_get_posts( $query ) {
		global $pagenow;
		if ( !is_admin() || $pagenow !== 'upload.php' ) return;
		remove_action( 'pre_get_posts', array( $this, 'pigen_attachment_pre_get_posts') );
		$opt = get_option( 'pigen_options' );
		if ( isset( $opt[ 'hidethumb' ] ) && $opt[ 'hidethumb' ] !== '' ){
			$post__not_in = array();
			$get_posts = get_posts( 'posts_per_page=-1&post_type=attachment&post_mime_type=application/pdf&meta_key=_thumbnail_id' );
			if ( $get_posts ): foreach ( $get_posts as $get ):
				$post__not_in[] = get_post_meta( $get->ID, '_thumbnail_id', true );
			endforeach; endif;
			if ( $post__not_in ) $query->set('post__not_in', $post__not_in );
		}
		add_action( 'pre_get_posts', array( $this, 'pigen_attachment_pre_get_posts') );
		return $query;
	}


	public function pigen_override_filter_object() { // Override relevant media manager javascript functions 
		$opt = get_option( 'pigen_options' );
		if ( isset( $opt[ 'featured' ] ) && $opt[ 'featured' ] === '' ) return;
?>
	<script type="text/javascript">
		l10n = wp.media.view.l10n = typeof _wpMediaViewsL10n === 'undefined' ? {} : _wpMediaViewsL10n;
		wp.media.view.AttachmentFilters.Uploaded.prototype.createFilters = function() {
			var type = this.model.get('type'),
				types = wp.media.view.settings.mimeTypes,
				text;
			if ( types && type ) text = types[ type ];
			filters = {
				all: { text: text || l10n.allMediaItems, props: { uploadedTo: null, orderby: 'date', order: 'DESC' }, priority: 10 },
				uploaded: { text: l10n.uploadedToThisPost, props: { uploadedTo: wp.media.view.settings.post.id, orderby: 'menuOrder', order: 'ASC' }, priority: 20 }
			};
			if ( this.options.controller._state.indexOf( 'featured-image' ) !== -1 ) {
				filters.all = { text: <?php echo '\''.__('Image').' & '.__( 'PDF' ).'\''; ?>, props: { type: 'image_n_pdf', uploadedTo: null, orderby: 'date', order: 'DESC' }, priority: 20 };
				filters.image = { text: <?php echo '\''.__('Image').'\''; ?>, props: { type: 'image', uploadedTo: null, orderby: 'date', order: 'DESC' }, priority: 20 };
				filters.uploaded = { text: l10n.uploadedToThisPost, props: { type: 'image_n_pdf', uploadedTo: wp.media.view.settings.post.id, orderby: 'menuOrder', order: 'ASC' }, priority: 10 };
				filters.unattached = { text: l10n.unattached, props: { 	status: null, uploadedTo: 0, type: null, orderby: 'menuOrder', order: 'ASC' }, priority: 50 };
			}
			this.filters = filters;
		}; // End create filters

<?php 
	if ( isset( $_GET['post'] ) ):
		$post_id = $_GET['post'];
		$featuredimage = get_post_meta( $post_id, '_thumbnail_id', true );
		if ( $featuredimage ) {
			$pdf_id = get_post( $featuredimage )->post_parent;
			if ( $pdf_id && get_post_mime_type ( $pdf_id ) === 'application/pdf' )
				echo "\t\t".'wp.media.view.settings.post.featuredImageId = '.$pdf_id.';'."\n";
		}
	endif;
?>

		wp.media.featuredImage.frame().on( 'ready', function(){ 
			jQuery( '.media-modal' ).addClass( 'media-modal-fi' );
			jQuery( 'select.attachment-filters [value="uploaded"]' ).attr( 'selected', true ).parent().trigger('change'); // Change the default view to "Uploaded to this post".
		}).on( 'close', function(){ 
			jQuery( '.media-modal' ).removeClass( 'media-modal-fi' );
		});

	</script>
<?php }


	public function pigen_attachment_fields_to_edit( $form_fields, $post ) {
		if ( get_post_mime_type( $post->ID ) !== 'application/pdf' ) return $form_fields;
		if ( !get_post_meta( $post->ID, '_thumbnail_id', true ) ) return $form_fields;
		$val = get_post_meta( $post->ID, '_pigen_attach_linkto', true );
		if ( empty( $val ) ) {
			$val = get_option( 'image_default_link_type' ); 
			if (!$val) $val = 'file';
			update_post_meta( $post->ID, '_pigen_attach_linkto', $val );
		}
		$form_fields['pigen_attach_linkto']['label'] = __('Link To');
		$form_fields['pigen_attach_linkto']['input'] = 'html';
		$form_fields['pigen_attach_linkto']['html'] = 
			'<select name="'. "attachments[{$post->ID}][pigen_attach_linkto]" .'">'.
			'<option ' .selected( $val, 'file', false ). ' value="file">'. __('PDF Media File', 'pdf-image-generator'). '</option>'.
			'<option ' .selected( $val, 'post', false ). ' value="post">'. __('Attachment Page'). '</option>'.
			'<option ' .selected( $val, 'none', false ). ' value="none">'. __('None'). '</option>'.
			'</select>'. "\n";

		$val = get_post_meta( $post->ID, '_pigen_attach_size', true );
		if ( empty( $val ) ) {
			$opt = get_option( 'pigen_options' );
			if ( isset( $opt[ 'default_size' ] ) && $opt[ 'default_size' ] ) $val = $opt[ 'default_size' ];
			//if (!$val) $val = get_option( 'image_default_size' );
			if (!$val) $val = 'medium';
			update_post_meta( $post->ID, '_pigen_attach_size', $val );
		}
		$form_fields['pigen_attach_size']['label'] = __( 'Media' );
		$form_fields['pigen_attach_size']['input'] = 'html';
		$form_fields['pigen_attach_size']['html'] = '<select name="'. "attachments[{$post->ID}][pigen_attach_size]" .'">';
		$sizes = apply_filters( 'image_size_names_choose', array(
			'thumbnail'	=> __('Thumbnail'),
			'medium'	=> __('Medium size'),
			'large'		=> __('Large size'),
			'full'		=> __('Full Size'),
			'title'		=> __('Default').' ('.__('Title').')',
			'url'		=> __('URL'),
			'caption'	=> __('Caption'),
		));
		foreach ( $sizes as $slug => $name ) : 
			if ( $slug == 'url' || $slug == 'title' || $slug == 'caption' ){
				$form_fields['pigen_attach_size']['html'] .= 
					'<option ' .selected( $val, $slug, false ). ' value="'.esc_attr( $slug ).'">'. esc_html( $name ). '</option>';
			} elseif ( $thumbdata = wp_get_attachment_image_src( $post->ID, $slug ) ){
				$form_fields['pigen_attach_size']['html'] .= 
					'<option ' .selected( $val, $slug, false ). ' value="'.esc_attr( $slug ).'">'. esc_html( $name ). ' &ndash; '.$thumbdata[1].' &times; '.$thumbdata[2].'</option>';
			}
		endforeach;
		$form_fields['pigen_attach_size']['html'] .= '</select>'. "\n";

		$val = get_post_meta( $post->ID, '_pigen_attach_align', true );
		if ( empty( $val ) ) {
			$val = get_option( 'image_default_align' );
			if (!$val) $val = 'none';
			update_post_meta( $post->ID, '_pigen_attach_align', $val );
		}
		$form_fields['pigen_attach_align']['label'] = __('Alignment');
		$form_fields['pigen_attach_align']['input'] = 'html';
		$form_fields['pigen_attach_align']['html'] = 
			'<select name="'. "attachments[{$post->ID}][pigen_attach_align]" .'">'.
			'<option ' .selected( $val, 'left', false ). ' value="left">'. __('Left'). '</option>'.
			'<option ' .selected( $val, 'center', false ). ' value="center">'. __('Center'). '</option>'.
			'<option ' .selected( $val, 'right', false ). ' value="right">'. __('Right'). '</option>'.
			'<option ' .selected( $val, 'none', false ). ' value="none">'. __('None'). '</option>'.
			'</select>'. "\n".
			'<style type="text/css">.attachment-details[data-id="' .$post->ID. '"]:after { content:"' .__('Attachment Display Settings'). '"; font-weight:bold; color:#777; padding:20px 0 0; text-transform:uppercase; clear:both; display:block; } .media-types-required-info, .attachment-display-settings, .attachment-compat, #post-body .compat-attachment-fields { display:none!important; } .media-modal-fi .attachment-details[data-id="' .$post->ID. '"]:after, .media-modal-fi tr.compat-field-pigen_attach_linkto, .media-modal-fi tr.compat-field-pigen_attach_size, .media-modal-fi tr.compat-field-pigen_attach_align { display:none!important; }</style>'."\n";
		return $form_fields;
	}


	public function pigen_attachment_fields_to_save( $post, $attachment ){
		if ( isset( $attachment['pigen_attach_linkto'] ) )
			update_post_meta( $post['ID'], '_pigen_attach_linkto', $attachment['pigen_attach_linkto'] );
		if ( isset( $attachment['pigen_attach_size'] ) ) 
			update_post_meta( $post['ID'], '_pigen_attach_size', $attachment['pigen_attach_size'] );
		if ( isset( $attachment['pigen_attach_align'] ) ) 
			update_post_meta( $post['ID'], '_pigen_attach_align', $attachment['pigen_attach_align'] );
		return $post;
	}


	public function pigen_save_post( $post_id ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;
		$opt = get_option( 'pigen_options' );
		if ( isset( $opt[ 'featured' ] ) && $opt[ 'featured' ] === 'true' ) {
			$featuredimage = get_post_meta( $post_id, '_thumbnail_id', true );
			if ( $featuredimage && get_post_mime_type ( $featuredimage ) === 'application/pdf' ){
				if ( $new_featuredimage = get_post_meta( $featuredimage, '_thumbnail_id', true ) ){
					update_post_meta( $post_id, '_thumbnail_id', $new_featuredimage );
				}
			}
		}
		return $post_id;
	}


}
new PIGEN();
}
