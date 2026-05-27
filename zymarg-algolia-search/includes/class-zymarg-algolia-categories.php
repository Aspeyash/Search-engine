<?php
/**
 * Product category indexer.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Categories
 */
class Zymarg_Algolia_Categories extends Zymarg_Algolia_Indexer {

	/**
	 * @return string
	 */
	protected function slug() {
		return 'categories';
	}

	/**
	 * @return string
	 */
	public function get_index_name() {
		return zymarg_algolia_index_name( 'categories' );
	}

	/**
	 * Hooks for category create/update/delete.
	 */
	protected function register_hooks() {
		add_action( 'created_product_cat', array( $this, 'on_change' ) );
		add_action( 'edited_product_cat', array( $this, 'on_change' ) );
		add_action( 'pre_delete_term', array( $this, 'on_pre_delete' ), 10, 2 );
	}

	public function on_change( $term_id ) {
		$this->queue_one( (int) $term_id );
	}

	public function on_pre_delete( $term_id, $taxonomy ) {
		if ( 'product_cat' === $taxonomy ) {
			$this->delete_one( (int) $term_id );
		}
	}

	/**
	 * Build category record.
	 *
	 * @param int $term_id Term ID.
	 * @return array|null
	 */
	public function build_record( $term_id ) {
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$image_id = get_term_meta( $term_id, 'thumbnail_id', true );
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

		$record = array(
			'objectID'  => $this->object_id( $term_id ),
			'id'        => (int) $term_id,
			'type'      => 'category',
			'name'      => $term->name,
			'slug'      => $term->slug,
			'permalink' => get_term_link( $term ),
			'count'     => (int) $term->count,
			'parent'    => (int) $term->parent,
			'image'     => $image,
		);

		return apply_filters( 'zymarg_algolia_category_record', $record, $term );
	}

	/**
	 * @return array
	 */
	public function get_all_ids() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map( 'intval', $terms );
	}

	/**
	 * @return array
	 */
	public function get_index_settings() {
		$languages = (array) zymarg_algolia_get_setting( 'languages', array( 'en', 'bn' ) );
		return array(
			'searchableAttributes' => array( 'unordered(name)' ),
			'customRanking'        => array( 'desc(count)' ),
			'queryLanguages'       => $languages,
			'indexLanguages'       => $languages,
			'removeStopWords'      => $languages,
			'ignorePlurals'        => $languages,
			'typoTolerance'        => true,
			'highlightPreTag'      => '<mark>',
			'highlightPostTag'     => '</mark>',
		);
	}
}
