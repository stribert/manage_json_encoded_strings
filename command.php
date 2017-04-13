<?php

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

/**
 * Manage json encoded strings in the database
 *
 * ## EXAMPLES
 *
 * # Find a string
 * $ wp mjson search "http://example.com"
 *
 * # Replace a string
 * $ wp mjson replace "http://example1.com" "https://example2.com"
 *
 * # Replace a string in a spesific table with db prefix
 * $ wp mjson replace "http://example1.com" "https://example2.com" --prefix="mysite_" --table="wp_postmeta" --column="meta_value" --primary="meta_id"
 *
 */
class Json_Strings extends WP_CLI_Command {

    /**
     * Version of this package
     */
    const VERSION = '0.5';

    /**
    * Show version of this package
    *
    * ## OPTIONS
    *
    * [--format=<format>]
    * : Output format
    *
    * ## EXAMPLES
    *
    *      wp mjson version --format json
    *
    * @return void
    */
    public function version( $args, $assoc_args ) {
        $version = Replace_Json_String::VERSION;
        wp_CLI::line();
        if( array_key_exists( 'format', $assoc_args ) ) {
            if( $assoc_args['format'] == 'json' ) {
                WP_CLI::log( json_encode( $version ) );
                return;
            }
            if( $assoc_args['format'] == 'text' ) {
                WP_CLI::log( $version );
                return;
            }
        }
        WP_CLI::log( 'Version of this package: '.$version );
    }


    /**
    * Replace json encoded strings in the database
    *
    * ## OPTIONS
    *
    * <search>
    * : The string to search for
    *
    * <replace>
    * : The string to replace
    *
    * [--prefix=<format>]
    * : Set database prefix. Default: '' (No prefix)
    *
    * [--table=<format>]
    * : Set table to search. Default: wp_postmeta
    *
    * [--column=<format>]
    * : Set column to search. Default: meta_value
    *
    * [--primary=<format>]
    * : Set primary key. Default: meta_id
    *
    * # Replace a string
    * $ wp mjson replace "http://example1.com" "https://example2.com"
    *
    * # Replace a string in a spesific table with db prefix
    * $ wp mjson replace "http://example1.com" "https://example2.com" --prefix="mysite_" --table="wp_postmeta" --column="meta_value" --primary="meta_id"
    *
    * @param array $args
    * @param array $assoc_args
    *
    * @return void
    */
    public function replace( $args, $assoc_args ) {
        global $wpdb;

        $prefix = '';
        if( array_key_exists( 'prefix', $assoc_args ) ) {
            $table = $assoc_args['prefix'];
        }
        $table = $prefix.'wp_postmeta';
        if( array_key_exists( 'table', $assoc_args ) ) {
            $table = $assoc_args['table'];
        }
        $column = 'meta_value';
        if( array_key_exists( 'column', $assoc_args ) ) {
            $column = $assoc_args['column'];
        }
        $primary_key = 'meta_id';
        if( array_key_exists( 'primary', $assoc_args ) ) {
            $primary_key = $assoc_args['primary'];
        }
        $search_string = array_shift( $args );
        $replace_string = array_shift( $args );
        $meta_keys = array();
        $total_count = 0;
        wp_CLI::line();
        WP_CLI::log( sprintf(
            'Replace %s with %s',
            WP_CLI::colorize( '%W'.$search_string.'%n' ),
            WP_CLI::colorize( '%W'.$replace_string.'%n' )));
        WP_CLI::log( sprintf(
            'Table: %s',
            WP_CLI::colorize( '%W'.$table.'%n' )));
        WP_CLI::log( sprintf(
            'Column: %s',
            WP_CLI::colorize( '%W'.$column.'%n' )));
        WP_CLI::log( sprintf(
            'Primary key: %s',
            WP_CLI::colorize( '%W'.$primary_key.'%n' )));
        /*
         * Query database
         */
        list($meta_keys, $replace_count) = $this->_replace($search_string, $replace_string, $table, $column, $primary_key, $json=true);
        $total_count += $replace_count;
        foreach( $meta_keys as $key => $key_count ) {
            WP_CLI::log( sprintf('  %-40s: %3d times', $key, $key_count) );
        }
        if( $total_count > 0 ) {
            WP_CLI::success( "Replaced string ".$total_count." times." );
        } else {
            WP_CLI::warning( "Replaced string ".$total_count." times." );
        }
        wp_CLI::line();
    }

