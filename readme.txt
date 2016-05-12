=== PDF Image Generator ===

Contributors: fishpie
Donate link: http://web.contempo.jp/donate?pigen
Tags: pdf, thumbnail, jpg, image, upload, convert, attachment
Plugin URI: http://web.contempo.jp/weblog/tips/p1522
Requires at least: 4.0
Tested up to: 4.5.2
Stable tag: 1.4.4
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Generate automatically cover image of PDF by using ImageMagick. Allow user to insert PDF link with thumbnail into editor and set as Featured Image.



== Description ==

By uploading a PDF attachment, this plugin convert the cover page to jpeg and attach it as a post thumbnail file. It also allows displaying a thumbnail icon and inserting PDF link with a cover image into the editor. 

= Available only for WordPress 4.0+, also only on the server which  ImageMagick and GhostScript are installed. =

This plugin hooks to the media editor and generates the first page image of PDF by using ImageMagick with GhostScript. It requires no setup, just activate the plugin via the admin install page. Allow to set a PDF as an image. 

= How it works =
This Plugin replaces and extends the following features.
<ul>
<li>Generate cover image of PDF by using ImageMagick. Generated Image has different size variations and is attached to PDF.</li>
<li>Register Generated Image as Featured Image (post-thumbnail) of PDF.</li>
<li>Display Generated Image as icon instead of Mime-type Icon in Admin Page.</li>
<li>Hide Generated Image file itself in Media Library. (v1.2 or later)</li>
<li>Replace link text with JPG when inserting PDF to Text Editor.</li>
<li>Delete Generated Image when deleting PDF from Media Library.</li>
<li>Allow to manage and output Generated Image and PDF on manually in template file. (see Other Notes.)</li>
<li>Allow to set PDF as Featured Image and to use functions in the same way as image file. (v1.2 or later, see Other Notes.)</li>
<li>Allow to control maximum size of Generated Image and other default settings in Plugin Page. (v1.3.4 or later)</li>
</ul>

= Generated Items =
A generated image file is registered as post children of a PDF file and it has different size variations. Files build a tree like below. 
<ul><li>my-file.pdf (Your PDF)
<ul><li>my-file-pdf.jpg (Generated Cover Image of Your PDF)
<ul><li>my-file-pdf-1024x768.jpg (Large Size)</li>
<li>my-file-pdf-300x225.jpg (Medium Size)</li>
<li>my-file-pdf-150x150.jpg (Thumbnail size)</li>
<li>...(And Your Custom File Sizes)</li></ul>
</li></ul></li></ul>

= Insert HTML into the editor =
Select a PDF file in the Media Uploader and insert it into the the editor. An output HTML is automatically rewritten like below.
<code>
<a class="link-to-pdf" title="dummy-pdf" href="http://exmaple.com/wp-content/uploads/2015/01/dummy-pdf.pdf" target="_blank"><img class="size-medium wp-image-9999" src="http://exmaple.com/wp-content/uploads/2015/01/dummy-pdf-pdf-227x320.jpg" alt="thumbnail-of-dummy-pdf" width="227" height="320" /></a>
</code>
*If you are using a document viewer plugin (like GDE) and want insert html which is made by it, select "Default (Title)" of "media" selector.



== Screenshots ==

1. No setup, just upload a PDF file and insert it into the editor.
2. An inserted PDF image is editable (align, sizes, etc...) just like an ordinary image file.
3. You can set PDF as Featured Image and use functions for PDF in the same way as an image file.
4. You can control default settings and generate thumbnails of already-uploaded PDFs in the plugin page.



== Installation ==

1. Copy the 'pdf-image-generator' folder into your plugins folder.
2. Activate the plugin via the 'Plugins' menu. The plugin requires no setup or modifying core wordpress files. 
See the 'Other Notes' tab for more info.



== Other Notes ==

= Get thumbnail in template file =
A generated image ID is stored in ['_thumbnail_id'] custom field of a PDF attachment post. An image ID is exportable by using get_post_thumbnail_id($your_pdf_id) or get_post_meta($your_pdf_id, '_thumbnail_id', true) functions in a template file.
<code>
$pdf_id = 'your PDF file ID';
if ( $thumbnail_id = get_post_thumbnail_id( $pdf_id ) ) {
echo '&lt;a class="pdf-link image-link" href="'.wp_get_attachment_url( $pdf_id ).'" title="'.esc_attr( get_the_title( $pdf_id ) ).'" target="_blank"&gt;'.wp_get_attachment_image ( $thumbnail_id, 'medium' ).'&lt;/a&gt;';
}
</code>

