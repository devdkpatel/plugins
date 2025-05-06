<?php

//Theme all CSS start
function load_stylesheets() {

    wp_register_style( 'carousel', get_template_directory_uri() . '/assets/css/owl.carousel.min.css', array(), '1.0', 'all' );
    wp_enqueue_style( 'carousel' );

    wp_register_style( 'style', get_template_directory_uri() . '/assets/css/style.css', array(), '1.0', 'all' );
    wp_enqueue_style( 'style' );

    wp_register_style( 'responsive', get_template_directory_uri() . '/assets/css/responsive.css', array(), '1.0', 'all' );
    wp_enqueue_style( 'responsive' );

    wp_register_style( 'color', get_template_directory_uri() . '/assets/css/color.css', array(), '1.0', 'all' );
    wp_enqueue_style( 'color' );
}
add_action( 'wp_enqueue_scripts', 'load_stylesheets' );

//Theme all script Start
function load_javascript() {
    wp_register_script( 'jQuery', get_template_directory_uri() . '/assets/js/jquery.js', array(), '1.0', true );
    wp_enqueue_script( 'jQuery' );

    wp_register_script( 'carousel', get_template_directory_uri() . '/assets/js/owl.carousel.js', array( 'jQuery' ), '1.0', true );
    wp_enqueue_script( 'carousel' );

    wp_register_script( 'animation', get_template_directory_uri() . '/assets/js/animation.js', array( 'jQuery' ), '1.0', true );
    wp_enqueue_script( 'animation' );
    wp_register_script( 'custom', get_template_directory_uri() . '/assets/js/custom.js', array( 'jQuery' ), '1.0', true );
    wp_enqueue_script( 'custom' );
}
add_action( 'wp_enqueue_scripts', 'load_javascript' );

//Website Menu Area Start
add_theme_support( 'menus' );
register_nav_menus(
    array(
        'main-menu' => __( 'Main Menu', 'theme' ),
        'quick-menu' => __( 'Quick Links', 'theme' ),
    )
);

// create PHP to CSS
function generate_options_css() {
    //$screen = get_current_screen();
    $ss_dir = get_stylesheet_directory();
    ob_start();
    require( $ss_dir . '/color.php' );
    $css = ob_get_clean();
    file_put_contents( $ss_dir . '/assets/css/color.css', $css, LOCK_EX );
}
add_action( 'acf/save_post', 'generate_options_css', 20 );

//SVG file type upload function start
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename, $mimes ) {

    global $wp_version;
    if ( $wp_version !== '4.7.1' ) {
        return $data;
    }

    $filetype = wp_check_filetype( $filename, $mimes );

    return [
        'ext' => $filetype[ 'ext' ],
        'type' => $filetype[ 'type' ],
        'proper_filename' => $data[ 'proper_filename' ]
    ];

}, 10, 4 );

function cc_mime_types( $mimes ) {
    $mimes[ 'svg' ] = 'image/svg+xml';
    return $mimes;
}
add_filter( 'upload_mimes', 'cc_mime_types' );

function fix_svg() {
    echo '<style type="text/css">
          .attachment-266x266, .thumbnail img {
               width: 100% !important;
               height: auto !important;
          }
          </style>';
}
add_action( 'admin_head', 'fix_svg' );

//Hide admin bar for all users
add_filter('show_admin_bar', '__return_false');

    // Remove spaces and dashes from the phone number
function clean_phone_number( $phone_number ) {
    $cleaned_number = str_replace( [ ' ' ], '', $phone_number );
    return $cleaned_number;
}

//Featured Images & Post Thumbnails
add_theme_support( 'post-thumbnails' );


