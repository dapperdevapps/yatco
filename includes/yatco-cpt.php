<?php
/**
 * Custom Post Type Registration
 * 
 * Registers the 'yacht' custom post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register Yacht CPT on activation.
 */
function yatco_create_cpt() {
    register_post_type(
        'yacht',
        array(
            'labels'       => array(
                'name'          => 'Yachts',
                'singular_name' => 'Yacht',
            ),
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => array( 'slug' => 'yachts' ),
            'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
            'show_in_rest' => true,
        )
    );
    flush_rewrite_rules();
}

