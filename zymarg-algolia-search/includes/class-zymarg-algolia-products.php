<?php
/**
 * WooCommerce product indexer.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Products
 */
class Zymarg_Algolia_Products extends Zymarg_Algolia_Indexer {

	/**
	 * @return string
	 */
	protected function slug() {
		return 'products';
	}

	/**
	 * @return string
	 */
	public function get_index_name() {
		return zymarg_algolia_index_name( 'products' );
	}

	/**
	 * Hooks for auto-update.
	 */
	protected function register_hooks() {
		// Fires for any product save (create / update / quick edit / bulk edit).
		add_action( 'save_post_product', array( $this, 'on_save' ), 20, 3 );
		add_action( 'save_post_product_variation', array( $this, 'on_save_variation' ), 20, 3 );

		// Trash / delete.
		add_action( 'wp_trash_post', array( $this, 'on_trash' ) );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
		add_action( 'untrashed_post', array( $this, 'on_untrash' ) );

		// Stock changes (WooCommerce).
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_change' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'on_stock_change' ), 10, 1 );
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post.
	 * @param bool     $update  Is update.
	 */
	public function on_save( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$this->queue_one( $post_id );
	}

	/**
	 * Variation save: re-index the parent product.
	 */
	public function on_save_variation( $post_id, $post, $update ) {
		$parent = wp_get_post_parent_id( $post_id );
		if ( $parent ) {
			$this->queue_one( $parent );
		}
	}

	public function on_trash( $post_id ) {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		$this->delete_one( $post_id );
	}

	public function on_delete( $post_id ) {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		$this->delete_one( $post_id );
	}

	public function on_untrash( $post_id ) {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		$this->queue_one( $post_id );
	}

	public function on_stock_change( $product_id ) {
		if ( $product_id ) {
			// If it's a variation, push the parent.
			$parent = wp_get_post_parent_id( $product_id );
			$this->queue_one( $parent ? $parent : $product_id );
		}
	}

	/**
	 * Build product record.
	 *
	 * @param int $post_id Product ID.
	 * @return array|null
	 */
	public function build_record( $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return null;
		}
		// Only published.
		if ( 'publish' !== $product->get_status() ) {
			return null;
		}

		// Exclude only products explicitly HIDDEN from the catalog.
		// We intentionally KEEP out-of-stock products in the index so they
		// stay visible in search results (site policy), independent of
		// WooCommerce's global "Hide out of stock items" setting. Use the
		// zymarg_algolia_index_product filter to customise this.
		$is_hidden = method_exists( $product, 'get_catalog_visibility' ) && 'hidden' === $product->get_catalog_visibility();
		$indexable = ! $is_hidden;

		/**
		 * Filter whether a product should be indexed.
		 *
		 * @param bool       $indexable Default: true unless catalog visibility is "hidden".
		 * @param WC_Product $product   Product object.
		 * @param int        $post_id   Product ID.
		 */
		if ( ! apply_filters( 'zymarg_algolia_index_product', $indexable, $product, $post_id ) ) {
			return null;
		}

		// Vendor info via Dokan.
		$vendor_id           = 0;
		$vendor_name         = '';
		$vendor_url          = '';
		$vendor_rating       = 0.0;
		$vendor_total_reviews = 0;
		if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
			$vendor = dokan_get_vendor_by_product( $product );
			if ( $vendor ) {
				$vendor_id   = (int) $vendor->get_id();
				$vendor_name = (string) $vendor->get_shop_name();
				$vendor_url  = (string) $vendor->get_shop_url();
				// Dokan Pro: store rating.
				if ( method_exists( $vendor, 'get_rating' ) ) {
					$rating_data          = $vendor->get_rating();
					$vendor_rating        = isset( $rating_data['rating'] ) ? (float) $rating_data['rating'] : 0.0;
					$vendor_total_reviews = isset( $rating_data['count'] )  ? (int) $rating_data['count']   : 0;
				}
			}
		}
		if ( ! $vendor_id ) {
			$author      = (int) get_post_field( 'post_author', $post_id );
			$vendor_id   = $author;
			$vendor_name = $author ? get_the_author_meta( 'display_name', $author ) : '';
		}

		// Categories — three parallel arrays (names, slugs, IDs).
		$cat_terms = get_the_terms( $post_id, 'product_cat' );
		$cats      = array();
		$cat_slugs = array();
		$cat_ids   = array();
		if ( is_array( $cat_terms ) ) {
			foreach ( $cat_terms as $t ) {
				$cats[]      = $t->name;
				$cat_slugs[] = $t->slug;
				$cat_ids[]   = (int) $t->term_id;
			}
		}

