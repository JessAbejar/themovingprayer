<?php
function karuna_child_styles() {

    $parent_style = 'karuna';

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_style ),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'karuna_child_styles' );

function karuna_child_styles_rtl() {

    $parent_style = 'karuna';

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/rtl.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/rtl.css',
        array( $parent_style ),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'karuna_child_styles_rtl' );

function karuna_child_styles_woocommerce() {

    $parent_style = 'karuna';

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/woocommerce.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/woocommerce.css',
        array( $parent_style ),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'karuna_child_styles_woocommerce' );

function karuna_child_styles_woocommerce_rtl() {

    $parent_style = 'karuna';

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/woocommerce-rtl.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/woocommerce-rtl.css',
        array( $parent_style ),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'karuna_child_styles_woocommerce_rtl' );