// Service menu fucntion start
function my_custom_page_type() {
    $labels = array(
        'name' => _x( 'Services', 'post type general name', 'textdomain' ),
        'singular_name' => _x( 'Service', 'post type singular name', 'textdomain' ),
        'menu_name' => _x( 'Services', 'admin menu', 'textdomain' ),
        'name_admin_bar' => _x( 'Service', 'add new on admin bar', 'textdomain' ),
        'add_new' => _x( 'Add New', 'service', 'textdomain' ),
        'add_new_item' => __( 'Add New Service', 'textdomain' ),
        'new_item' => __( 'New Service', 'textdomain' ),
        'edit_item' => __( 'Edit Service', 'textdomain' ),
        'view_item' => __( 'View Service', 'textdomain' ),
        'all_items' => __( 'All Services', 'textdomain' ),
        'search_items' => __( 'Search Services', 'textdomain' ),
        'parent_item_colon' => __( 'Parent Services:', 'textdomain' ),
        'not_found' => __( 'No Services found.', 'textdomain' ),
        'not_found_in_trash' => __( 'No Services found in Trash.', 'textdomain' ),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array( 'slug' => 'service' ),
        'capability_type' => 'page',
        'hierarchical' => true,
        'menu_position' => 3,
        'menu_icon' => 'dashicons-category',
        'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'page-attributes' ),
    );

    register_post_type( 'services', $args );
}
add_action( 'init', 'my_custom_page_type' );

function add_services_meta_box() {
    add_meta_box(
        'services_meta_box', // Meta box ID
        'Additional Information', // Meta box title
        'services_meta_box_callback', // Callback function
        'services', // Post type
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'add_services_meta_box' );

function services_meta_box_callback( $post ) {
    // Add a nonce field so we can check for it later.
    wp_nonce_field( 'save_services_meta_box_data', 'services_meta_box_nonce' );

    // Retrieve an existing value from the database.
    $value = get_post_meta( $post->ID, '_services_meta_value_key', true );

    // Display the form, using the current value.
    echo '<label for="services_meta_field">My Meta Field</label>';
    echo '<input type="text" id="services_meta_field" name="services_meta_field" value="' . esc_attr( $value ) . '" size="25" />';
}

function save_services_meta_box_data( $post_id ) {
    // Check if our nonce is set.
    if ( !isset( $_POST[ 'services_meta_box_nonce' ] ) ) {
        return;
    }
    // Verify that the nonce is valid.
    if ( !wp_verify_nonce( $_POST[ 'services_meta_box_nonce' ], 'save_services_meta_box_data' ) ) {
        return;
    }
    // If this is an autosave, our form has not been submitted, so we don’t want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    // Check the user's permissions.
    if ( !current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST[ 'services_meta_field' ] ) ) {
        $my_data = sanitize_text_field( $_POST[ 'services_meta_field' ] );
        update_post_meta( $post_id, '_services_meta_value_key', $my_data );
    }
}
add_action( 'save_post', 'save_services_meta_box_data' );

//Career Menu 
function career_page_type() {
    $labels = array(
        'name' => _x( 'Career', 'post type general name', 'textdomain' ),
        'singular_name' => _x( 'Career', 'post type singular name', 'textdomain' ),
        'menu_name' => _x( 'Career', 'admin menu', 'textdomain' ),
        'name_admin_bar' => _x( 'Career', 'add new on admin bar', 'textdomain' ),
        'add_new' => _x( 'Add New', 'career', 'textdomain' ),
        'add_new_item' => __( 'Add New Career', 'textdomain' ),
        'new_item' => __( 'New Career', 'textdomain' ),
        'edit_item' => __( 'Edit Career', 'textdomain' ),
        'view_item' => __( 'View Career', 'textdomain' ),
        'all_items' => __( 'All Career', 'textdomain' ),
        'search_items' => __( 'Search Career', 'textdomain' ),
        'parent_item_colon' => __( 'Parent Career:', 'textdomain' ),
        'not_found' => __( 'No Career found.', 'textdomain' ),
        'not_found_in_trash' => __( 'No Career found in Trash.', 'textdomain' ),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array( 'slug' => 'career' ),
        'capability_type' => 'page',
        'hierarchical' => true,
        'menu_position' => 6,
        'menu_icon' => 'dashicons-category',
        'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes' ),
    );

    register_post_type( 'careers', $args );
}
add_action( 'init', 'career_page_type' );


?>