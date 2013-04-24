<?php
/*
Plugin Name: Analytics Variables for WordPress
Plugin URI: http://alexis.nomine.fr/en/blog/
Description: Track WordPress data with Google Analytics custom variables: archive (main blog, date, author, category, tag, taxonomy, post type) or single (page, post, attachment, post type) and single infos (categories, tags, author, date, comments, custom taxonomies). Works with Yoast Analytics and Analyticator.
Version: 0.6
Author: Alexis Nominé
Author URI: http://alexis.nomine.fr/en/blog/
License: MIT
*/

/* TODO: 
detect which plugin is in use (Yoast/Analyticator)
remove post_type, tags, categories, author and year for yoast if set
limit var to 128 caracters (key+value)
add support for custom meta via get_post_meta()
*/

class WP_Analytics_Variables{
	protected $params;
	protected $defaults = array( 
		'debug' => false,
		'single_vars' => array( 'category', 'post_tag', 'author', 'date', 'comments', 'post_format' )
	);

	/**
	* Define parameters and hooks the analytics plugins (currently: Analyticator and Yoast)
	*/
	public function __construct( $args ){
		$this->params = wp_parse_args( $args, $defaults );

		if( $this->params['debug'] )
			add_action( 'wp_head', array( $this, 'debug' ) );
		else {
			add_filter( 'yoast-ga-custom-vars', array( $this, 'yoast_add_custom_vars' ), 10, 2 );
			add_action( 'google_analyticator_extra_js_before', array( $this, 'analyticator_add_custom_vars' ) );
		}
	}

	/**
	* Adds the variables to Yoast's Google Analytics
	*/
	public function yoast_add_custom_vars($push, $customvarslot) {
		$gvars = $this->get_custom_vars();
		foreach ($gvars as $name => $value) {
			$push[] = "'_setCustomVar'," . $customvarslot . ",'" . $name . "','" . $value . "',3";
			$customvarslot++;
		}
		return $push;
	}

	/**
	* Adds the variables to Analyticator
	*/
	public function analyticator_add_custom_vars() {
		$gvars = $this->get_custom_vars();
		$customvarslot = 1;
		foreach ($gvars as $name => $value) {
			echo "_gaq.push(['_setCustomVar'," . $customvarslot . ",'" . $name . "','" . $value . "',3]);";
			$customvarslot++;
		}
	}

	/**
	* Prints the variables in a comment in the header of the page for debug purposes
	*/
	public function debug(){
		global $wp_query;
		$gvars = $this->get_custom_vars();
		echo "\n<!-- debug-analytics-variables\nparams: ";
		print_r( $this->params );
		echo "\ncustom vars: ";
		print_r( $gvars );
		echo "\nwpquery vars: ";
		print_r($wp_query->query_vars);
		echo "\n-->";
	}

	/**
	* Defines all the variables to add to google analytics
	*/
	protected function get_custom_vars() {
		$gvars = array();

		if ( is_home() ) {
			$page = (get_query_var('paged')) ? get_query_var('paged') : 1;
			$gvars[ 'blog' ] = $page;
		}
		elseif ( is_singular() ) { // single element like page, post, attachment or custom post type
			global $post;

			$display = $this->params[ 'single_vars' ];

			$gvars[ get_post_type() ] =  $post->post_name; // ex: $gvars['page'] = 'the-page-slug'

			if ( in_array ( 'author' , $display ) )
				$gvars[ 'author' ] = get_the_author();

			if ( in_array ( 'date' , $display ) )
				$gvars[ 'date' ] = get_the_date( 'Y-m-d' );

			if ( in_array ( 'comments' , $display ) )
				$gvars[ 'comments' ] = get_comments_number();

			// includes category, tags, post format
			$taxonomies = get_object_taxonomies( get_post_type() );
			foreach ( $taxonomies as $taxonomy ) {
				if ( in_array ( $taxonomy , $display ) ) {
					$terms = get_the_terms( get_the_ID(), $taxonomy );
					$gvars[ $taxonomy ] = $this->terms_array_to_string( $terms );
				}
			}

			// TODO: post meta support
		}
		elseif ( is_archive() ){
			if ( is_category() ) {
				$gvars[ 'archive-category' ] = get_query_var( 'category_name' );
			}
			elseif ( is_tag() ) {
				$gvars[ 'archive-tag' ] = get_query_var( 'tag' );
			}
			elseif ( is_author() ) {
				$gvars[ 'archive-author' ] = get_query_var( 'author_name' );
			}
			elseif ( is_date() ) {
				// add a 0 to the month if necessary
				$mth = get_query_var('monthnum');
				$mth = ($mth < 10) ? '0'.$mth : $mth;

				if (is_day()){
					$gvars[ 'archive-date' ] = get_query_var('year').'-'.$mth.'-'.get_query_var('day');
				}
				elseif (is_month()){
					$gvars[ 'archive-date' ] = get_query_var('year').'-'.$mth;
				}
				elseif (is_year()){
					$gvars[ 'archive-date' ] = get_query_var('year');
				}
			}
			elseif ( is_post_type_archive() ) {
				$page = (get_query_var('paged')) ? get_query_var('paged') : 1;
				$gvars[ 'archive-' . get_query_var( 'post_type' ) ] =  $page;
			}
			elseif ( is_tax() ) { // includes post format
				$tax = get_query_var( 'taxonomy' );
				$gvars[ 'archive-' . get_query_var( 'taxonomy' ) ] = get_query_var('term');
			}
		}
		return $gvars;
	}

	/**
	* return a string containing each term slug separated by a space
	*/
	protected function terms_array_to_string($terms){
		$i = 0;
		$termsstr = '';
		foreach ( (array) $terms as $term ){
			if ( $i > 0 )
				$termsstr .= ' ';
			$termsstr .= $term->slug;
			$i++;
		}
		return $termsstr;
	}
}

$WPAV = new WP_Analytics_Variables( array( 'debug' => false, 'single_vars' => array( 'category', 'post_format', 'profile_cat', 'calendrier' ) ) );
?>