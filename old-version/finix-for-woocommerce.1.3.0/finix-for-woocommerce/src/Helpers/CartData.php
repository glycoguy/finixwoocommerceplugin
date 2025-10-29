<?php

namespace FinixWC\Helpers;

/**
 * Helper methods to gather and return data from the cart.
 */
class CartData {

	/**
	 * Get products from order instead of cart if on order pay page.
	 *
	 * @param array $order_items Order items.
	 *
	 * @return array Products array.
	 */
	private static function get_products_from_order( array $order_items ): array {

		$products = [];

		foreach ( $order_items as $item_id => $item ) {
			$product = $item->get_product();

			if ( ! $product ) {
				continue;
			}
			$products[] = [
				'name'     => sanitize_text_field( $product->get_name() ),
				'quantity' => $item->get_quantity(),
				'image'    => esc_url( wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ),
				'total'    => number_format( $product->get_price() * $item->get_quantity(), 2 ),
			];
		}

		return $products;
	}

	/**
	 * Prepare order tracking details.
	 * This can be used to return from webhook or call from internal gateway functionality.
	 *
	 * @return array Array of order tracking details.
	 */
	public static function prepare_order_tracking_details(): array {

		$shipping_total = WC()->cart->get_shipping_total();
		$tax_total      = WC()->cart->get_total_tax();
		$subtotal       = WC()->cart->get_subtotal();
		$cart_contents  = WC()->cart->get_cart_contents();
		$currency_code  = sanitize_text_field( get_woocommerce_currency() );

		// Get coupons applied to the cart and what they are.
		$coupons = WC()->cart->get_applied_coupons();
		$coupons = array_map(
			static function ( $coupon ) {

				return [
					'code'  => $coupon,
					'total' => number_format( WC()->cart->get_coupon_discount_amount( $coupon ), 2 ),
				];
			},
			$coupons
		);

		// Create an array that includes product details.
		$products = array_map(
			static function ( $item ) {

				return [
					// Escape name in case there are special characters.
					'name'     => sanitize_text_field( $item['data']->get_name() ),
					'quantity' => $item['quantity'],
					'image'    => esc_url( wp_get_attachment_image_url( $item['data']->get_image_id(), 'thumbnail' ) ),
					'total'    => number_format( $item['data']->get_price() * $item['quantity'], 2 ),
				];
			},
			$cart_contents
		);

		// We might not have a cart over here, so retrieve the data from the current order page.
		if ( is_checkout_pay_page() ) {
			global $wp;

			$order_id = absint( $wp->query_vars['order-pay'] );
		}

		// If we are on the "Order Pay" page, we need to get the totals from the order, not the cart.
		if ( ! empty( $order_id ) ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				$shipping_total = $order->get_shipping_total();
				$tax_total      = $order->get_total_tax();
				$subtotal       = $order->get_subtotal();
				$currency_code  = $order->get_currency();
				$products       = self::get_products_from_order( $order->get_items() );
				$discounts      = $order->get_items( 'coupon' );
				$coupons        = [];

				foreach ( $discounts as $discount ) {
					$coupons[] = [
						'code'  => $discount->get_code(),
						'total' => number_format( $discount->get_discount(), 2 ),
					];
				}
			}
		}

		return [
			'subtotal'        => number_format( $subtotal, 2 ),
			'shipping_amount' => number_format( $shipping_total, 2 ),
			'tax_amount'      => number_format( $tax_total, 2 ),
			'products'        => $products,
			'coupons'         => $coupons,
			'currency_code'   => $currency_code,
		];
	}
}