Or more simply, you can call images and link to PDF files by wp_get_attachment_image($your_pdf_id, $size) and wp_get_attachment_link($your_pdf_id, $size) functions. (v1.2 or later) *If the plugin is deactivated, this function just will return empty.
<code>
$pdf_id = 'your PDF file ID';
echo wp_get_attachment_image ( $pdf_id, 'medium' );
echo wp_get_attachment_link ( $pdf_id, 'medium' );
</code>
 If you call all PDFs that are attached to the post. You can get them by get_posts function.

Examples.
Dynamically get PDFs that are attached to the post.  
<code>
&lt;?php
$pdfs = get_posts( 'posts_per_page=-1&amp;post_type=attachment&amp;post_mime_type=application/pdf&amp;post_parent='.$post-&gt;ID );
if( $pdfs ): foreach( $pdfs as $pdf ):
  $thumbnail_id = get_post_thumbnail_id( $pdf-&gt;ID );
  if( $thumbnail_id ):
    echo '&lt;li&gt;';
    echo '&lt;a href="'wp_get_attachment_url( $pdf-&gt;ID )'" class="link-to-pdf"&gt;';
    echo wp_get_attachment_image ( $thumbnail_id, 'medium' );
    echo '&lt;/a&gt;';
    echo '&lt;/li&gt;';
  endif;
endforeach; endif;
?&gt;
</code>


= Set PDF thumbnail as Featured Image =
The plugin support you to set thumbnail of PDF as Featured Image. (v1.2 or later) 
Strictly explaining, when you set a PDF as Featured Image, the plugin automatically set a thumbnail of this PDF. 
You can call thumbnail by using get_the_post_thumbnail function.
<code>
echo get_the_post_thumbnail ( $post->ID, 'medium' );
</code>

May you want pdf file from the post thumbnail.
<code>
$thumb_id = get_post_thumbnail_id ( $post-&gt;ID );
$pdf_id = get_post( $thumb_id )-&gt;post_parent;
if ( $pdf_id &amp;&amp; get_post_mime_type ( $pdf_id ) === 'application/pdf' ){
  $pdf = get_post($pdf_id);
  echo '&lt;a class="link-to-pdf" href="'.wp_get_attachment_url($pdf_id).'" title="'.esc_html($pdf-&gt;post_title).'" target="_blank"&gt;'.get_the_post_thumbnail().'&lt;/a&gt;'."\n";
}
</code>


= Display attachment data with in caption =
The plugin allows you to insert [caption] short-code into the post content area as when insert an image. If you want to display attachment title, description, file type, file size and so on, use img_caption_shortcode filter in your functions.php. 
Here's an example code...
<code>function add_attachment_data_in_caption( $empty, $attr, $content ) {
    $attr = shortcode_atts( array( 'id'=&gt;'', 'align'=&gt;'alignnone', 'width'=&gt;'', 'caption'=&gt;'' ), $attr );
    if ( 1 &gt; (int) $attr['width'] || empty( $attr['caption'] ) ) return '';
    if ( $attr['id'] ) {
        $attr['id'] = 'id="' . esc_attr( $attr['id'] ) . '" ';
        $attachment_id = explode('_', $attr['id']);
        $attachment_id = $attachment_id[1];// get attachment id
        if( get_post_mime_type ( $attachment_id ) === 'application/pdf' ){ 
            $attachment = get_post( $attachment_id );
            $bytes = filesize( get_attached_file( $attachment-&gt;ID ) );
            if ($bytes &gt;= 1073741824) $bytes = number_format($bytes / 1073741824, 2). ' GB';
            elseif ($bytes &gt;= 1048576) $bytes = number_format($bytes / 1048576, 2). ' MB';
            elseif ($bytes &gt;= 1024) $bytes = number_format($bytes / 1024, 2). ' KB';
            elseif ($bytes &gt; 1) $bytes = $bytes. ' bytes';
            elseif ($bytes == 1) $bytes = $bytes. ' byte';
            else $bytes = '0 bytes';
            $attr['caption'] = 
              'title : ' .$attachment-&gt;post_title. '&lt;br/&gt;' . // title
              'caption : ' .$attr['caption']. '&lt;br/&gt;' .// caption
              'size : ' .$bytes. '&lt;br/&gt;' . // file size
              'filetype : ' .get_post_mime_type ( $attachment_id ). '&lt;br/&gt;' . // file type
              'description : ' .$attachment-&gt;post_content. '&lt;br/&gt;'; // description
        }
    }

    return 
      '&lt;div ' .$attr['id']. 'class="wp-caption ' .esc_attr( $attr['align'] ). '" style="max-width: ' .( 10 + (int) $attr['width'] ). 'px;"&gt;' .
      do_shortcode( $content ). '&lt;p class="wp-caption-text"&gt;' .$attr['caption']. '&lt;/p&gt;' .
      '&lt;/div&gt;';
}
add_filter('img_caption_shortcode', 'add_attachment_data_in_caption', 10, 3);</code>


