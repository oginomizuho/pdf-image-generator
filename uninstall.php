<?php

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

$opt = get_option( 'pigen_options' );
if ( isset( $opt[ 'keepthumbs' ] ) && $opt[ 'keepthumbs' ] == '' ) {
	$pdfs = get_posts(array('post_type'=>'attachment','post_mime_type'=>'application/pdf','numberposts'=>-1));
	if($pdfs): foreach($pdfs as $pdf):
		if( $thumb = get_post_meta( $pdf->ID, '_thumbnail_id', true ) ) {
			wp_delete_attachment( $thumb );
		}
	endforeach; endif;
}

delete_option( 'pigen_options' );

return;

