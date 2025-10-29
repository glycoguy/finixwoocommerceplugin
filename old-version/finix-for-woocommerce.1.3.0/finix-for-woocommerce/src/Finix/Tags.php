<?php

namespace FinixWC\Finix;

/**
 * Process tags.
 *
 * @see https://docs.finix.com/api#section/Tags
 */
class Tags {

	public const MAX_TAGS     = 50;
	public const LENGTH_KEY   = 40;
	public const LENGTH_VALUE = 500;

	private array $tags = [];

	/**
	 * Add tags one by one.
	 *
	 * Key/Value are both cast to string when being cleaned.
	 */
	public function add( $key, $value ): void {

		$key = $this->clean_key( $key );

		if ( $key === null ) {
			return;
		}

		$this->tags[ $key ] = $this->clean_value( $value );
	}

	/**
	 * Retrieve the value of an individual key.
	 */
	public function get( $key ): ?string {

		$key = $this->clean_key( $key );

		if ( $key === null ) {
			return null;
		}

		return $this->tags[ $key ] ?? null;
	}

	/**
	 * Delete the tag by its key.
	 */
	public function delete( $key ): void {

		$key = $this->clean_key( $key );

		if ( $key === null ) {
			return;
		}

		unset( $this->tags[ $key ] );
	}

	/**
	 * Add tags in bulk.
	 */
	public function add_bulk( array $tags ): void {

		foreach ( $tags as $key => $value ) {
			$this->add( $key, $value );
		}
	}

	/**
	 * Retrieve all the tags in a format understandably by our API calls - an object list.
	 */
	public function prepare(): object {

		$tags = $this->tags;

		// Finix API does not allow more than 50 tags.
		// We take the 1st 50 and discard everything else.
		if ( count( $this->tags ) > self::MAX_TAGS ) {
			$tags = array_slice( $tags, 0, self::MAX_TAGS, true );
		}

		return (object) $tags;
	}

	/**
	 * Prepare the tag key to be used in requests to Finix API.
	 * Discard non-scalar keys.
	 */
	private function clean_key( $key ): ?string {

		// As we can get wild types, let's cast to string.
		$key = is_scalar( $key ) ? (string) $key : null;

		if ( $key === null ) {
			return null;
		}

		return mb_substr( sanitize_key( $key ), 0, self::LENGTH_KEY );
	}

	/**
	 * Prepare the tag value to be used in requests to Finix API.
	 * Discard non-scalar values.
	 */
	private function clean_value( $value ): string {

		// As we can get wild types, let's cast to string.
		$value = is_scalar( $value ) ? (string) $value : null;

		if ( $value === null ) {
			return '';
		}

		// These special characters are specifically not allowed.
		$clean = str_replace(
			[
				'\\',
				',',
				'"',
				"'",
			],
			'',
			sanitize_text_field( $value )
		);

		return mb_substr( $clean, 0, self::LENGTH_VALUE );
	}
}