= Generate thumbnails of all PDFs in the media library =
You can generate thumbnails of any already-uploaded PDFs.
Activate the plugin and Click "Generate Thumbnails Now" link in "settings". (v1.3.3 or later)


= Change attachment link attributes =
<code>
function pigen_filter_attachment_link ( $html, $attach_id, $attach_url, $attach_output ){
  $attach_title = get_the_title( $attach_id );
  $html = '<a class="link-to-pdf" href="'.$attach_url.'" rel="attachment wp-att-' .esc_attr($attach_id). '" title="'.esc_attr( $attach_title ).'" target="_blank">' .$attach_output. '</a>';
  return $html;
};
add_filter( 'pigen_filter_attachment_link', 'pigen_filter_attachment_link', 10, 4 );
</code>


= Change thumbnail attributes =
<code>
function pigen_filter_attachment_output ( $attach_output, $thumbnail_id, $thumbnail, $size, $align ){
  $attach_output =  '<img src="'. $thumbnail[0] .'" alt="'.get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ).'" width="'. $thumbnail[1] .'" height="'. $thumbnail[2] .'" class="'.( $align ? 'align' .$align. ' ' : '' ). 'size-' .esc_attr( $size ). ' wp-image-'.$thumbnail_id.'" />';
  return $attach_output;
};
add_filter( 'pigen_filter_attachment_output', 'pigen_filter_attachment_output', 10, 5 );
</code>


= Modify imageMagick's Settings =
Filters allow you to add your own modify imageMagick's behaviour by hooking.

Sample usage for imageMagick user.
<code>
function pigen_filter_convert_file_basename( $file_basename ){
  $file_basename = str_replace( '.jpg', '.png', $file_basename );
  return $file_basename;
};
add_filter( 'pigen_filter_convert_file_basename', 'pigen_filter_convert_file_basename' );

function pigen_filter_convert_imageMagick( $imageMagick, $before_name, $after_name, $max_width, $max_height ){
  $imageMagick = "convert -density 150 -quality 80 -background black -flatten {$max_width}x{$max_height} {$before_name} {$after_name}";
  return $imageMagick;
};
add_filter( 'pigen_filter_convert_imageMagick', 'pigen_filter_convert_imageMagick', 10, 5 );
</code>

Sample usage for imagick extension user.
<code>
function pigen_filter_convert_file_basename( $file_basename ){
  $file_basename = str_replace( '.jpg', '.png', $file_basename );
  return $file_basename;
};
add_filter( 'pigen_filter_convert_file_basename', 'pigen_filter_convert_file_basename' );

function pigen_filter_convert_imagick( $imagick ){
  $imagick-&gt;setImageBackgroundColor( 'black' );
  $imagick-&gt;setCompressionQuality( 80 );
  $imagick-&gt;setImageFormat( 'png' );
return $imagick;
};
add_filter( 'pigen_filter_convert_imagick', 'pigen_filter_convert_imagick' );
</code>


= Automatically save image/PDF as featured image =
Automatically set PDF thumbnail as featured image.
<code>
function save_pdf_thumb_as_featuredimage ( $post_id ) { 
    if ( wp_is_post_revision( $post_id ) ) return; 
    if ( get_post_type( $post_id ) !== 'post' ) return; // set your post type
    if ( get_post_meta( $post_id, '_thumbnail_id', true ) ) return; // post already has featured image
    $attaches = get_posts ( 'post_parent='.$post_id.'&amp;numberposts=-1&amp;post_type=attachment&amp;post_mime_type=application/pdf&amp;orderby=menu_order&amp;order=ASC' );
    if ( $attaches ): foreach( $attaches as $attach ):
        if ( $thumb_id = get_post_meta( $attach-&gt;ID, '_thumbnail_id', true ) ){ // if pdf has thumbnail
            update_post_meta( $post_id, '_thumbnail_id', $thumb_id );
            break;
        }
    endforeach; endif;
}
add_action( 'save_post', 'save_pdf_thumb_as_featuredimage' );
</code>

