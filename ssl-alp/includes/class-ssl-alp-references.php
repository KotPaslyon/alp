<?php

if ( ! defined( 'WPINC' ) ) {
    // prevent direct access
    exit;
}

/**
 * Literature reference functionality
 */
class SSL_ALP_References extends SSL_ALP_Module {
	/**
	 * Supported post types for reference extraction/display, and whether to display their date
	 * on the revisions list
	 */
	protected $supported_reference_post_types = array(
		'post'	=>	true,
		'page'	=>	false
	);

	/**
	 * Register settings
	 */
	public function register_settings() {
        register_setting(
			SSL_ALP_SITE_SETTINGS_PAGE,
			'ssl_alp_enable_crossreferences',
			array(
				'type'		=>	'boolean'
			)
		);
	}

    /**
     * Register settings fields
     */
    public function register_settings_fields() {
        /**
         * Post references settings
         */

        add_settings_field(
			'ssl_alp_reference_settings',
			__( 'References', 'ssl-alp' ),
			array( $this, 'reference_settings_callback' ),
			SSL_ALP_SITE_SETTINGS_PAGE,
			'ssl_alp_post_settings_section'
		);
    }

    public function reference_settings_callback() {
		require_once SSL_ALP_BASE_DIR . 'partials/admin/settings/post/reference-settings-display.php';
	}

	/**
	 * Register hooks
	 */
	public function register_hooks() {
		$loader = $this->get_loader();

		// extract references from saved posts
		$loader->add_action( 'init', $this, 'create_crossreference_taxonomy', 20 ); // called after settings are registered
		$loader->add_action( 'save_post', $this, 'extract_crossreferences', 10, 2 );
	}

	public function create_crossreference_taxonomy() {
		if ( ! get_option( 'ssl_alp_enable_crossreferences' ) ) {
			// cross-references are disabled
			return;
		}

		// create internal reference taxonomy
		register_taxonomy(
			'ssl_alp_crossreference',
			array_keys( $this->supported_reference_post_types ),
			array(
				'hierarchical'	=> false,
				'rewrite' 		=> false,
				'meta_box_cb'	=> false,
				'public'		=> false,
				'labels' 		=> array(
					'name'                       => _x( 'Cross-references', 'cross-reference taxonomy general name', 'ssl-alp' ),
					'singular_name'              => _x( 'Cross-reference', 'cross-reference taxonomy singular name', 'ssl-alp' )
				)
			)
		);
	}

	/**
	 * Extract references from updated/created posts and insert them into the
	 * term database for display under the post
	 */
	public function extract_crossreferences( $post_id, $post ) {
		if ( ! get_option( 'ssl_alp_enable_crossreferences' ) ) {
			// cross-references are disabled
			return;
		} elseif ( ! $this->is_supported( $post ) ) {
			// post type not supported
			return;
		}

		// find URLs in post content
		$urls = wp_extract_urls( $post->post_content );

		// terms to set
		$terms = array();

		foreach ( $urls as $url ) {
			// attempt to find the post ID for the URL
			$reference_id = url_to_postid( $url );

			$referenced_post = get_post( $reference_id );

			if ( is_null( $referenced_post ) ) {
				// invalid post - skip
				continue;
			} elseif ( ! $this->is_supported( $referenced_post ) ) {
				// target not supported for referencing
				continue;
			} elseif ( $referenced_post->ID == $post_id ) {
				// self-reference
				continue;
			}

			/*
			 * create referenced-to relationship
			 */

			// create "reference to" term
			$ref_to_post_term_name = sprintf( 'reference-to-post-id-%d', $referenced_post->ID );

			// add term name to list that will be associated with the post
			$terms[ $ref_to_post_term_name ] = $referenced_post->ID;
		}

		// update post's reference taxonomy terms (replaces any existing terms)
		wp_set_post_terms( $post->ID, array_keys( $terms ), 'ssl_alp_crossreference' );

		// set internal term metadata
		foreach ( $terms as $term_name => $referenced_post_id ) {
			// get term
			$term = get_term_by( 'name', $term_name, 'ssl_alp_crossreference' );

			// add term metadata
			update_term_meta( $term->term_id, "reference-to-post-id", $referenced_post_id );
		}
	}

	/**
	 * Check if specified post is supported with references
	 */
	public function is_supported( $post ) {
		$post = get_post( $post );

		return array_key_exists( $post->post_type, $this->supported_reference_post_types );
	}

	/**
	 * Check whether the specified post should have its publication date shown in cross-references
	 */
	public function show_date( $post ) {
		$post = get_post( $post );

		if ( ! $this->is_supported( $post ) ) {
			// post type is not supported
			return null;
		}

		// values of supported_reference_post_types specifies whether to show date
		return (bool) $this->supported_reference_post_types[$post->post_type];
	}

	/**
	 * Re-scan supported post types to update references
	 */
	public function rebuild_references() {
		if ( ! get_option( 'ssl_alp_enable_crossreferences' ) ) {
			// cross-references are disabled
			return;
		}

		// allow unlimited execution
		ini_set( 'max_execution_time', 0 );

		foreach ( array_keys( $this->supported_reference_post_types ) as $post_type ) {
			$posts = get_posts(
				array(
					'post_type' 		=> $post_type,
					'post_status'		=> 'published',
					'posts_per_page'	=> -1 // needed to get all
				)
			);

			foreach ( $posts as $post ) {
				$this->extract_crossreferences( $post->ID, $post );
			}
		}
	}

	/**
	 * Get posts that are referenced by the specified post
	 */
	public function get_reference_to_posts( $post = null ) {
		$post = get_post( $post );

		$terms = get_the_terms( $post, 'ssl_alp_crossreference' );

		$posts = array();

		if ( ! is_array( $terms ) ) {
			// no terms to get posts from
			return $posts;
		}

		// get the posts associated with the terms
		foreach ( $terms as $term ) {
			// get post ID
			$post_id = get_term_meta( $term->term_id, 'reference-to-post-id', 'ssl_alp_crossreference' );

			// get the post
			$referenced_post = get_post( $post_id );

			if ( ! is_null( $referenced_post ) ) {
				$posts[] = $referenced_post;
			}
		}

		return $posts;
	}

	/**
	 * Get posts that reference the specified post
	 */
	public function get_reference_from_posts( $post = null ) {
		global $wpdb;

		$post = get_post( $post );

		$posts = array();

		if ( is_null( $post ) ) {
			return $posts;
		}

		// query for terms that reference this post
		$object_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT posts.ID
				FROM {$wpdb->termmeta} AS termmeta
				INNER JOIN {$wpdb->term_relationships} AS term_relationships
					ON termmeta.term_id = term_relationships.term_taxonomy_id
				INNER JOIN {$wpdb->posts} AS posts
					ON term_relationships.object_id = posts.ID
				INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy
					ON termmeta.term_id = term_taxonomy.term_id
				WHERE
					termmeta.meta_key = %s
					AND termmeta.meta_value = %d
					AND term_taxonomy.taxonomy = %s
				ORDER BY
					posts.post_date DESC
				",
				'reference-to-post-id',
				$post->ID,
				'ssl_alp_crossreference'
			)
		);

		// get the posts associated with the term IDs
		foreach ( $object_ids as $post_id ) {
			// get the post
			$referenced_post = get_post( $post_id );

			$posts[] = $referenced_post;
		}

		return $posts;
	}
}