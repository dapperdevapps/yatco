<?php
/*
Plugin Name: YATCO Custom Integration (Rebuilt)
Description: Imports YATCO vessels into WordPress and maps core fields.
Version: 1.0.0
Author: ChatGPT
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YATCO_Custom_Integration_Rebuilt {

    const OPTION_TOKEN = 'yatco_api_token_basic';
    const LOG_FILE     = 'yatco-api.log';
    const API_BASE     = 'https://api.yatcoboss.com/api/v1/';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_yatco_test_connection', array( $this, 'handle_test_connection' ) );
        add_action( 'admin_post_yatco_preview_listings', array( $this, 'handle_preview_listings' ) );
        add_action( 'admin_post_yatco_import_selected', array( $this, 'handle_import_selected' ) );
    }

    public static function log( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $upload_dir = wp_upload_dir();
            $log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'yatco-logs';
            if ( ! file_exists( $log_dir ) ) {
                wp_mkdir_p( $log_dir );
            }
            $file = trailingslashit( $log_dir ) . self::LOG_FILE;
            $timestamp = date( 'Y-m-d H:i:s' );
            $line = '[' . $timestamp . '] ' . $message;
            if ( ! empty( $context ) ) {
                $line .= ' ' . wp_json_encode( $context );
            }
            $line .= PHP_EOL;
            @file_put_contents( $file, $line, FILE_APPEND );
        }
    }

    public function register_admin_menu() {
        add_options_page(
            'YATCO API Settings',
            'YATCO API',
            'manage_options',
            'yatco-api-settings',
            array( $this, 'render_settings_page' )
        );

        add_menu_page(
            'YATCO Import',
            'YATCO Import',
            'manage_options',
            'yatco-import',
            array( $this, 'render_import_page' ),
            'dashicons-download',
            58
        );
    }

    public function register_settings() {
        register_setting( 'yatco_api_settings_group', self::OPTION_TOKEN );

        add_settings_section(
            'yatco_api_main_section',
            'YATCO API Credentials',
            '__return_false',
            'yatco-api-settings'
        );

        add_settings_field(
            self::OPTION_TOKEN,
            'API Token (Basic)',
            array( $this, 'render_token_field' ),
            'yatco-api-settings',
            'yatco_api_main_section'
        );
    }

    public function render_token_field() {
        $token = esc_attr( get_option( self::OPTION_TOKEN, '' ) );
        echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_TOKEN ) . '" value="' . $token . '" />';
        echo '<p class="description">Paste the Basic token exactly as provided by YATCO (do not re-encode).</p>';
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>YATCO API Settings</h1>
            <?php if ( isset( $_GET['yatco_test'] ) ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html( wp_unslash( $_GET['yatco_test'] ) ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'yatco_api_settings_group' );
                do_settings_sections( 'yatco-api-settings' );
                submit_button( 'Save Changes' );
                ?>
            </form>

            <hr />

            <h2>Test API Connection</h2>
            <p>This test calls the <code>ForSale/Vessel/Active</code> endpoint to confirm your token is valid.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'yatco_test_connection', 'yatco_test_nonce' ); ?>
                <input type="hidden" name="action" value="yatco_test_connection" />
                <?php submit_button( 'Test Connection', 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    protected function get_token() {
        $token = get_option( self::OPTION_TOKEN, '' );
        return trim( $token );
    }

    protected function api_get( $path, $args = array() ) {
        $token = $this->get_token();
        if ( empty( $token ) ) {
            return new WP_Error( 'yatco_no_token', 'YATCO API token is not set.' );
        }

        $url = trailingslashit( self::API_BASE ) . ltrim( $path, '/' );
        if ( ! empty( $args ) ) {
            $url = add_query_arg( $args, $url );
        }

        $headers = array(
            'Authorization' => 'Basic ' . $token,
            'Accept'        => 'application/json',
        );

        self::log( 'GET ' . $url );

        $response = wp_remote_get( $url, array(
            'headers' => $headers,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            self::log( 'API error', array( 'error' => $response->get_error_message() ) );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        self::log( 'API response', array( 'code' => $code, 'body_snippet' => substr( $body, 0, 300 ) ) );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'yatco_http_error', 'Unexpected HTTP status: ' . $code, array( 'body' => $body ) );
        }

        $data = json_decode( $body, true );
        if ( null === $data && json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'yatco_json_error', 'JSON decode error: ' . json_last_error_msg() );
        }

        return $data;
    }

    public function handle_test_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'yatco_test_connection', 'yatco_test_nonce' );

        $result = $this->api_get( 'ForSale/Vessel/Active' );
        $msg    = '';

        if ( is_wp_error( $result ) ) {
            $msg = 'Error: ' . $result->get_error_message();
        } else {
            if ( is_array( $result ) ) {
                $count = isset( $result['Result'] ) && is_array( $result['Result'] ) ? count( $result['Result'] ) : count( $result );
                $msg   = 'Success! API responded with ' . $count . ' active vessel IDs.';
            } else {
                $msg = 'Success! API responded.';
            }
        }

        $url = add_query_arg(
            array(
                'page'       => 'yatco-api-settings',
                'yatco_test' => rawurlencode( $msg ),
            ),
            admin_url( 'options-general.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    public function render_import_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $price_min  = isset( $_POST['price_min'] ) ? floatval( wp_unslash( $_POST['price_min'] ) ) : '';
        $price_max  = isset( $_POST['price_max'] ) ? floatval( wp_unslash( $_POST['price_max'] ) ) : '';
        $loa_min    = isset( $_POST['loa_min'] ) ? floatval( wp_unslash( $_POST['loa_min'] ) ) : '';
        $loa_max    = isset( $_POST['loa_max'] ) ? floatval( wp_unslash( $_POST['loa_max'] ) ) : '';
        $year_min   = isset( $_POST['year_min'] ) ? intval( wp_unslash( $_POST['year_min'] ) ) : '';
        $year_max   = isset( $_POST['year_max'] ) ? intval( wp_unslash( $_POST['year_max'] ) ) : '';
        $max_records = isset( $_POST['max_records'] ) ? intval( wp_unslash( $_POST['max_records'] ) ) : 50;

        if ( $max_records <= 0 ) {
            $max_records = 50;
        }

        $preview_rows = array();
        if ( isset( $_GET['preview'] ) && isset( $_POST['yatco_import_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['yatco_import_nonce'] ), 'yatco_import' ) ) {
            $preview_rows = $this->get_preview_rows( $max_records, $price_min, $price_max, $loa_min, $loa_max, $year_min, $year_max );
        }

        ?>
        <div class="wrap">
            <h1>YATCO Import</h1>

            <?php if ( isset( $_GET['yatco_msg'] ) ) : ?>
                <div class="notice notice-info"><p><?php echo esc_html( wp_unslash( $_GET['yatco_msg'] ) ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( add_query_arg( 'page', 'yatco-import', admin_url( 'admin.php' ) ) ); ?>">
                <?php wp_nonce_field( 'yatco_import', 'yatco_import_nonce' ); ?>
                <input type="hidden" name="action" value="yatco_preview_listings" />
                <h2>Import Criteria</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Price (USD)</th>
                        <td>
                            Min: <input type="number" step="1" name="price_min" value="<?php echo esc_attr( $price_min ); ?>" />
                            Max: <input type="number" step="1" name="price_max" value="<?php echo esc_attr( $price_max ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Length (LOA, feet)</th>
                        <td>
                            Min: <input type="number" step="0.1" name="loa_min" value="<?php echo esc_attr( $loa_min ); ?>" />
                            Max: <input type="number" step="0.1" name="loa_max" value="<?php echo esc_attr( $loa_max ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Year Built</th>
                        <td>
                            Min: <input type="number" step="1" name="year_min" value="<?php echo esc_attr( $year_min ); ?>" />
                            Max: <input type="number" step="1" name="year_max" value="<?php echo esc_attr( $year_max ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Max Records</th>
                        <td>
                            <input type="number" step="1" name="max_records" value="<?php echo esc_attr( $max_records ); ?>" />
                            <span class="description">Maximum number of active vessels to fetch from YATCO before filtering (default 50).</span>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Preview Listings', 'primary', 'preview_listings' ); ?>
            </form>

            <?php if ( ! empty( $preview_rows ) ) : ?>
                <h2>Preview Results</h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'yatco_import', 'yatco_import_nonce' ); ?>
                    <input type="hidden" name="action" value="yatco_import_selected" />
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" onclick="jQuery('.yatco-vessel-checkbox').prop('checked', this.checked);" /></th>
                                <th>Vessel ID</th>
                                <th>MLS ID</th>
                                <th>Name</th>
                                <th>Price (USD)</th>
                                <th>Year</th>
                                <th>LOA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $preview_rows as $row ) : ?>
                                <tr>
                                    <th class="check-column">
                                        <input class="yatco-vessel-checkbox" type="checkbox" name="vessel_ids[]" value="<?php echo esc_attr( $row['vessel_id'] ); ?>" />
                                    </th>
                                    <td><?php echo esc_html( $row['vessel_id'] ); ?></td>
                                    <td><?php echo esc_html( $row['mls_id'] ); ?></td>
                                    <td><?php echo esc_html( $row['name'] ); ?></td>
                                    <td><?php echo esc_html( $row['price_usd'] ); ?></td>
                                    <td><?php echo esc_html( $row['year'] ); ?></td>
                                    <td><?php echo esc_html( $row['loa_text'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php submit_button( 'Import Selected', 'primary', 'import_selected' ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_preview_listings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'yatco_import', 'yatco_import_nonce' );

        // We simply re-render the import page; criteria will be read from POST.
        $url = add_query_arg(
            array(
                'page'    => 'yatco-import',
                'preview' => 1,
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    protected function get_preview_rows( $max_records, $price_min, $price_max, $loa_min, $loa_max, $year_min, $year_max ) {
        $rows = array();

        $data = $this->api_get( 'ForSale/Vessel/Active' );
        if ( is_wp_error( $data ) ) {
            self::log( 'Failed to fetch active vessels', array( 'error' => $data->get_error_message() ) );
            return $rows;
        }

        $ids = array();
        if ( isset( $data['Result'] ) && is_array( $data['Result'] ) ) {
            $ids = $data['Result'];
        } elseif ( is_array( $data ) ) {
            $ids = $data;
        }

        $ids = array_slice( $ids, 0, $max_records );

        foreach ( $ids as $id ) {
            $id = intval( $id );
            if ( $id <= 0 ) {
                continue;
            }

            $details = $this->api_get( 'ForSale/Vessel/' . $id . '/Details/FullSpecsAll' );
            if ( is_wp_error( $details ) ) {
                self::log( 'Failed to fetch FullSpecsAll', array( 'vessel_id' => $id, 'error' => $details->get_error_message() ) );
                continue;
            }

            $mapped = $this->map_vessel_data( $details );
            if ( ! $this->passes_filters( $mapped, $price_min, $price_max, $loa_min, $loa_max, $year_min, $year_max ) ) {
                continue;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    protected function map_vessel_data( $details ) {
        $result   = isset( $details['Result'] ) ? $details['Result'] : array();
        $basic    = isset( $details['BasicInfo'] ) ? $details['BasicInfo'] : array();
        $dims     = isset( $details['Dimensions'] ) ? $details['Dimensions'] : array();

        $vessel_id = isset( $result['VesselID'] ) ? intval( $result['VesselID'] ) : ( isset( $basic['VesselID'] ) ? intval( $basic['VesselID'] ) : 0 );
        $mls_id    = isset( $result['MLSID'] ) ? $result['MLSID'] : ( isset( $basic['MLSID'] ) ? $basic['MLSID'] : '' );

        $name = '';
        if ( ! empty( $result['VesselName'] ) ) {
            $name = $result['VesselName'];
        } elseif ( ! empty( $basic['BoatName'] ) ) {
            $name = $basic['BoatName'];
        } elseif ( ! empty( $basic['Model'] ) ) {
            $name = $basic['Model'];
        } else {
            $name = 'Unnamed Yacht';
        }

        $price_usd = 0;
        if ( isset( $basic['AskingPriceUSD'] ) ) {
            $price_usd = floatval( $basic['AskingPriceUSD'] );
        } elseif ( isset( $result['AskingPriceCompare'] ) ) {
            $price_usd = floatval( $result['AskingPriceCompare'] );
        } elseif ( isset( $result['AskingPrice'] ) && isset( $result['AskingPriceCurrencyText'] ) && $result['AskingPriceCurrencyText'] === 'USD' ) {
            $price_usd = floatval( $result['AskingPrice'] );
        }

        $year = 0;
        if ( isset( $result['ModelYear'] ) && $result['ModelYear'] > 0 ) {
            $year = intval( $result['ModelYear'] );
        } elseif ( isset( $result['YearBuilt'] ) && $result['YearBuilt'] > 0 ) {
            $year = intval( $result['YearBuilt'] );
        } elseif ( isset( $basic['ModelYear'] ) && $basic['ModelYear'] > 0 ) {
            $year = intval( $basic['ModelYear'] );
        } elseif ( isset( $basic['YearBuilt'] ) && $basic['YearBuilt'] > 0 ) {
            $year = intval( $basic['YearBuilt'] );
        }

        $loa_text = '';
        $loa_feet = 0.0;

        if ( ! empty( $dims['LOAFeet'] ) ) {
            $loa_text = $dims['LOAFeet'];
            if ( preg_match( '/([0-9.]+)/', $dims['LOAFeet'], $m ) ) {
                $loa_feet = floatval( $m[1] );
            }
        } elseif ( ! empty( $dims['LOA'] ) ) {
            $loa_text = $dims['LOA'];
            if ( preg_match( '/([0-9.]+)/', $dims['LOA'], $m ) ) {
                $loa_feet = floatval( $m[1] );
            }
        } elseif ( isset( $result['LOAFeet'] ) ) {
            $loa_feet = floatval( $result['LOAFeet'] );
            $loa_text = $loa_feet . ' ft';
        } elseif ( isset( $dims['Length'] ) ) {
            $len = floatval( $dims['Length'] );
            $unit = isset( $dims['LengthUnit'] ) ? intval( $dims['LengthUnit'] ) : 0;
            if ( $unit === 2 ) { // meters
                $loa_feet = $len * 3.28084;
            } else {
                $loa_feet = $len;
            }
            $loa_text = sprintf( '%.1f ft', $loa_feet );
        }

        return array(
            'vessel_id' => $vessel_id,
            'mls_id'    => $mls_id,
            'name'      => $name,
            'price_usd' => $price_usd,
            'year'      => $year,
            'loa_feet'  => $loa_feet,
            'loa_text'  => $loa_text,
        );
    }

    protected function passes_filters( $mapped, $price_min, $price_max, $loa_min, $loa_max, $year_min, $year_max ) {
        $price = floatval( $mapped['price_usd'] );
        $loa   = floatval( $mapped['loa_feet'] );
        $year  = intval( $mapped['year'] );

        if ( $price_min !== '' && $price > 0 && $price < $price_min ) {
            return false;
        }
        if ( $price_max !== '' && $price > 0 && $price > $price_max ) {
            return false;
        }
        if ( $loa_min !== '' && $loa > 0 && $loa < $loa_min ) {
            return false;
        }
        if ( $loa_max !== '' && $loa > 0 && $loa > $loa_max ) {
            return false;
        }
        if ( $year_min !== '' && $year > 0 && $year < $year_min ) {
            return false;
        }
        if ( $year_max !== '' && $year > 0 && $year > $year_max ) {
            return false;
        }

        return true;
    }

    public function handle_import_selected() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'yatco_import', 'yatco_import_nonce' );

        $ids = isset( $_POST['vessel_ids'] ) ? (array) $_POST['vessel_ids'] : array();
        $ids = array_map( 'intval', $ids );
        $ids = array_filter( $ids );

        if ( empty( $ids ) ) {
            $url = add_query_arg(
                array(
                    'page'      => 'yatco-import',
                    'yatco_msg' => rawurlencode( 'No vessels selected for import.' ),
                ),
                admin_url( 'admin.php' )
            );
            wp_safe_redirect( $url );
            exit;
        }

        $imported = 0;

        foreach ( $ids as $id ) {
            $details = $this->api_get( 'ForSale/Vessel/' . $id . '/Details/FullSpecsAll' );
            if ( is_wp_error( $details ) ) {
                self::log( 'Failed to fetch FullSpecsAll during import', array( 'vessel_id' => $id, 'error' => $details->get_error_message() ) );
                continue;
            }

            $mapped = $this->map_vessel_data( $details );

            $post_id = wp_insert_post( array(
                'post_type'   => 'post',
                'post_status' => 'publish',
                'post_title'  => $mapped['name'],
                'post_content'=> $this->extract_description_from_details( $details ),
            ), true );

            if ( is_wp_error( $post_id ) ) {
                self::log( 'Failed to create post', array( 'vessel_id' => $id, 'error' => $post_id->get_error_message() ) );
                continue;
            }

            update_post_meta( $post_id, 'yatco_vessel_id', $mapped['vessel_id'] );
            update_post_meta( $post_id, 'yatco_mls_id', $mapped['mls_id'] );
            update_post_meta( $post_id, 'yatco_price_usd', $mapped['price_usd'] );
            update_post_meta( $post_id, 'yatco_year', $mapped['year'] );
            update_post_meta( $post_id, 'yatco_loa_feet', $mapped['loa_feet'] );
            update_post_meta( $post_id, 'yatco_loa_text', $mapped['loa_text'] );

            $imported++;
        }

        $msg = sprintf( 'Imported %d vessel(s).', $imported );
        $url = add_query_arg(
            array(
                'page'      => 'yatco-import',
                'yatco_msg' => rawurlencode( $msg ),
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    protected function extract_description_from_details( $details ) {
        if ( isset( $details['VD']['VesselDescriptionShortDescriptionNoStyles'] ) && ! empty( $details['VD']['VesselDescriptionShortDescriptionNoStyles'] ) ) {
            return $details['VD']['VesselDescriptionShortDescriptionNoStyles'];
        }
        if ( isset( $details['MiscInfo']['VesselDescriptionShortDescription'] ) && ! empty( $details['MiscInfo']['VesselDescriptionShortDescription'] ) ) {
            return $details['MiscInfo']['VesselDescriptionShortDescription'];
        }
        if ( isset( $details['VD']['VesselDescriptionShortDescription'] ) && ! empty( $details['VD']['VesselDescriptionShortDescription'] ) ) {
            return $details['VD']['VesselDescriptionShortDescription'];
        }
        return '';
    }
}

new YATCO_Custom_Integration_Rebuilt();