		// Tags — three parallel arrays (names, slugs, IDs).
		$tag_terms = get_the_terms( $post_id, 'product_tag' );
		$tags      = array();
		$tag_slugs = array();
		$tag_ids   = array();
		if ( is_array( $tag_terms ) ) {
			foreach ( $tag_terms as $t ) {
				$tags[]      = $t->name;
				$tag_slugs[] = $t->slug;
				$tag_ids[]   = (int) $t->term_id;
			}
		}

		// Brand — check the three most common brand plugins.
		$brand      = '';
		$brand_slug = '';
		$brand_taxonomies = array( 'pwb-brand', 'yith_product_brand', 'product_brand' );
		foreach ( $brand_taxonomies as $brand_tax ) {
			$brand_terms = get_the_terms( $post_id, $brand_tax );
			if ( is_array( $brand_terms ) && ! empty( $brand_terms ) ) {
				$brand      = $brand_terms[0]->name;
				$brand_slug = $brand_terms[0]->slug;
				break;
			}
		}

		// Image.
		$thumb_id = $product->get_image_id();
		$thumb    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'woocommerce_thumbnail' ) : '';
		if ( ! $thumb && function_exists( 'wc_placeholder_img_src' ) ) {
			$thumb = wc_placeholder_img_src( 'woocommerce_thumbnail' );
		}
		$image_count = count( $product->get_gallery_image_ids() ) + ( $thumb_id ? 1 : 0 );

		// Prices.
		$price         = (float) wc_get_price_to_display( $product );
		$regular_price = (float) $product->get_regular_price();
		$sale_price    = $product->get_sale_price();
		$on_sale       = $product->is_on_sale();
		$price_html    = wp_strip_all_tags( wc_price( $price ) );

		// Min/max variation prices (variable products only).
		$product_type        = $product->get_type();
		$min_variation_price = null;
		$max_variation_price = null;
		if ( 'variable' === $product_type ) {
			$min_meta = $product->get_meta( '_min_price' );
			$max_meta = $product->get_meta( '_max_price' );
			if ( '' === $min_meta || null === $min_meta ) {
				$min_meta = get_post_meta( $post_id, '_min_price', true );
			}
			if ( '' === $max_meta || null === $max_meta ) {
				$max_meta = get_post_meta( $post_id, '_max_price', true );
			}
			$min_variation_price = ( '' !== $min_meta && null !== $min_meta ) ? (float) $min_meta : null;
			$max_variation_price = ( '' !== $max_meta && null !== $max_meta ) ? (float) $max_meta : null;
		}

		// Stock quantity.
		$stock_qty_raw  = $product->get_stock_quantity();
		$stock_quantity = ( null !== $stock_qty_raw && '' !== $stock_qty_raw ) ? (int) $stock_qty_raw : null;

		// Total sales — prefer WC product meta lookup table.
		$total_sales  = 0;
		global $wpdb;
		$lookup_table = isset( $wpdb->wc_product_meta_lookup ) ? $wpdb->wc_product_meta_lookup : $wpdb->prefix . 'wc_product_meta_lookup';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$lookup_value = $wpdb->get_var( $wpdb->prepare( "SELECT total_sales FROM {$lookup_table} WHERE product_id = %d", (int) $post_id ) );
		if ( null !== $lookup_value && '' !== $lookup_value ) {
			$total_sales = (int) $lookup_value;
		} else {
			$total_sales = (int) get_post_meta( $post_id, 'total_sales', true );
		}

		// Attributes — FLATTENED to top-level attr_{label} arrays.
		// Nested objects cannot be faceted in Algolia; flat arrays can.
		// Key format: attr_{sanitized_label}  e.g. attr_color, attr_size.
		$flat_attrs = array();
		foreach ( $product->get_attributes() as $attr ) {
			if ( $attr->is_taxonomy() ) {
				$terms = wp_get_post_terms( $post_id, $attr->get_name(), array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$label               = strtolower( str_replace( array( ' ', '-' ), '_', wc_attribute_label( $attr->get_name() ) ) );
					$flat_attrs[ 'attr_' . sanitize_key( $label ) ] = array_values( $terms );
				}
			} else {
				$options = $attr->get_options();
				if ( ! empty( $options ) ) {
					$label               = strtolower( str_replace( array( ' ', '-' ), '_', $attr->get_name() ) );
					$flat_attrs[ 'attr_' . sanitize_key( $label ) ] = array_values( $options );
				}
			}
		}

		$record = array(
			'objectID'            => $this->object_id( $post_id ),
			'id'                  => (int) $post_id,
			'type'                => 'product',
			'name'                => $product->get_name(),
			'slug'                => $product->get_slug(),
			'permalink'           => get_permalink( $post_id ),
			'thumbnail'           => $thumb,
			'sku'                 => $product->get_sku(),
			'short_description'   => wp_strip_all_tags( $product->get_short_description() ),
			'description'         => wp_strip_all_tags( $product->get_description() ),
			'price'               => $price,
			'regular_price'       => $regular_price,
			'sale_price'          => '' !== $sale_price ? (float) $sale_price : null,
			'on_sale'             => (bool) $on_sale,
			'price_html'          => $price_html,
			'currency'            => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'stock_status'        => $product->get_stock_status(),
			'in_stock'            => $product->is_in_stock(),
			'stock_quantity'      => $stock_quantity,
			'product_type'        => $product_type,
			'min_variation_price' => $min_variation_price,
			'max_variation_price' => $max_variation_price,
			'categories'          => $cats,
			'category_slugs'      => $cat_slugs,
			'category_ids'        => $cat_ids,
			'tags'                => $tags,
			'tag_slugs'           => $tag_slugs,
			'tag_ids'             => $tag_ids,
			'brand'               => $brand,
			'brand_slug'          => $brand_slug,
			'featured'            => method_exists( $product, 'is_featured' ) ? (bool) $product->is_featured() : false,
			'average_rating'      => (float) $product->get_average_rating(),
			'review_count'        => (int) $product->get_review_count(),
			'total_sales'         => $total_sales,
			'image_count'         => (int) $image_count,
			'vendor_id'           => $vendor_id,
			'vendor_name'         => $vendor_name,
			'vendor_url'          => $vendor_url,
			'vendor_rating'       => $vendor_rating,
			'vendor_total_reviews' => $vendor_total_reviews,
			'date_created'        => $product->get_date_created() ? $product->get_date_created()->getTimestamp() : 0,
			'date_modified'       => $product->get_date_modified() ? $product->get_date_modified()->getTimestamp() : 0,
		);

		// Merge flattened attribute fields into the record.
		if ( ! empty( $flat_attrs ) ) {
			$record = array_merge( $record, $flat_attrs );
		}

		/**
		 * Filter the product record before it's pushed to Algolia.
		 */
		return apply_filters( 'zymarg_algolia_product_record', $record, $product );
	}

	/**
	 * @return array
	 */
	public function get_all_ids() {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);
		$q = new WP_Query( $args );
		return array_map( 'intval', $q->posts );
	}

	/**
	 * @return array
	 */
	public function get_index_settings() {
		$languages = (array) zymarg_algolia_get_setting( 'languages', array( 'en', 'bn' ) );

		// Dynamically build attributesForFaceting for product attributes.
		// We prefix each WooCommerce attribute label with attr_ and register
		// it as a searchable facet so filter sidebars can use them.
		$attr_facets = array();
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$wc_attributes = wc_get_attribute_taxonomies();
			if ( is_array( $wc_attributes ) ) {
				foreach ( $wc_attributes as $wc_attr ) {
					$label         = strtolower( str_replace( array( ' ', '-' ), '_', $wc_attr->attribute_label ) );
					$attr_facets[] = 'searchable(attr_' . sanitize_key( $label ) . ')';
				}
			}
		}

		return array(
			'searchableAttributes'  => array(
				'unordered(name)',
				'unordered(brand)',
				'unordered(sku)',
				'unordered(categories)',
				'unordered(tags)',
				'unordered(vendor_name)',
				'unordered(short_description)',
				'unordered(description)',
			),
			'attributesForFaceting' => array_merge(
				array(
					'searchable(categories)',
					'searchable(vendor_name)',
					'searchable(tags)',
					'searchable(brand)',
					'on_sale',
					'in_stock',
					'featured',
					'price',
					'filterOnly(category_slugs)',
					'filterOnly(tag_slugs)',
					'filterOnly(vendor_id)',
					'filterOnly(product_type)',
				),
				$attr_facets
			),
			'customRanking'         => array(
				'desc(total_sales)',
				'desc(average_rating)',
				'desc(on_sale)',
				'desc(vendor_rating)',
				'desc(date_modified)',
				'desc(image_count)',
			),
			'attributesToHighlight' => array( 'name', 'brand', 'vendor_name', 'categories' ),
			'attributesToSnippet'   => array( 'short_description:30' ),
			'queryLanguages'        => $languages,
			'indexLanguages'        => $languages,
			'removeStopWords'       => $languages,
			'ignorePlurals'         => $languages,
			'typoTolerance'         => true,
			'minWordSizefor1Typo'   => 4,
			'minWordSizefor2Typos'  => 8,
			'highlightPreTag'       => '<mark>',
			'highlightPostTag'      => '</mark>',
		);
	}
}