    /**
    * Find json encoded strings in the database
    *
    * ## OPTIONS
    *
    * <search>
    * : The string to search for
    *
    * [--prefix=<format>]
    * : Set database prefix. Default: '' (No prefix)
    *
    * [--table=<format>]
    * : Set table to search. Default: wp_postmeta
    *
    * [--column=<format>]
    * : Set column to search. Default: meta_value
    *
    * [--primary=<format>]
    * : Set primary key. Default: meta_id
    *
    * # Find a string
    * $ wp mjson search "http://example1.com"
    *
    * # Find a string in a spesific table with db prefix
    * $ wp mjson search "http://example1.com" --prefix="mysite_" --table="wp_postmeta" --column="meta_value" --primary="meta_id"
    *
    * @param array $args
    * @param array $assoc_args
    *
    * @return void
    */
    public function search( $args, $assoc_args ) {
        global $wpdb;

        $prefix = '';
        if( array_key_exists( 'prefix', $assoc_args ) ) {
            $prefix = $assoc_args['prefix'];
        }
        $table = $prefix.'wp_postmeta';
        if( array_key_exists( 'table', $assoc_args ) ) {
            $table = $prefix.$assoc_args['table'];
        }
        $column = 'meta_value';
        if( array_key_exists( 'column', $assoc_args ) ) {
            $column = $assoc_args['column'];
        }
        $search_string = array_shift( $args );
        $meta_keys = array();
        $total_count = 0;
        WP_CLI::line();
        WP_CLI::log( sprintf(
            '%10s: %s',
            'Find',
            WP_CLI::colorize( '%W'.$search_string.'%n' )));
        WP_CLI::log( sprintf(
            '%10s: %s',
            'Prefix',
            WP_CLI::colorize( '%W'.$prefix.'%n' )));
        WP_CLI::log( sprintf(
            '%10s: %s',
            'Table',
            WP_CLI::colorize( '%W'.$table.'%n' )));
        WP_CLI::log( sprintf(
            '%10s: %s',
            'Column',
            WP_CLI::colorize( '%W'.$column.'%n' )));
        /*
         * Query database
         */
        $search = $this->_json_encode($search_string);
        $results = $this->_search($search, $table, $column);
        $num = count($results);
        WP_CLI::log( sprintf(
            '%10s: %s',
            'Found',
            WP_CLI::colorize( '%W'."$num results".':%n' )));
        WP_CLI::line();
        foreach( $results as $res ) {
            $start = strpos($res->$column, $search) - 30;
            $length = strlen($search) + 74;
            $result_cut = '...'.substr($res->$column, $start, 74).'...';
            WP_CLI::log( $result_cut );
        }
        WP_CLI::line();
    }

    private function _search($search, $table, $column) {
        global $wpdb;

        $esc_char = '!';

        if( strpos( $search, $esc_char ) ) {
            return False;
        }
        $meta_key = '%'.$search.'%';
        $query =  $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$column} LIKE %s ESCAPE %s",
            $meta_key, $esc_char );
        $results = $wpdb->get_results( $query );
        return $results;
    }

    private function _json_encode($text) {
        $text = json_encode( $text );
        /* Remove quotes */
        $text = substr_replace( $text, '', 0, 1 );
        $text = substr_replace( $text, '', -1, 1 );
        return $text;
    }

    private function _replace($search, $replace, $table, $column, $primary_key, $json=false) {
        global $wpdb;

        $meta_keys = array();
        $total_count = 0;

        if( $json === true ) {
            $search = $this->_json_encode($search);
            $replace = $this->_json_encode($replace);
        }

        $results = $this->_search($search, $table, $column);

        foreach( $results as $result ) {
            $replace_count = 0;
            $col_val = $result->$column;
            /*
             * Replace strings in column
             */
            $new_col_val = str_replace(
                $search, $replace, $col_val, $replace_count );
            /*
             * Update database
             */
            $wpdb->update(
                $table,
                array( $column => $new_col_val),
                array( $primary_key => $result->$primary_key ) );
            if( !array_key_exists( $meta_key, $meta_keys )) {
                $meta_keys[$meta_key] = $replace_count;
            } else {
                $meta_keys[$meta_key] += $replace_count;
            }
            $total_count += $replace_count;
        }
        return array($meta_keys, $total_count);
    }
}
WP_CLI::add_command( 'mjson', 'Json_Strings' );
