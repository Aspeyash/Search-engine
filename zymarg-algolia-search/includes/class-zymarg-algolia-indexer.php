<?php
/**
 * Base indexer class. Provides shared helpers + Action Scheduler / WP-Cron
 * fallback for async indexing so saves never block the WP admin.
 *
 * @package ZymargAlgolia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zymarg_Algolia_Indexer
 *
 * Subclasses must implement:
 *   - get_index_name()
 *   - build_record( $object_id )
 *   - get_all_ids()
 *   - get_index_settings()
 */
abstract class Zymarg_Algolia_Indexer {

	/**
	 * Algolia client instance.
	 *
	 * @var Zymarg_Algolia_Client
	 */
	protected $client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->client = new Zymarg_Algolia_Client();
		$this->register_hooks();
		// Async batch processor.
		add_action( 'zymarg_algolia_reindex_batch_' . $this->slug(), array( $this, 'cron_batch' ), 10, 2 );
	}

	/**
	 * Slug used as a stable key (e.g. "products", "vendors", "categories").
	 *
	 * @return string
	 */
	abstract protected function slug();

	/**
	 * Algolia index name (already prefixed).
	 *
	 * @return string
	 */
	abstract public function get_index_name();

	/**
	 * Build an Algolia record from a given object ID.
	 * Return null to skip (e.g. unpublished, hidden, etc).
	 *
	 * @param int|string $object_id ID.
	 * @return array|null
	 */
	abstract public function build_record( $object_id );

	/**
	 * Get all object IDs that should be indexed.
	 *
	 * @return array
	 */
	abstract public function get_all_ids();

	/**
	 * Algolia index settings (ranking, searchable attrs, languages...).
	 *
	 * @return array
	 */
	abstract public function get_index_settings();

	/**
	 * Register hooks. Subclasses override.
	 *
	 * @return void
	 */
	protected function register_hooks() {}

	/* ---------------------------------------------------------------------- */
	/* Public API.                                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * Push (or update) one object now.
	 *
	 * @param int|string $object_id ID.
	 * @return array|WP_Error|null
	 */
	public function index_one( $object_id ) {
		if ( ! $this->client->is_configured() ) {
			return null;
		}
		$record = $this->build_record( $object_id );
		if ( null === $record ) {
			// Skipped — also remove from index in case it was there.
			return $this->delete_one( $object_id );
		}
		$result = $this->client->save_object( $this->get_index_name(), $record );
		/**
		 * Fires after a single object is indexed.
		 */
		do_action( 'zymarg_algolia_indexed_single', $object_id, $this->slug() );
		return $result;
	}

	/**
	 * Schedule indexing for one object asynchronously (preferred).
	 *
	 * @param int|string $object_id ID.
	 * @return void
	 */
	public function queue_one( $object_id ) {
		if ( ! zymarg_algolia_get_setting( 'auto_index', 1 ) ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				'zymarg_algolia_index_single_' . $this->slug(),
				array( $object_id ),
				'zymarg-algolia'
			);
		} else {
			// Fallback: index inline on shutdown so the request returns first.
			add_action(
				'shutdown',
				function () use ( $object_id ) {
					$this->index_one( $object_id );
				}
			);
		}
		// Action Scheduler listener.
		add_action(
			'zymarg_algolia_index_single_' . $this->slug(),
			array( $this, 'index_one' ),
			10,
			1
		);
	}

	/**
	 * Delete one object.
	 *
	 * @param int|string $object_id ID.
	 * @return array|WP_Error|null
	 */
	public function delete_one( $object_id ) {
		if ( ! $this->client->is_configured() ) {
			return null;
		}
		return $this->client->delete_object( $this->get_index_name(), $this->object_id( $object_id ) );
	}

	/**
	 * Object ID prefix (e.g. product_123).
	 *
	 * @param int|string $object_id ID.
	 * @return string
	 */
	public function object_id( $object_id ) {
		return $this->slug() . '_' . $object_id;
	}

	/**
	 * Apply index settings (ranking, languages, etc).
	 *
	 * @return array|WP_Error|null
	 */
	public function apply_settings() {
		if ( ! $this->client->is_configured() ) {
			return null;
		}
		return $this->client->set_settings( $this->get_index_name(), $this->get_index_settings() );
	}

	/**
	 * Reindex everything in batches via Action Scheduler / WP-Cron.
	 *
	 * @param int $batch_size Records per batch.
	 * @return int Total queued.
	 */
	public function reindex_all( $batch_size = 100 ) {
		if ( ! $this->client->is_configured() ) {
			return 0;
		}

		// Apply latest settings first.
		$this->apply_settings();

		$ids   = $this->get_all_ids();
		$total = count( $ids );

		if ( 0 === $total ) {
			return 0;
		}

		$chunks = array_chunk( $ids, max( 10, (int) $batch_size ) );
		foreach ( $chunks as $i => $chunk ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action(
					'zymarg_algolia_reindex_batch_' . $this->slug(),
					array( $chunk, $i ),
					'zymarg-algolia'
				);
			} else {
				wp_schedule_single_event(
					time() + ( $i * 5 ),
					'zymarg_algolia_reindex_batch_' . $this->slug(),
					array( $chunk, $i )
				);
			}
		}
		return $total;
	}

	/**
	 * Cron/AS callback that processes one batch.
	 *
	 * @param array $ids   IDs.
	 * @param int   $batch Batch index.
	 * @return void
	 */
	public function cron_batch( $ids, $batch = 0 ) {
		if ( empty( $ids ) || ! $this->client->is_configured() ) {
			return;
		}
		$records = array();
		$delete  = array();
		foreach ( $ids as $id ) {
			$rec = $this->build_record( $id );
			if ( null !== $rec ) {
				$records[] = $rec;
			} else {
				// No longer indexable (e.g. went out of stock / hidden).
				// Remove any previously-indexed copy so the grid never
				// receives an ID it cannot render.
				$delete[] = $this->object_id( $id );
			}
		}
		if ( ! empty( $records ) ) {
			$this->client->save_objects( $this->get_index_name(), $records );
		}
		if ( ! empty( $delete ) ) {
			foreach ( $delete as $oid ) {
				$this->client->delete_object( $this->get_index_name(), $oid );
			}
		}
	}
}