Automatically set first image/PDF as featured image.
<code>
function save_thumbnail_as_featuredimage ( $post_id ) { 
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( get_post_type( $post_id ) !== 'post' ) return; // set your post type
    if ( get_post_meta( $post_id, '_thumbnail_id', true ) ) return; // post already has featured image
    $args = array(
        'post_parent' =&gt; $post_id,
        'post_type' =&gt; 'attachment',
        'numberposts' =&gt; -1,
        'post_status' =&gt; null,
        'orderby' =&gt; 'menu_order date',
        'order' =&gt; 'ASC ASC'
    );
    $attaches = get_posts($args);
    if ( $attaches ): foreach( $attaches as $attach ):
        if ( $attach-&gt;post_mime_type == 'application/pdf' ){
            if ( $thumb_id = get_post_meta( $attach-&gt;ID, '_thumbnail_id', true ) ){ // if pdf has thumbnail
                update_post_meta( $post_id, '_thumbnail_id', $thumb_id );
                break;
            }
        } elseif ( preg_match("/^image\//", $attach-&gt;post_mime_type ) ) {
            update_post_meta( $post_id, '_thumbnail_id', $attach-&gt;ID );
            break;
        }
    endforeach; endif;
}
add_action( 'save_post', 'save_thumbnail_as_featuredimage' );
</code>


== ChangeLog ==

= 1.4.4 =
12.May.2016. Add Color detection process and command of ICC Profile application. Recommend to regenerate CMYK pdf files.

= 1.4.3 =
11.May.2016. Add thumbnail box in an edit page of PDF. Change fork process by ImageMagick Versions and convert settings.

= 1.4.2 =
8.May.2016. Correspondence to GlotPress. Change the default resolution to 300dpi.

= 1.4.1 =
14.Apr.2016. Divide converting processes by IM versions. Use "flattenImages" to imagick api with IM lt6.3.8. Use "-alpha remove" to IM gte6.7.5.

= 1.4.0 =
5.Apr.2016. Add option to convert png and background color selector. Modify to avoid overwriting GDE (Google Doc Embedder) hook.

= 1.3.9 =
20.Mar.2016. Change imagick filter flattenImages() to "setImageAlphaChannel" & "mergeImageLayers". Modify behavior of inserting image without media link. Change process of activation.

= 1.3.8 =
12.Feb.2016. Add filter to hide image file of PDF on List mode of Media Library. Modify behavior of wp.media.view.settings. Arrange and clean up options of Featured Image which doesn't function well.

= 1.3.7 =
7.Feb.2016. Add filter to attachment link and change format of renaming file basename.

= 1.3.6 =
22.Dec 2015. Modify css and activate message.

= 1.3.5 =
20.Nov 2015. Modify input fields of Max-width, Max-height and quality to accept only numeric values. Fix behavior of "hide thumbnail" option. Update translation files.

= 1.3.4 =
30.Oct 2015. Add option to manually set maximum size of generated image. Add option to regenerate images of already uploaded PDFs. Fix continuity of settings.

= 1.3.3 =
21.Oct 2015. Fix and add filters for modifying imageMagick settings.

= 1.3.2 =
20.Oct 2015. Add an option for select the functions of imageMagick. Fix default alignment of insert media. Update translation files.

= 1.3.1 =
18.Oct 2015. Fix.

= 1.3 =
17.Oct 2015. Define classes in the plugin file. Add Attachment Display Settings when trying to insert PDF.

= 1.2.2 =
14.Oct 2015. Fix.

= 1.2.1 =
12.Oct 2015. Add the plugin page, customizable options, and Japanese language files. 

= 1.2.0 =
09.Oct 2015. Thumbnail files themselves now hidden in the media library. Allow user to set PDF as Featured Image and to use wp_get_attachment_image function to a PDF file. 

= 1.1.6 =
28.June 2015. Disabl pigen_change_icon function only in the static media library.

= 1.1.5 =
13.May 2015. Automatically add caption short-code when the caption field filled.

= 1.1.4 =
02.May 2015. Remove the [0] from the image file name.

= 1.1.3 =
24.Apr 2015. Change the way to check in an activation if exec() is enabled or disabled on a server.

= 1.1.2 =
4.Apr 2015. Remove the process of generating a test file.

= 1.1.1 =
14.Mar 2015. Fix colorspace bug, modified register_activation_hook and error messages.

= 1.1 =
3.Feb 2015. Add support for imagick Extension, and added uninstall.php.

= 1.0.1 =
17.Jan 2015. Add Verifying ImageMagick installation

= 1.0 =
12.Jan 2015. First public version Release

= 0.1 =
26.Sep 2014. Initial Release
