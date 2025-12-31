<?php
/**
 * Shortcode Functions
 * 
 * Handles shortcode registration and vessel display functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register shortcode for displaying YATCO vessels.
 */
function yatco_register_shortcode() {
    add_shortcode( 'yatco_vessels', 'yatco_vessels_shortcode' );
}

/**
 * Generate HTML from vessel data (helper function for cached data).
 */
function yatco_generate_vessels_html_from_data( $vessels, $builders, $categories, $types, $conditions, $atts ) {
    if ( empty( $vessels ) ) {
        return '<p>No vessels match your criteria.</p>';
    }

    $columns = intval( $atts['columns'] );
    if ( $columns < 1 || $columns > 4 ) {
        $columns = 3;
    }

    $column_class = 'yatco-col-' . $columns;
    $show_price = $atts['show_price'] === 'yes';
    $show_year  = $atts['show_year'] === 'yes';
    $show_loa   = $atts['show_loa'] === 'yes';
    $show_filters = $atts['show_filters'] === 'yes';
    $currency = strtoupper( $atts['currency'] ) === 'EUR' ? 'EUR' : 'USD';
    $length_unit = strtoupper( $atts['length_unit'] ) === 'M' ? 'M' : 'FT';

    ob_start();
    ?>
    <div class="yatco-vessels-container" data-currency="<?php echo esc_attr( $currency ); ?>" data-length-unit="<?php echo esc_attr( $length_unit ); ?>">
        <?php if ( $show_filters ) : ?>
        <div class="yatco-filters">
            <div class="yatco-filters-row yatco-filters-row-1">
                <div class="yatco-filter-group">
                    <label for="yatco-keywords">Keywords</label>
                    <input type="text" id="yatco-keywords" class="yatco-filter-input" placeholder="Boat Name, Location, Features" />
                </div>
                <div class="yatco-filter-group">
                    <label for="yatco-builder">Builder</label>
                    <select id="yatco-builder" class="yatco-filter-select">
                        <option value="">Any</option>
                        <?php foreach ( $builders as $builder ) : ?>
                            <option value="<?php echo esc_attr( $builder ); ?>"><?php echo esc_html( $builder ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yatco-filter-group">
                    <label>Year</label>
                    <div class="yatco-filter-range">
                        <input type="number" id="yatco-year-min" class="yatco-filter-input yatco-input-small" placeholder="Min" />
                        <span>-</span>
                        <input type="number" id="yatco-year-max" class="yatco-filter-input yatco-input-small" placeholder="Max" />
                    </div>
                </div>
                <div class="yatco-filter-group">
                    <label>Length</label>
                    <div class="yatco-filter-range">
                        <input type="number" id="yatco-loa-min" class="yatco-filter-input yatco-input-small" placeholder="Min" step="0.1" />
                        <span>-</span>
                        <input type="number" id="yatco-loa-max" class="yatco-filter-input yatco-input-small" placeholder="Max" step="0.1" />
                    </div>
                    <div class="yatco-filter-toggle">
                        <button type="button" class="yatco-toggle-btn yatco-ft active" data-unit="FT">FT</button>
                        <button type="button" class="yatco-toggle-btn yatco-m" data-unit="M">M</button>
                    </div>
                </div>
                <div class="yatco-filter-group">
                    <label>Price</label>
                    <div class="yatco-filter-range">
                        <input type="number" id="yatco-price-min" class="yatco-filter-input yatco-input-small" placeholder="Min" step="1" />
                        <span>-</span>
                        <input type="number" id="yatco-price-max" class="yatco-filter-input yatco-input-small" placeholder="Max" step="1" />
                    </div>
                    <div class="yatco-filter-toggle">
                        <button type="button" class="yatco-toggle-btn yatco-usd active" data-currency="USD">USD</button>
                        <button type="button" class="yatco-toggle-btn yatco-eur" data-currency="EUR">EUR</button>
                    </div>
                </div>
            </div>
            <div class="yatco-filters-row yatco-filters-row-2">
                <div class="yatco-filter-group">
                    <label for="yatco-condition">Condition</label>
                    <select id="yatco-condition" class="yatco-filter-select">
                        <option value="">Any</option>
                        <?php foreach ( $conditions as $condition ) : ?>
                            <option value="<?php echo esc_attr( $condition ); ?>"><?php echo esc_html( $condition ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yatco-filter-group">
                    <label for="yatco-type">Type</label>
                    <select id="yatco-type" class="yatco-filter-select">
                        <option value="">Any</option>
                        <?php foreach ( $types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yatco-filter-group">
                    <label for="yatco-category">Category</label>
                    <select id="yatco-category" class="yatco-filter-select">
                        <option value="">Any</option>
                        <?php foreach ( $categories as $category ) : ?>
                            <option value="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yatco-filter-group">
                    <label for="yatco-cabins">Cabins</label>
                    <select id="yatco-cabins" class="yatco-filter-select">
                        <option value="">Any</option>
                        <option value="1">1+</option>
                        <option value="2">2+</option>
                        <option value="3">3+</option>
                        <option value="4">4+</option>
                        <option value="5">5+</option>
                        <option value="6">6+</option>
                    </select>
                </div>
                <div class="yatco-filter-group yatco-filter-actions">
                    <button type="button" id="yatco-search-btn" class="yatco-search-btn">Search</button>
                    <button type="button" id="yatco-reset-btn" class="yatco-reset-btn">Reset</button>
                </div>
            </div>
        </div>
        <div class="yatco-results-header">
            <span class="yatco-results-count">0 - 0 of <span id="yatco-total-count"><?php echo count( $vessels ); ?></span> YACHTS FOUND</span>
            <div class="yatco-sort-view">
                <label for="yatco-sort">Sort by:</label>
                <select id="yatco-sort" class="yatco-sort-select">
                    <option value="">Pick a sort</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                    <option value="year_desc">Year: Newest First</option>
                    <option value="year_asc">Year: Oldest First</option>
                    <option value="length_desc">Length: Largest First</option>
                    <option value="length_asc">Length: Smallest First</option>
                    <option value="name_asc">Name: A to Z</option>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <div class="yatco-vessels-grid <?php echo esc_attr( $column_class ); ?>" id="yatco-vessels-grid">
        <?php foreach ( $vessels as $vessel ) : 
            // Get the link - prefer CPT permalink if post_id exists, otherwise use the link from vessel data
            $vessel_link = '';
            if ( ! empty( $vessel['post_id'] ) ) {
                $vessel_link = get_permalink( $vessel['post_id'] );
            } elseif ( ! empty( $vessel['link'] ) ) {
                $vessel_link = $vessel['link'];
            }
        ?>
            <a href="<?php echo esc_url( $vessel_link ); ?>" class="yatco-vessel-card" 
                 data-name="<?php echo esc_attr( strtolower( $vessel['name'] ) ); ?>"
                 data-location="<?php echo esc_attr( strtolower( $vessel['location'] ) ); ?>"
                 data-builder="<?php echo esc_attr( $vessel['builder'] ); ?>"
                 data-category="<?php echo esc_attr( $vessel['category'] ); ?>"
                 data-type="<?php echo esc_attr( $vessel['type'] ); ?>"
                 data-condition="<?php echo esc_attr( $vessel['condition'] ); ?>"
                 data-year="<?php echo esc_attr( $vessel['year'] ); ?>"
                 data-loa-feet="<?php echo esc_attr( $vessel['loa_feet'] ); ?>"
                 data-loa-meters="<?php echo esc_attr( $vessel['loa_meters'] ); ?>"
                 data-price-usd="<?php echo esc_attr( $vessel['price_usd'] ); ?>"
                 data-price-eur="<?php echo esc_attr( $vessel['price_eur'] ); ?>"
                 data-state-rooms="<?php echo esc_attr( $vessel['state_rooms'] ); ?>">
                <?php if ( ! empty( $vessel['image'] ) ) : ?>
                    <div class="yatco-vessel-image">
                        <img src="<?php echo esc_url( $vessel['image'] ); ?>" alt="<?php echo esc_attr( $vessel['name'] ); ?>" />
                    </div>
                <?php endif; ?>
                <div class="yatco-vessel-info">
                    <h3 class="yatco-vessel-name"><?php echo esc_html( $vessel['name'] ); ?></h3>
                    <?php if ( ! empty( $vessel['location'] ) ) : ?>
                        <div class="yatco-vessel-location"><?php echo esc_html( $vessel['location'] ); ?></div>
                    <?php endif; ?>
                    <div class="yatco-vessel-details">
                        <?php 
                        $display_price = null;
                        $currency_symbol = '$';
                        if ( $currency === 'EUR' && ! empty( $vessel['price_eur'] ) ) {
                            $display_price = $vessel['price_eur'];
                            $currency_symbol = '€';
                        } elseif ( ! empty( $vessel['price_usd'] ) ) {
                            $display_price = $vessel['price_usd'];
                        }
                        ?>
                        <?php if ( $show_price && $display_price ) : ?>
                            <span class="yatco-vessel-price"><?php echo esc_html( $currency_symbol . number_format( floatval( $display_price ) ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( $show_year && ! empty( $vessel['year'] ) ) : ?>
                            <span class="yatco-vessel-year"><?php echo esc_html( $vessel['year'] ); ?></span>
                        <?php endif; ?>
                        <?php 
                        $display_loa = null;
                        $loa_unit_text = ' ft';
                        if ( $length_unit === 'M' && ! empty( $vessel['loa_meters'] ) ) {
                            $display_loa = $vessel['loa_meters'];
                            $loa_unit_text = ' m';
                        } elseif ( ! empty( $vessel['loa_feet'] ) ) {
                            $display_loa = $vessel['loa_feet'];
                        }
                        ?>
                        <?php if ( $show_loa && $display_loa ) : ?>
                            <span class="yatco-vessel-loa"><?php echo esc_html( number_format( $display_loa, 1 ) . $loa_unit_text ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
    // Include CSS and JavaScript
    if ( file_exists( YATCO_PLUGIN_DIR . 'includes/yatco-shortcode-assets.php' ) ) {
        include YATCO_PLUGIN_DIR . 'includes/yatco-shortcode-assets.php';
    }
    return ob_get_clean();
}

/**
 * Shortcode to display YATCO vessels in real-time.
 * 
 * Usage: [yatco_vessels max="20" price_min="25000" price_max="500000" year_min="" year_max="" loa_min="" loa_max="" columns="3" show_price="yes" show_year="yes" show_loa="yes" show_filters="yes"]
 */
function yatco_vessels_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'max'           => '50',
            'price_min'     => '',
            'price_max'     => '',
            'year_min'      => '',
            'year_max'      => '',
            'loa_min'       => '',
            'loa_max'       => '',
            'columns'       => '3',
            'show_price'    => 'yes',
            'show_year'     => 'yes',
            'show_loa'      => 'yes',
            'cache'         => 'yes',
            'show_filters'  => 'yes',
            'currency'      => 'USD',
            'length_unit'   => 'FT',
        ),
        $atts,
        'yatco_vessels'
    );

    $token = yatco_get_token();
    if ( empty( $token ) ) {
        return '<p>YATCO API token is not configured.</p>';
    }

    // NEW CPT-BASED APPROACH: Query vessels from Custom Post Type
    // Check if cache is enabled (CPT-based, not transients)
    if ( $atts['cache'] === 'yes' ) {
        // Increase memory limit for shortcode execution
        @ini_set( 'memory_limit', '512M' );
        
        // Build WP_Query args for yacht CPT
        // CRITICAL: Use fields => 'ids' to only fetch IDs, not full post objects (saves memory)
        // Load all posts for client-side pagination (memory optimized with fields => 'ids')
        $query_args = array(
            'post_type'      => 'yacht',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Load all posts for client-side pagination (memory optimized)
            'fields'         => 'ids', // Only get IDs to save memory - this is the key optimization
            'meta_query'     => array(),
            'no_found_rows'  => true, // Skip counting total rows to save memory
            'update_post_meta_cache' => false, // Don't cache all meta - we'll fetch only what we need
            'update_post_term_cache' => false, // Don't cache terms
        );
        
        // Parse filter criteria
        $price_min = ! empty( $atts['price_min'] ) && $atts['price_min'] !== '0' ? floatval( $atts['price_min'] ) : '';
        $price_max = ! empty( $atts['price_max'] ) && $atts['price_max'] !== '0' ? floatval( $atts['price_max'] ) : '';
        $year_min  = ! empty( $atts['year_min'] ) && $atts['year_min'] !== '0' ? intval( $atts['year_min'] ) : '';
        $year_max  = ! empty( $atts['year_max'] ) && $atts['year_max'] !== '0' ? intval( $atts['year_max'] ) : '';
        $loa_min   = ! empty( $atts['loa_min'] ) && $atts['loa_min'] !== '0' ? floatval( $atts['loa_min'] ) : '';
        $loa_max   = ! empty( $atts['loa_max'] ) && $atts['loa_max'] !== '0' ? floatval( $atts['loa_max'] ) : '';
        
        // Add meta queries for filtering
        if ( $price_min !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_price_usd',
                'value'   => $price_min,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $price_max !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_price_usd',
                'value'   => $price_max,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $year_min !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_year',
                'value'   => $year_min,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $year_max !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_year',
                'value'   => $year_max,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $loa_min !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_length_feet',
                'value'   => $loa_min,
                'compare' => '>=',
                'type'    => 'DECIMAL',
            );
        }
        if ( $loa_max !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_length_feet',
                'value'   => $loa_max,
                'compare' => '<=',
                'type'    => 'DECIMAL',
            );
        }
        
        // Extract filter criteria from URL parameters (if not in shortcode atts)
        // This allows filtering via URL like ?category=Motor%20Yacht
        if ( ! empty( $_GET['builder'] ) ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_make',
                'value'   => sanitize_text_field( urldecode( $_GET['builder'] ) ),
                'compare' => '=',
            );
        }
        if ( ! empty( $_GET['category'] ) ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_category',
                'value'   => sanitize_text_field( urldecode( $_GET['category'] ) ),
                'compare' => '=',
            );
        }
        if ( ! empty( $_GET['type'] ) ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_type',
                'value'   => sanitize_text_field( urldecode( $_GET['type'] ) ),
                'compare' => '=',
            );
        }
        if ( ! empty( $_GET['condition'] ) ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_condition',
                'value'   => sanitize_text_field( urldecode( $_GET['condition'] ) ),
                'compare' => '=',
            );
        }
        
        // Check if cache is currently warming
        $cache_status = get_transient( 'yatco_cache_warming_status' );
        $cache_progress = get_transient( 'yatco_cache_warming_progress' );
        $is_warming = ( $cache_status !== false ) || ( $cache_progress !== false );
        
        // OPTIMIZATION: For initial page load, limit to first 12 vessels for instant display
        // Remaining vessels will be loaded via AJAX in the background
        // Check if URL parameters are present (filtering requested via URL)
        $has_url_params = ! empty( $_GET['keywords'] ) || ! empty( $_GET['builder'] ) || ! empty( $_GET['category'] ) || 
                         ! empty( $_GET['type'] ) || ! empty( $_GET['condition'] ) || ! empty( $_GET['year_min'] ) || 
                         ! empty( $_GET['year_max'] ) || ! empty( $_GET['loa_min'] ) || ! empty( $_GET['loa_max'] ) || 
                         ! empty( $_GET['price_min'] ) || ! empty( $_GET['price_max'] ) || ! empty( $_GET['cabins'] );
        
        $initial_load = ! isset( $_GET['yatco_ajax_load'] ) || $_GET['yatco_ajax_load'] !== 'all';
        
        // Query ALL posts first to get filter options (builders, categories, types, conditions)
        // This ensures filters show all available options, not just from the first 12 vessels
        $filter_query_args = $query_args;
        $filter_query_args['posts_per_page'] = -1; // Get all for filter options
        $filter_query_args['fields'] = 'ids';
        $filter_query_args['no_found_rows'] = true;
        $all_post_ids_for_filters = get_posts( $filter_query_args );
        
        // Always limit to first 12 for instant display (AJAX loads rest in background)
        // This prevents timeouts even when URL parameters are present
        $query_args['posts_per_page'] = 12;
        $query_args['no_found_rows'] = false; // Need total count for pagination
        
        // Query CPT posts for display (only IDs to save memory)
        $vessel_query = new WP_Query( $query_args );
        $post_ids = $vessel_query->posts;
        $total_vessels = $initial_load ? $vessel_query->found_posts : count( $post_ids ); // Get total count
        
        if ( ! empty( $post_ids ) ) {
            // Convert CPT posts to vessel data array
            $vessels = array();
            $builders = array();
            $categories = array();
            $types = array();
            $conditions = array();
            
            // OPTIMIZATION: Fetch all meta data in bulk using a single SQL query
            global $wpdb;
            $meta_keys = array(
                'yacht_vessel_id', 'yacht_price_usd', 'yacht_price_eur', 'yacht_price',
                'yacht_year', 'yacht_length', 'yacht_length_feet', 'yacht_length_meters',
                'yacht_make', 'yacht_category', 'yacht_type', 'yacht_condition',
                'yacht_location', 'yacht_state_rooms', 'yacht_image_url'
            );
            
            // OPTIMIZATION: Get filter options directly from meta table using DISTINCT queries
            // This is MUCH faster than loading all vessel IDs first - we query distinct values directly
            // Only join with posts table to ensure we only get published yacht posts
            
            // Get distinct builders (yacht_make)
            $builders_query = $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value 
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = 'yacht_make'
                 AND p.post_type = 'yacht'
                 AND p.post_status = 'publish'
                 AND pm.meta_value != ''
                 ORDER BY pm.meta_value ASC"
            );
            $builders_results = $wpdb->get_col( $builders_query );
            $builders = array_filter( array_map( 'trim', $builders_results ) );
            
            // Get distinct categories
            $categories_query = $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value 
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = 'yacht_category'
                 AND p.post_type = 'yacht'
                 AND p.post_status = 'publish'
                 AND pm.meta_value != ''
                 ORDER BY pm.meta_value ASC"
            );
            $categories_results = $wpdb->get_col( $categories_query );
            $categories = array_filter( array_map( 'trim', $categories_results ) );
            
            // Get distinct types
            $types_query = $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value 
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = 'yacht_type'
                 AND p.post_type = 'yacht'
                 AND p.post_status = 'publish'
                 AND pm.meta_value != ''
                 ORDER BY pm.meta_value ASC"
            );
            $types_results = $wpdb->get_col( $types_query );
            $types = array_filter( array_map( 'trim', $types_results ) );
            
            // Get distinct conditions
            $conditions_query = $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value 
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = 'yacht_condition'
                 AND p.post_type = 'yacht'
                 AND p.post_status = 'publish'
                 AND pm.meta_value != ''
                 ORDER BY pm.meta_value ASC"
            );
            $conditions_results = $wpdb->get_col( $conditions_query );
            $conditions = array_filter( array_map( 'trim', $conditions_results ) );
            
            // Build placeholders for SQL IN clause (prepare handles arrays properly)
            $post_ids_placeholder = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
            $meta_keys_placeholder = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
            
            // Prepare statement with all values
            $prepare_values = array_merge( $post_ids, $meta_keys );
            
            // Fetch all meta in one query (much faster than individual get_post_meta calls)
            $meta_query = $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value 
                 FROM {$wpdb->postmeta} 
                 WHERE post_id IN ($post_ids_placeholder) 
                 AND meta_key IN ($meta_keys_placeholder)",
                $prepare_values
            );
            
            $all_meta_results = $wpdb->get_results( $meta_query, ARRAY_A );
            
            // Organize meta by post_id for easy lookup
            $meta_by_post = array();
            foreach ( $all_meta_results as $meta_row ) {
                $post_id = intval( $meta_row['post_id'] );
                if ( ! isset( $meta_by_post[ $post_id ] ) ) {
                    $meta_by_post[ $post_id ] = array();
                }
                $meta_by_post[ $post_id ][ $meta_row['meta_key'] ] = $meta_row['meta_value'];
            }
            
            // Get all post titles in one query
            $title_placeholder = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
            $title_query = $wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($title_placeholder)",
                $post_ids
            );
            $titles = $wpdb->get_results( $title_query, OBJECT_K );
            
            // Bulk fetch featured image IDs for posts that might need them (if yacht_image_url is missing)
            // This is much faster than calling get_post_thumbnail_id() individually
            $thumbnail_query = $wpdb->prepare(
                "SELECT post_id, meta_value as thumbnail_id 
                 FROM {$wpdb->postmeta} 
                 WHERE post_id IN ($title_placeholder) 
                 AND meta_key = '_thumbnail_id'",
                $post_ids
            );
            $thumbnail_results = $wpdb->get_results( $thumbnail_query, ARRAY_A );
            
            // Build array: post_id => thumbnail_id
            $thumbnail_ids = array();
            foreach ( $thumbnail_results as $thumb_row ) {
                $thumbnail_ids[ intval( $thumb_row['post_id'] ) ] = intval( $thumb_row['thumbnail_id'] );
            }
            
            // Fetch all meta data efficiently
            foreach ( $post_ids as $post_id ) {
                // Get meta from our pre-fetched array
                $all_meta = isset( $meta_by_post[ $post_id ] ) ? $meta_by_post[ $post_id ] : array();
                
                // Ensure all keys exist (set to empty string if missing)
                foreach ( $meta_keys as $key ) {
                    if ( ! isset( $all_meta[ $key ] ) ) {
                        $all_meta[ $key ] = '';
                    }
                }
                
                // Extract values
                $vessel_id = $all_meta['yacht_vessel_id'];
                $price_usd = $all_meta['yacht_price_usd'];
                $price_eur = $all_meta['yacht_price_eur'];
                $price = $all_meta['yacht_price'];
                $year = $all_meta['yacht_year'];
                $loa = $all_meta['yacht_length'];
                $loa_feet = $all_meta['yacht_length_feet'];
                $loa_meters = $all_meta['yacht_length_meters'];
                $builder = $all_meta['yacht_make'];
                $category = $all_meta['yacht_category'];
                $type = $all_meta['yacht_type'];
                $condition = $all_meta['yacht_condition'];
                $location = $all_meta['yacht_location'];
                $state_rooms = $all_meta['yacht_state_rooms'];
                $image_url = $all_meta['yacht_image_url'];
                
                // Get post title from pre-fetched array
                $post_title = isset( $titles[ $post_id ] ) ? $titles[ $post_id ]->post_title : '';
                
                // If no image URL from meta, try featured image (thumbnail ID was bulk-fetched)
                // Only call wp_get_attachment_image_url() if needed (most vessels have yacht_image_url)
                if ( empty( $image_url ) && isset( $thumbnail_ids[ $post_id ] ) && $thumbnail_ids[ $post_id ] > 0 ) {
                    $image_url = wp_get_attachment_image_url( $thumbnail_ids[ $post_id ], 'medium' );
                }
                
                $vessel_data = array(
                    'id'          => $vessel_id ? $vessel_id : $post_id,
                    'post_id'     => $post_id,
                    'name'        => $post_title,
                    'price'       => $price,
                    'price_usd'   => $price_usd,
                    'price_eur'   => $price_eur,
                    'year'        => $year,
                    'loa'         => $loa,
                    'loa_feet'    => $loa_feet,
                    'loa_meters'  => $loa_meters,
                    'builder'     => $builder,
                    'category'    => $category,
                    'type'        => $type,
                    'condition'   => $condition,
                    'state_rooms' => $state_rooms,
                    'location'    => $location,
                    'image'       => $image_url,
                    'link'        => get_permalink( $post_id ),
                );
                
                $vessels[] = $vessel_data;
                
                // Only collect filter options from displayed vessels if we didn't already collect from ALL vessels
                // (This happens when initial_load is false, meaning all vessels are being loaded)
                if ( ! $initial_load || empty( $all_post_ids_for_filters ) ) {
                    if ( ! empty( $builder ) && ! in_array( $builder, $builders ) ) {
                        $builders[] = $builder;
                    }
                    if ( ! empty( $category ) && ! in_array( $category, $categories ) ) {
                        $categories[] = $category;
                    }
                    if ( ! empty( $type ) && ! in_array( $type, $types ) ) {
                        $types[] = $type;
                    }
                    if ( ! empty( $condition ) && ! in_array( $condition, $conditions ) ) {
                        $conditions[] = $condition;
                    }
                }
            }
            // No need for wp_reset_postdata() since we used get_posts() with fields => 'ids'
            
            sort( $builders );
            sort( $categories );
            sort( $types );
            sort( $conditions );
            
            // Note: Don't limit vessels here - client-side pagination will handle display
            // All vessels are needed for filtering and pagination to work correctly
            
            // Clear memory (free up large arrays)
            if ( isset( $meta_by_post ) ) unset( $meta_by_post );
            if ( isset( $all_meta_results ) ) unset( $all_meta_results );
            if ( isset( $titles ) ) unset( $titles );
            if ( isset( $thumbnail_ids ) ) unset( $thumbnail_ids );
            if ( isset( $thumbnail_results ) ) unset( $thumbnail_results );
            
            // Generate HTML output
            $vessels_html = yatco_generate_vessels_html_from_data( $vessels, $builders, $categories, $types, $conditions, $atts );
            
            // If warming, show message with partial results
            if ( $is_warming && ! empty( $vessels ) ) {
                $status_msg = esc_html( $cache_status ? $cache_status : 'Vessels are being imported to CPT...' );
                return '<div class="yatco-cache-warming-notice" style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;"><strong>Note:</strong> ' . $status_msg . ' Showing ' . count( $vessels ) . ' vessels from CPT. More will appear as import completes.</div>' . $vessels_html;
            }
            
            // For initial load, add data attributes and script to load remaining vessels in background
            // Always use lazy loading to prevent timeouts, even when URL params are present
            if ( $initial_load && $total_vessels > 12 ) {
                // Add data attributes to container for JavaScript
                // Match the opening div tag (may have existing data attributes)
                $vessels_html = preg_replace(
                    '/(<div class="yatco-vessels-container"[^>]*)(>)/',
                    '$1 data-yatco-total="' . esc_attr( $total_vessels ) . '" data-yatco-loaded="' . esc_attr( count( $vessels ) ) . '"$2',
                    $vessels_html,
                    1 // Only replace first occurrence
                );
                
                // Add inline script to load remaining vessels in background (after page load)
                $vessels_html .= '<script type="text/javascript">
                (function() {
                    if (typeof jQuery === "undefined") {
                        console.log("[YATCO AJAX] jQuery not available, skipping AJAX load");
                        return;
                    }
                    jQuery(document).ready(function($) {
                        console.log("[YATCO AJAX] Script loaded, looking for container...");
                        var container = $(".yatco-vessels-container[data-yatco-total]");
                        if (container.length === 0) {
                            console.log("[YATCO AJAX] Container with data-yatco-total not found, skipping AJAX load");
                            return;
                        }
                        console.log("[YATCO AJAX] Container found with data-yatco-total");
                        
                        var totalVessels = parseInt(container.attr("data-yatco-total")) || 0;
                        var loadedVessels = parseInt(container.attr("data-yatco-loaded")) || 0;
                        console.log("[YATCO AJAX] Total vessels:", totalVessels, ", Loaded vessels:", loadedVessels);
                        
                        if (totalVessels <= loadedVessels) {
                            console.log("[YATCO AJAX] All vessels already loaded, skipping");
                            return; // All vessels already loaded
                        }
                        
                        // Load remaining vessels in background after page load (1 second delay)
                        console.log("[YATCO AJAX] Scheduling AJAX call in 1 second...");
                        setTimeout(function() {
                            console.log("[YATCO AJAX] Making AJAX call to load remaining vessels...");
                            // Get current filter values from URL params (if any)
                            var urlParams = new URLSearchParams(window.location.search);
                            $.ajax({
                                url: ' . json_encode( admin_url( 'admin-ajax.php' ) ) . ',
                                type: "POST",
                                data: {
                                    action: "yatco_load_all_vessels",
                                    nonce: ' . json_encode( wp_create_nonce( 'yatco_load_vessels' ) ) . ',
                                    price_min: urlParams.get("price_min") || ' . json_encode( $price_min ) . ',
                                    price_max: urlParams.get("price_max") || ' . json_encode( $price_max ) . ',
                                    year_min: urlParams.get("year_min") || ' . json_encode( $year_min ) . ',
                                    year_max: urlParams.get("year_max") || ' . json_encode( $year_max ) . ',
                                    loa_min: urlParams.get("loa_min") || ' . json_encode( $loa_min ) . ',
                                    loa_max: urlParams.get("loa_max") || ' . json_encode( $loa_max ) . ',
                                    builder: urlParams.get("builder") || "",
                                    category: urlParams.get("category") || "",
                                    type: urlParams.get("type") || "",
                                    condition: urlParams.get("condition") || "",
                                    keywords: urlParams.get("keywords") || "",
                                    cabins: urlParams.get("cabins") || ""
                                },
                                success: function(response) {
                                    console.log("[YATCO AJAX] Success response received");
                                    if (response && response.success && response.data && response.data.html) {
                                        // Trigger event immediately with total count (before appending, so count/pagination update first)
                                        var event = new CustomEvent("yatco:vessels-loaded", {
                                            detail: { count: response.data.total_count || 0 }
                                        });
                                        document.dispatchEvent(event);
                                        
                                        // Append vessels in batches to avoid blocking the page
                                        var grid = container.find("#yatco-vessels-grid");
                                        if (grid.length) {
                                            // Parse HTML once
                                            var tempDiv = $("<div>").html(response.data.html);
                                            var vesselCards = tempDiv.find(".yatco-vessel-card");
                                            var totalVessels = vesselCards.length;
                                            var batchSize = 100; // Append 100 vessels at a time
                                            var currentIndex = 0;
                                            
                                            console.log("[YATCO AJAX] Appending", totalVessels, "vessels in batches of", batchSize);
                                            
                                            function appendBatch() {
                                                var endIndex = Math.min(currentIndex + batchSize, totalVessels);
                                                var batch = vesselCards.slice(currentIndex, endIndex);
                                                
                                                if (batch.length > 0) {
                                                    // Filter out duplicates by checking if vessel with same href already exists
                                                    var existingLinks = {};
                                                    grid.find(".yatco-vessel-card").each(function() {
                                                        var href = $(this).attr("href");
                                                        if (href) existingLinks[href] = true;
                                                    });
                                                    
                                                    var filteredBatch = batch.filter(function() {
                                                        var href = $(this).attr("href");
                                                        if (!href || existingLinks[href]) {
                                                            return false; // Skip duplicate
                                                        }
                                                        existingLinks[href] = true; // Mark as seen
                                                        return true;
                                                    });
                                                    
                                                    if (filteredBatch.length > 0) {
                                                        // Append only non-duplicate vessels
                                                        grid.append(filteredBatch);
                                                    }
                                                    
                                                    currentIndex = endIndex;
                                                } else {
                                                    // Batch is empty, move to next
                                                    currentIndex = endIndex;
                                                }
                                                
                                                // Check if we are done (regardless of whether batch was empty)
                                                if (currentIndex >= totalVessels) {
                                                    console.log("[YATCO AJAX] Finished appending all vessels");
                                                    // All vessels appended - trigger a final refresh event
                                                    var completeEvent = new CustomEvent("yatco:vessels-append-complete", {
                                                        detail: { count: response.data.total_count || 0 }
                                                    });
                                                    document.dispatchEvent(completeEvent);
                                                } else {
                                                    // Continue with next batch
                                                    requestAnimationFrame(appendBatch);
                                                }
                                            }
                                            
                                            // Start appending in batches (defer slightly to let count/pagination render first)
                                            setTimeout(function() {
                                                requestAnimationFrame(appendBatch);
                                            }, 100);
                                        } else {
                                            console.log("[YATCO AJAX] Grid not found, dispatching complete event anyway");
                                            // Grid not found, but still dispatch event to clear loading flag
                                            var completeEvent = new CustomEvent("yatco:vessels-append-complete", {
                                                detail: { count: response.data.total_count || 0 }
                                            });
                                            document.dispatchEvent(completeEvent);
                                        }
                                    } else {
                                        console.log("[YATCO AJAX] No HTML in response, dispatching complete event anyway");
                                        // No HTML to append, but still dispatch event to clear loading flag
                                        var completeEvent = new CustomEvent("yatco:vessels-append-complete", {
                                            detail: { count: 0 }
                                        });
                                        document.dispatchEvent(completeEvent);
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.log("[YATCO AJAX] Error loading vessels:", status, error);
                                    // Dispatch complete event even on error to clear loading flag
                                    var completeEvent = new CustomEvent("yatco:vessels-append-complete", {
                                        detail: { count: 0 }
                                    });
                                    document.dispatchEvent(completeEvent);
                                }
                            });
                        }, 1000);
                    });
                })();
                </script>';
            }
            
            // Return results from CPT
            return $vessels_html;
        } else {
            // No CPT posts yet - check if warming or show fallback message
            if ( $is_warming ) {
                $status_msg = esc_html( $cache_status ? $cache_status : 'Vessels are being imported to CPT in the background' );
                return '<div class="yatco-cache-warming-notice" style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin: 20px 0;"><p><strong>CPT Import in Progress</strong></p><p>' . $status_msg . '</p><p>Vessels will appear once the import is complete. This may take several minutes for 7000+ vessels.</p><p><em>Tip: You can temporarily use <code>cache="no"</code> in the shortcode to load vessels directly from the API while import is running.</em></p></div>';
            }
            
            // No CPT posts and not warming - fall through to API fallback
        }
    }
    
    // FALLBACK: If cache is disabled or no CPT posts exist, fetch from API

    // Respect the max parameter - limit how many vessel IDs to fetch
    $max_vessels = ! empty( $atts['max'] ) && $atts['max'] !== '0' ? intval( $atts['max'] ) : 50;
    // Fetch more IDs than needed to account for filtering (3x the desired results, but cap at reasonable limit)
    $ids_to_fetch = min( $max_vessels * 3, 500 ); // Cap at 500 to prevent timeouts
    
    $ids = yatco_get_active_vessel_ids( $token, $ids_to_fetch );

    if ( is_wp_error( $ids ) ) {
        return '<p>Error loading vessels: ' . esc_html( $ids->get_error_message() ) . '</p>';
    }

    if ( empty( $ids ) ) {
        return '<p>No vessels available.</p>';
    }

    $vessels = array();
    
    // Parse filter criteria.
    $price_min = ! empty( $atts['price_min'] ) && $atts['price_min'] !== '0' ? floatval( $atts['price_min'] ) : '';
    $price_max = ! empty( $atts['price_max'] ) && $atts['price_max'] !== '0' ? floatval( $atts['price_max'] ) : '';
    $year_min  = ! empty( $atts['year_min'] ) && $atts['year_min'] !== '0' ? intval( $atts['year_min'] ) : '';
    $year_max  = ! empty( $atts['year_max'] ) && $atts['year_max'] !== '0' ? intval( $atts['year_max'] ) : '';
    $loa_min   = ! empty( $atts['loa_min'] ) && $atts['loa_min'] !== '0' ? floatval( $atts['loa_min'] ) : '';
    $loa_max   = ! empty( $atts['loa_max'] ) && $atts['loa_max'] !== '0' ? floatval( $atts['loa_max'] ) : '';

    // Process ALL vessel IDs to make all 7000+ vessels searchable/filterable
    // Note: For large datasets (7000+), this may take time. We use batch processing to prevent timeouts.
    $vessel_count = count( $ids );
    $processed = 0;
    $error_count = 0;
    
    // Increase limits to handle processing
    @ini_set( 'max_execution_time', 300 ); // 5 minutes max
    @ini_set( 'memory_limit', '256M' ); // Reasonable memory limit
    @set_time_limit( 300 ); // 5 minutes
    
    // Start fresh - no progress caching when cache="no"
    $vessels = array();
    $processed = 0;
    $error_count = 0;
    
    // Process vessels up to max limit
    foreach ( $ids as $index => $id ) {
        // Stop if we've reached the max limit
        if ( count( $vessels ) >= $max_vessels ) {
            break;
        }
        
        $processed++;
        
        // Reset execution time periodically
        if ( $processed % 10 === 0 ) {
            @set_time_limit( 300 );
        }

        $full = yatco_fetch_fullspecs( $token, $id );
        if ( is_wp_error( $full ) ) {
            continue;
        }

        $brief = yatco_build_brief_from_fullspecs( $id, $full );

        // Apply filtering.
        $price = ! empty( $brief['Price'] ) ? floatval( $brief['Price'] ) : null;
        $year  = ! empty( $brief['Year'] ) ? intval( $brief['Year'] ) : null;
        $loa_raw = $brief['LOA'];
        if ( is_string( $loa_raw ) && preg_match( '/([0-9.]+)/', $loa_raw, $matches ) ) {
            $loa = floatval( $matches[1] );
        } elseif ( ! empty( $loa_raw ) && is_numeric( $loa_raw ) ) {
            $loa = floatval( $loa_raw );
        } else {
            $loa = null;
        }

        // Apply filters.
        if ( $price_min !== '' && ( is_null( $price ) || $price <= 0 || $price < $price_min ) ) {
            continue;
        }
        if ( $price_max !== '' && ( is_null( $price ) || $price <= 0 || $price > $price_max ) ) {
            continue;
        }
        if ( $year_min !== '' && ( is_null( $year ) || $year <= 0 || $year < $year_min ) ) {
            continue;
        }
        if ( $year_max !== '' && ( is_null( $year ) || $year <= 0 || $year > $year_max ) ) {
            continue;
        }
        if ( $loa_min !== '' && ( is_null( $loa ) || $loa <= 0 || $loa < $loa_min ) ) {
            continue;
        }
        if ( $loa_max !== '' && ( is_null( $loa ) || $loa <= 0 || $loa > $loa_max ) ) {
            continue;
        }

        // Get full specs for display.
        $result = isset( $full['Result'] ) ? $full['Result'] : array();
        $basic  = isset( $full['BasicInfo'] ) ? $full['BasicInfo'] : array();
        
        // Get builder, category, type, condition
        $builder = isset( $basic['Builder'] ) ? $basic['Builder'] : ( isset( $result['BuilderName'] ) ? $result['BuilderName'] : '' );
        $category = isset( $basic['MainCategory'] ) ? $basic['MainCategory'] : ( isset( $result['MainCategoryText'] ) ? $result['MainCategoryText'] : '' );
        $type = isset( $basic['VesselTypeText'] ) ? $basic['VesselTypeText'] : ( isset( $result['VesselTypeText'] ) ? $result['VesselTypeText'] : '' );
        $condition = isset( $result['VesselCondition'] ) ? $result['VesselCondition'] : '';
        $state_rooms = isset( $basic['StateRooms'] ) ? intval( $basic['StateRooms'] ) : ( isset( $result['StateRooms'] ) ? intval( $result['StateRooms'] ) : 0 );
        $location = isset( $basic['LocationCustom'] ) ? $basic['LocationCustom'] : '';
        
        // Get LOA in feet and meters
        $loa_feet = isset( $result['LOAFeet'] ) && $result['LOAFeet'] > 0 ? floatval( $result['LOAFeet'] ) : null;
        $loa_meters = isset( $result['LOAMeters'] ) && $result['LOAMeters'] > 0 ? floatval( $result['LOAMeters'] ) : null;
        if ( ! $loa_meters && $loa_feet ) {
            $loa_meters = $loa_feet * 0.3048;
        }
        
        // Get price in USD and EUR
        $price_usd = isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ? floatval( $basic['AskingPriceUSD'] ) : null;
        if ( ! $price_usd && isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
            $price_usd = floatval( $result['AskingPriceCompare'] );
        }
        
        $price_eur = isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 && isset( $basic['Currency'] ) && $basic['Currency'] === 'EUR' ? floatval( $basic['AskingPrice'] ) : null;
        
        // Try to find existing CPT post for this vessel
        $post_id = 0;
        $mlsid = isset( $result['MLSID'] ) ? $result['MLSID'] : '';
        
        // Try matching by MLSID first
        if ( ! empty( $mlsid ) ) {
            $existing = get_posts(
                array(
                    'post_type'   => 'yacht',
                    'meta_key'    => 'yacht_mlsid',
                    'meta_value'  => $mlsid,
                    'numberposts' => 1,
                    'fields'      => 'ids',
                )
            );
            if ( ! empty( $existing ) ) {
                $post_id = (int) $existing[0];
            }
        }
        
        // Fallback: Try matching by VesselID
        if ( ! $post_id ) {
            $existing = get_posts(
                array(
                    'post_type'   => 'yacht',
                    'meta_key'    => 'yacht_vessel_id',
                    'meta_value'  => $id,
                    'numberposts' => 1,
                    'fields'      => 'ids',
                )
            );
            if ( ! empty( $existing ) ) {
                $post_id = (int) $existing[0];
            }
        }
        
        // Use CPT permalink if post exists, otherwise fallback to archive link
        $vessel_link = '';
        if ( $post_id ) {
            $vessel_link = get_permalink( $post_id );
        } else {
            $vessel_link = get_post_type_archive_link( 'yacht' ) . '?vessel_id=' . $id;
        }
        
        $vessel_data = array(
            'id'          => $id,
            'post_id'     => $post_id,
            'name'        => $brief['Name'],
            'price'       => $brief['Price'],
            'price_usd'   => $price_usd,
            'price_eur'   => $price_eur,
            'year'        => $brief['Year'],
            'loa'         => $brief['LOA'],
            'loa_feet'    => $loa_feet,
            'loa_meters'  => $loa_meters,
            'builder'     => $builder,
            'category'    => $category,
            'type'        => $type,
            'condition'   => $condition,
            'state_rooms' => $state_rooms,
            'location'    => $location,
            'image'       => isset( $result['MainPhotoUrl'] ) ? $result['MainPhotoUrl'] : ( isset( $basic['MainPhotoURL'] ) ? $basic['MainPhotoURL'] : '' ),
            'link'        => $vessel_link,
        );

        $vessels[] = $vessel_data;
        
        // Stop if we've reached the max limit
        if ( count( $vessels ) >= $max_vessels ) {
            break;
        }
    }

    // Collect unique values for filter dropdowns
    $builders = array();
    $categories = array();
    $types = array();
    $conditions = array();
    
    foreach ( $vessels as $vessel ) {
        if ( ! empty( $vessel['builder'] ) && ! in_array( $vessel['builder'], $builders ) ) {
            $builders[] = $vessel['builder'];
        }
        if ( ! empty( $vessel['category'] ) && ! in_array( $vessel['category'], $categories ) ) {
            $categories[] = $vessel['category'];
        }
        if ( ! empty( $vessel['type'] ) && ! in_array( $vessel['type'], $types ) ) {
            $types[] = $vessel['type'];
        }
        if ( ! empty( $vessel['condition'] ) && ! in_array( $vessel['condition'], $conditions ) ) {
            $conditions[] = $vessel['condition'];
        }
    }
    sort( $builders );
    sort( $categories );
    sort( $types );
    sort( $conditions );

    // Generate HTML output
    $output = yatco_generate_vessels_html_from_data( $vessels, $builders, $categories, $types, $conditions, $atts );

    // Cache the output if enabled.
    if ( $atts['cache'] === 'yes' ) {
        $options = get_option( 'yatco_api_settings' );
        $cache_duration = isset( $options['yatco_cache_duration'] ) ? intval( $options['yatco_cache_duration'] ) : 30;
        
        set_transient( $cache_key, $output, $cache_duration * MINUTE_IN_SECONDS );
        
        // Also cache vessel data separately for faster future loads
        set_transient( 'yatco_vessels_data', $vessels, $cache_duration * MINUTE_IN_SECONDS );
        set_transient( 'yatco_vessels_builders', $builders, $cache_duration * MINUTE_IN_SECONDS );
        set_transient( 'yatco_vessels_categories', $categories, $cache_duration * MINUTE_IN_SECONDS );
        set_transient( 'yatco_vessels_types', $types, $cache_duration * MINUTE_IN_SECONDS );
        set_transient( 'yatco_vessels_conditions', $conditions, $cache_duration * MINUTE_IN_SECONDS );
    }

    return $output;
}

