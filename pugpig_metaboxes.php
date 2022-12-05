<?php
/**
 * Registering meta boxes
 *
 * All the definitions of meta boxes are listed below with comments.
 * Please read them CAREFULLY.
 *
 * You also should read the changelog to know what has been changed before updating.
 *
 * For more information, please visit:
 * @link http://www.deluxeblogtips.com/meta-box/
 */

/********************* META BOX DEFINITIONS ***********************/

/********************* META BOX REGISTERING ***********************/

/**
 * Register meta boxes
 *
 * @return void
 */
function pugpigmb_register_meta_boxes()
{
	// Make sure there's no errors when the plugin is deactivated or during upgrade
    if ( !class_exists( 'RW_Meta_Box' ) )
		return;

	/**
	 * Prefix of meta keys (optional)
	 * Use underscore (_) at the beginning to make keys hidden
	 * Alt.: You also can make prefix empty to disable it
	 */
	// Better has an underscore as last sign
    $prefix = 'pugpigmb_';

	$meta_boxes = array();

	// 2nd meta box
    $meta_boxes[] = array(
		'title' => 'Newsstand Content',
		'pages'    => array( PUGPIG_EDITION_POST_TYPE),
		'priority' => 'high',

		'fields' => array(
			array(
				'name' => 'Long Description',
				'desc' => ' Longer (optional) description for the edition. This is not used in the app/s, but will overwrite the Summary if
	    you are automatically feeding information using the iTunes Newsstand ATOM feed. Min. 10 characters; max. 2000 characters.',
				'id'   => "{$prefix}newsstand_long_desc",
				'type' => 'textarea',
				'cols' => '50',
				'rows' => '3',
			),
			// IMAGE UPLOAD
            array(
				'name' => 'Cover Upload',
				'desc' => 'This must be a PNG, at least 1024px high, with an aspect ratio between 0.5 and 2.0. It will be used in the Newsstand ATOM feed.',
				'id'   => "{$prefix}newsstand_cover",
				'type' => 'image',
			),
			/*
			array(
				'name' => 'Thichbox Image Upload',
				'id'   => "{$prefix}thickbox",
				'type' => 'thickbox_image',
			),
			*/
		)
	);

    // Add PDF uploader if allowed in settings
    if (pugpig_pdf_allowed()){
    	$meta_boxes[] = array(
			'title' => 'PDF edition',
			'pages'    => array( PUGPIG_EDITION_POST_TYPE),
			'priority' => 'high',
	
			'fields' => array(
    	        array(
					'name' => 'PDF Upload',
					'desc' => 'This is an optional PDF which will replace HTML pages in the edition',
					'id'   => '{$prefix}edition_pdf',
					'type' => 'file',
				)
			)
		);
    }

	foreach ($meta_boxes as $meta_box) {
		new RW_Meta_Box( $meta_box );
	}
}
// Hook to 'admin_init' to make sure the meta box class is loaded before
// (in case using the meta box class in another plugin)
// This is also helpful for some conditionals like checking page template, categories, etc.
add_action( 'admin_init', 'pugpigmb_register_meta_boxes', 11);
