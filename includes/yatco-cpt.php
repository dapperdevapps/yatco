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
    
    // Register custom taxonomies for archives
    yatco_register_taxonomies();
    
    flush_rewrite_rules();
}

/**
 * Register custom taxonomies for Builder, Vessel Type, and Category.
 * These enable archive pages and better organization.
 */
function yatco_register_taxonomies() {
    // Builder Taxonomy
    register_taxonomy(
        'yacht_builder',
        'yacht',
        array(
            'labels'            => array(
                'name'          => 'Builders',
                'singular_name' => 'Builder',
                'menu_name'     => 'Builders',
            ),
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false, // Non-hierarchical (like tags)
            'rewrite'           => array( 'slug' => 'yacht-builder' ),
            'query_var'         => true,
        )
    );
    
    // Vessel Type Taxonomy
    register_taxonomy(
        'yacht_vessel_type',
        'yacht',
        array(
            'labels'            => array(
                'name'          => 'Vessel Types',
                'singular_name' => 'Vessel Type',
                'menu_name'     => 'Vessel Types',
            ),
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'rewrite'           => array( 'slug' => 'yacht-type' ),
            'query_var'         => true,
        )
    );
    
    // Category Taxonomy
    register_taxonomy(
        'yacht_category',
        'yacht',
        array(
            'labels'            => array(
                'name'          => 'Yacht Categories',
                'singular_name' => 'Category',
                'menu_name'     => 'Categories',
            ),
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => true, // Hierarchical (like categories) - allows sub-categories
            'rewrite'           => array( 'slug' => 'yacht-category' ),
            'query_var'         => true,
        )
    );
}

// Register taxonomies on init (not just on activation)
add_action( 'init', 'yatco_register_taxonomies', 0 );

/**
 * Load single yacht template from plugin if theme doesn't have one.
 * 
 * WordPress will use single-yacht.php from your theme if it exists.
 * Otherwise, it will use this plugin's template.
 */
function yatco_load_single_yacht_template( $template ) {
    global $post;
    
    // Only for yacht post type
    if ( ! $post || $post->post_type !== 'yacht' ) {
        return $template;
    }
    
    // Check if theme has a single-yacht.php template
    $theme_template = locate_template( array( 'single-yacht.php' ) );
    
    // If theme has a template, use it (theme takes priority)
    if ( $theme_template ) {
        return $theme_template;
    }
    
    // Otherwise, use plugin template
    $plugin_template = YATCO_PLUGIN_DIR . 'templates/single-yacht.php';
    if ( file_exists( $plugin_template ) ) {
        return $plugin_template;
    }
    
    // Fallback to default single template
    return $template;
}
add_filter( 'single_template', 'yatco_load_single_yacht_template' );