/**
 * API-Only Mode shortcode handler.
 * Fetches vessels directly from API without using CPT.
 * 
 * @param array  $atts Shortcode attributes
 * @param string $token API token
 * @return string HTML output
 */
function yatco_vessels_shortcode_api_only( $atts, $token ) {
    // Check if API-only functions are available
    if ( ! function_exists( 'yatco_api_only_get_vessel_ids' ) || ! function_exists( 'yatco_api_only_get_vessel_data' ) ) {
        return '<p>API-only mode functions are not available. Please ensure yatco-api-only.php is loaded.</p>';
    }

    // Get max vessels to display
    $max_vessels = ! empty( $atts['max'] ) && $atts['max'] !== '0' ? intval( $atts['max'] ) : 50;
    
    // Parse filter criteria
    $price_min = ! empty( $atts['price_min'] ) && $atts['price_min'] !== '0' ? floatval( $atts['price_min'] ) : '';
    $price_max = ! empty( $atts['price_max'] ) && $atts['price_max'] !== '0' ? floatval( $atts['price_max'] ) : '';
    $year_min  = ! empty( $atts['year_min'] ) && $atts['year_min'] !== '0' ? intval( $atts['year_min'] ) : '';
    $year_max  = ! empty( $atts['year_max'] ) && $atts['year_max'] !== '0' ? intval( $atts['year_max'] ) : '';
    $loa_min   = ! empty( $atts['loa_min'] ) && $atts['loa_min'] !== '0' ? floatval( $atts['loa_min'] ) : '';
    $loa_max   = ! empty( $atts['loa_max'] ) && $atts['loa_max'] !== '0' ? floatval( $atts['loa_max'] ) : '';
    
    // Check if JSON cache is enabled and available
    $options = get_option( 'yatco_api_settings', array() );
    $json_cache_enabled = isset( $options['yatco_api_only_json_cache'] ) ? $options['yatco_api_only_json_cache'] : 'yes'; // Default to enabled
    $use_json_cache = ( $json_cache_enabled === 'yes' ) && function_exists( 'yatco_json_cache_get_vessels' );
    
    if ( $use_json_cache ) {
        // Try to get vessels from JSON cache first (fast!)
        $filters = array(
            'price_min' => $price_min,
            'price_max' => $price_max,
            'year_min' => $year_min,
            'year_max' => $year_max,
            'loa_min' => $loa_min,
            'loa_max' => $loa_max,
        );
        
        $vessels = yatco_json_cache_get_vessels( $filters, $max_vessels );
        $filter_values = yatco_json_cache_get_filter_values();
        
        // If we got results from JSON cache, use them
        if ( ! empty( $vessels ) ) {
            return yatco_generate_vessels_html_from_data( 
                $vessels, 
                $filter_values['builders'], 
                $filter_values['categories'], 
                $filter_values['types'], 
                $filter_values['conditions'], 
                $atts 
            );
        }
        
        // If JSON cache is empty, fall through to API fetching
        // (This will also populate JSON cache for next time)
    }
    
    // Fallback: Fetch from API (slower, but works if lightweight storage is empty)
    // Set execution limits to prevent timeouts
    @ini_set( 'max_execution_time', 60 ); // 1 minute max
    @set_time_limit( 60 );
    
    // Get all active vessel IDs (cached)
    $vessel_ids = yatco_api_only_get_vessel_ids( $token );
    
    if ( is_wp_error( $vessel_ids ) ) {
        return '<p>Error loading vessels: ' . esc_html( $vessel_ids->get_error_message() ) . '</p>';
    }
    
    if ( empty( $vessel_ids ) ) {
        return '<p>No vessels available.</p>';
    }
    
    // CRITICAL: Limit how many vessel IDs we process to prevent timeouts
    // Process up to 3x the max_vessels, but cap at 200 to prevent timeouts
    // This means if user wants 50 vessels, we'll check up to 150 IDs (or 200 max)
    $ids_to_check = min( $max_vessels * 3, 200 );
    $vessel_ids = array_slice( $vessel_ids, 0, $ids_to_check );
    
    $vessels = array();
    $processed = 0;
    $error_count = 0;
    $start_time = time();
    $max_processing_time = 50; // Stop after 50 seconds to prevent timeout
    
    // Process vessels up to max limit
    foreach ( $vessel_ids as $vessel_id ) {
        // Check timeout
        if ( ( time() - $start_time ) > $max_processing_time ) {
            break;
        }
        
        // Stop if we've reached the max limit
        if ( count( $vessels ) >= $max_vessels ) {
            break;
        }
        
        $processed++;
        
        // Reset execution time periodically
        if ( $processed % 10 === 0 ) {
            @set_time_limit( 60 );
        }
        
        // Get vessel data (cached)
        $vessel_data = yatco_api_only_get_vessel_data( $token, $vessel_id );
        
        if ( is_wp_error( $vessel_data ) ) {
            $error_count++;
            continue;
        }
        
        // Apply filters
        if ( $price_min !== '' && ( empty( $vessel_data['price_usd'] ) || $vessel_data['price_usd'] < $price_min ) ) {
            continue;
        }
        if ( $price_max !== '' && ( empty( $vessel_data['price_usd'] ) || $vessel_data['price_usd'] > $price_max ) ) {
            continue;
        }
        if ( $year_min !== '' && ( empty( $vessel_data['year'] ) || intval( $vessel_data['year'] ) < $year_min ) ) {
            continue;
        }
        if ( $year_max !== '' && ( empty( $vessel_data['year'] ) || intval( $vessel_data['year'] ) > $year_max ) ) {
            continue;
        }
        if ( $loa_min !== '' && ( empty( $vessel_data['loa_feet'] ) || $vessel_data['loa_feet'] < $loa_min ) ) {
            continue;
        }
        if ( $loa_max !== '' && ( empty( $vessel_data['loa_feet'] ) || $vessel_data['loa_feet'] > $loa_max ) ) {
            continue;
        }
        
        // Build vessel data array for display (compatible with existing HTML generator)
        $vessel_display = array(
            'id'          => $vessel_data['vessel_id'],
            'post_id'     => 0, // No post ID in API-only mode
            'name'        => $vessel_data['name'],
            'price'       => $vessel_data['price_formatted'] ? $vessel_data['price_formatted'] : ( $vessel_data['price_usd'] ? '$' . number_format( $vessel_data['price_usd'] ) : '' ),
            'price_usd'   => $vessel_data['price_usd'],
            'price_eur'   => $vessel_data['price_eur'],
            'year'        => $vessel_data['year'],
            'loa'         => $vessel_data['loa_feet'] ? $vessel_data['loa_feet'] . ' ft' : '',
            'loa_feet'    => $vessel_data['loa_feet'],
            'loa_meters'  => $vessel_data['loa_meters'],
            'builder'     => $vessel_data['builder'],
            'category'    => $vessel_data['category'],
            'type'        => $vessel_data['type'],
            'condition'   => $vessel_data['condition'],
            'state_rooms' => $vessel_data['state_rooms'],
            'location'    => $vessel_data['location'],
            'image'       => $vessel_data['image_url'],
            'link'        => $vessel_data['yatco_listing_url'], // Use YATCO listing URL directly
        );
        
        $vessels[] = $vessel_display;
        
        // Store in JSON cache if available (for next time)
        if ( $use_json_cache && function_exists( 'yatco_json_cache_store_vessel' ) ) {
            yatco_json_cache_store_vessel( $vessel_data );
        }
    }
    
    // Collect unique values for filter dropdowns
    $builders = array();
    $categories = array();
    $types = array();
    $conditions = array();
    
    foreach ( $vessels as $vessel ) {
        if ( ! empty( $vessel['builder'] ) && ! in_array( $vessel['builder'], $builders ) ) {
            $builders[] = $vessel['builder'];
        }
        if ( ! empty( $vessel['category'] ) && ! in_array( $vessel['category'], $categories ) ) {
            $categories[] = $vessel['category'];
        }
        if ( ! empty( $vessel['type'] ) && ! in_array( $vessel['type'], $types ) ) {
            $types[] = $vessel['type'];
        }
        if ( ! empty( $vessel['condition'] ) && ! in_array( $vessel['condition'], $conditions ) ) {
            $conditions[] = $vessel['condition'];
        }
    }
    sort( $builders );
    sort( $categories );
    sort( $types );
    sort( $conditions );
    
    // Show warning if we hit processing limits
    $warning_message = '';
    if ( count( $vessels ) < $max_vessels && $processed >= $ids_to_check ) {
        $warning_message = '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">';
        $warning_message .= '<p style="margin: 0;"><strong>Note:</strong> API-only mode processed ' . number_format( $processed ) . ' vessels to find ' . count( $vessels ) . ' matching results. ';
        $warning_message .= 'To see more results, consider using CPT mode or adjusting your filters.</p>';
        $warning_message .= '</div>';
    } elseif ( ( time() - $start_time ) > $max_processing_time && count( $vessels ) < $max_vessels ) {
        $warning_message = '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">';
        $warning_message .= '<p style="margin: 0;"><strong>Note:</strong> Processing stopped after ' . $max_processing_time . ' seconds to prevent timeout. ';
        $warning_message .= 'Found ' . count( $vessels ) . ' matching vessels. Consider using CPT mode for better performance with large datasets.</p>';
        $warning_message .= '</div>';
    }
    
    // Generate HTML output using existing function
    $output = yatco_generate_vessels_html_from_data( $vessels, $builders, $categories, $types, $conditions, $atts );
    
    return $warning_message . $output;
}