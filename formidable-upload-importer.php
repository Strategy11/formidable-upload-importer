<?php
/*
Plugin Name: Formidable Upload Importer
Description: Attach uploads to the imported entry
Version: 1.0
Plugin URI: http://formidablepro.com/
Author URI: http://strategy11.com
Author: Strategy11
*/

add_filter('frm_import_val', 'frm_import_attachment', 10, 2);
function frm_import_attachment($val, $field){
	// Set up global vars to track uploaded files
	frm_setup_global_media_import_vars( $field );

    if ( $field->type != 'file' || is_numeric($val) || empty($val) ) {
        return $val;
    }

    global $wpdb, $frm_vars;
    
    if ( is_array($val) ) {
        $vals = $val;
    } else {
        $vals = str_replace('<br/>', ',', $val);
        $vals = explode(',', $vals);
    }

    $new_val = array();
    foreach ( (array) $vals as $v ) {
        $v = trim($v);
        //check to see if the attachment already exists on this site
        $exists = $wpdb->get_var($wpdb->prepare('SELECT ID FROM '. $wpdb->posts .' WHERE guid = %s', $v ));
        if ( $exists ) {
            $new_val[] = $exists;
        } else {
			// Get media ID for newly uploaded image
			$mid = frm_curl_image( $v );
			$new_val[] = $mid;
			if ( is_numeric( $mid ) ) {
				// Add newly uploaded images to the global media IDs for this field.
				$frm_vars['media_id'][$field->id][] = $mid;
			}
		}
        unset($v);
    }
    if ( count($new_val) == 1 ) {
        $val = reset($new_val);
    } else {
        $val = implode(',', $new_val);
    }
    
    return $val;
}

/**
* Set up global media_id vars. This will be used for post fields.
*/
function frm_setup_global_media_import_vars( $field ){
	if ( $field->type != 'file' ) {
		return;
	}

	global $frm_vars;

	// If it hasn't been set yet, set it now
	if ( ! isset( $frm_vars['media_id'] ) ) {
		$frm_vars['media_id'] = array();
		$frm_vars['media_id'][$field->id] = array();

	// If media_id was set for the current field in a previous entry, clear it now
	} else if ( isset( $frm_vars['media_id'][$field->id] ) ) {
		// Clear out old values
		$frm_vars['media_id'][$field->id] = array();
	}
}
function frm_curl_image($img_url) {
    $ch = curl_init(str_replace(array(' '), array('%20'), $img_url));
    $uploads = wp_upload_dir();
    $filename = wp_unique_filename( $uploads['path'], basename($img_url));
    $path =  trailingslashit($uploads['path']); // dirname(__FILE__) . '/screenshots/';
    $fp = fopen($path . $filename, 'wb');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $result = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    if ( $result ) {
        return frm_attach_existing_image($filename);
    } else {
        unlink($path . $filename);
        //echo "<p>Failed to download image $img_url";
        return $img_url;
    }
}

function frm_attach_existing_image($filename){
    $uploads = wp_upload_dir();
    $file = $uploads['path'] . "/$filename";
    $url = $uploads['url'] . "/$filename";
    if ( function_exists('finfo_file') ) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $type = finfo_file($finfo, $file);
        finfo_close($finfo);
        unset($finfo);
    } else {
        $type = mime_content_type($file);
    }

    $name_parts = pathinfo($file);
    $name = trim( substr( $name_parts['basename'], 0, -(1 + strlen($name_parts['extension'])) ) );

    // Construct the attachment array
    $attachment = array(
        'post_mime_type' => $type,
        'guid' => $url,
        'post_title' => $name,
        'post_content' => '',
    );

    // Save the data
    $id = wp_insert_attachment($attachment, $file);
    
    if ( ! function_exists('wp_generate_attachment_metadata') ) {
        require_once(ABSPATH .'wp-admin/includes/image.php');
    }
    
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );

    return $id;
}

add_action('admin_init', 'frm_upim_include_updater', 1);
function frm_upim_include_updater(){
    include_once(dirname(__FILE__) .'/FrmUpImUpdate.php');
    $obj = new FrmUpImUpdate();
}