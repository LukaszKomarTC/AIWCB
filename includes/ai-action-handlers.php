<?php
if (!defined('ABSPATH')) exit;

/**
 * Normalize date-related parameters for all handlers
 */
function ai_normalize_dates($params) {
    if (!isset($params['date']) && !isset($params['start_date'])) {
        return $params;
    }

    if (!empty($params['start_date']) && !empty($params['end_date'])) {
        return $params;
    }

    if (!empty($params['start_date']) && !empty($params['duration'])) {
        $params['end_date'] = date('Y-m-d', strtotime($params['start_date'] . " +{$params['duration']} days"));
        return $params;
    }

    if (!empty($params['date'])) {
        $params['start_date'] = $params['date'];
        $params['end_date']   = $params['date'];
        return $params;
    }

    if (!empty($params['start_date'])) {
        $params['end_date'] = $params['start_date'];
        return $params;
    }

    return $params;
}

/**
 * ✅ New Shared Availability Check
 */
function ai_check_availability($product_id, $start_timestamp, $end_timestamp, $resource_id = null, $quantity = 1) {
    $product = wc_get_product($product_id);

    if (!$product || !$product->is_type('booking')) {
        return ['error' => "❌ Product ID $product_id is not a booking product."];
    }

    // If product has resources
    if ($product->has_resources()) {
        $resources = $resource_id ? [$product->get_resource($resource_id)] : $product->get_resources();
        $total_available = 0;

        foreach ($resources as $resource) {
            if (!$resource) continue;

            $available_qty = $resource->has_qty() ? $resource->get_qty() : $product->get_qty();
            $existing_bookings = $product->get_bookings_in_date_range(
                $start_timestamp,
                $end_timestamp,
                $resource->get_id()
            );

            $booked_qty = count($existing_bookings);
            $remaining_qty = $available_qty - $booked_qty;

            if ($remaining_qty > 0) {
                $total_available += $remaining_qty;
            }
        }

        return [
            'available' => $total_available >= $quantity,
            'count'     => $total_available,
            'product'   => $product,
        ];
    }

    // If product has no resources
    $available_qty = $product->get_qty();
    $existing_bookings = $product->get_bookings_in_date_range($start_timestamp, $end_timestamp);
    $booked_qty = count($existing_bookings);

    $remaining_qty = $available_qty - $booked_qty;

    return [
        'available' => $remaining_qty >= $quantity,
        'count'     => max(0, $remaining_qty),
        'product'   => $product,
    ];
}

/**
 * ✅ Main dispatcher
 */
function ai_execute_action($action, $params) {
    $params = ai_normalize_dates($params);

    switch ($action) {
        case 'woocommerce/check_availability':
            return ai_handle_check_availability($params);

        case 'woocommerce/create_booking':
            return ai_handle_create_booking($params);

        case 'woocommerce/update_booking':
            return wp_json_encode(["success" => false, "data" => "🚧 Update booking handler not implemented yet."]);

        case 'woocommerce/cancel_booking':
            return wp_json_encode(["success" => false, "data" => "🚧 Cancel booking handler not implemented yet."]);

        case 'woocommerce/change_booking_status':
            return wp_json_encode(["success" => false, "data" => "🚧 Change booking status handler not implemented yet."]);

        case 'woocommerce/get_booking_details':
            return wp_json_encode(["success" => false, "data" => "🚧 Get booking details handler not implemented yet."]);

        default:
            return wp_json_encode(["success" => false, "data" => "❌ Unknown action: $action"]);
    }
}

/**
 * ✅ Handler: Check Availability
 */
function ai_handle_check_availability($params) {
    if (empty($params['product_id']) || empty($params['start_date'])) {
        return wp_json_encode([
            "success" => false,
            "data" => "❌ Missing required parameters: product_id and date."
        ]);
    }

    $product_id  = intval($params['product_id']);
    $resource_id = !empty($params['resource_id']) ? intval($params['resource_id']) : null;
    $quantity    = !empty($params['quantity']) ? intval($params['quantity']) : 1;

    $start_timestamp = strtotime($params['start_date']);
    $end_timestamp   = strtotime($params['end_date']);

    $result = ai_check_availability($product_id, $start_timestamp, $end_timestamp, $resource_id, $quantity);

    if (!empty($result['error'])) {
        return wp_json_encode(["success" => false, "data" => $result['error']]);
    }

    if (!$result['available']) {
        return wp_json_encode([
            "success" => true,
            "data" => "⚠️ No availability for <b>{$result['product']->get_name()}</b> from " .
                      date_i18n('M j, Y', $start_timestamp) . " to " .
                      date_i18n('M j, Y', $end_timestamp) . "."
        ]);
    }

    return wp_json_encode([
        "success" => true,
        "data" => "✅ Available slots for <b>{$result['product']->get_name()}</b> from " .
                  date_i18n('M j, Y', $start_timestamp) . " to " .
                  date_i18n('M j, Y', $end_timestamp) . ": <b>{$result['count']}</b>."
    ]);
}

/**
 * ✅ Handler: Create Booking
 */
function ai_handle_create_booking($params) {
    if (empty($params['product_id']) || empty($params['start_date']) || empty($params['end_date'])) {
        return wp_json_encode([
            "success" => false,
            "data" => "❌ Missing required parameters: product_id and dates."
        ]);
    }

    if (!function_exists('create_wc_booking')) {
        return wp_json_encode([
            "success" => false,
            "data" => "❌ WooCommerce Bookings function create_wc_booking() is not available."
        ]);
    }

    $product_id  = intval($params['product_id']);
    $resource_id = !empty($params['resource_id']) ? intval($params['resource_id']) : null;
    $quantity    = !empty($params['quantity']) ? intval($params['quantity']) : 1;

    $start_timestamp = strtotime($params['start_date']);
    $end_timestamp   = strtotime($params['end_date']);

    // ✅ Check availability before creating booking
    $result = ai_check_availability($product_id, $start_timestamp, $end_timestamp, $resource_id, $quantity);
    if (!empty($result['error'])) {
        return wp_json_encode(["success" => false, "data" => $result['error']]);
    }
    if (!$result['available']) {
        return wp_json_encode([
            "success" => false,
            "data" => "⚠️ No availability for <b>{$result['product']->get_name()}</b>. Booking not created."
        ]);
    }

    $status = !empty($params['status']) ? sanitize_text_field($params['status']) : 'pending-confirmation';
    $customer_id = !empty($params['customer_id']) ? intval($params['customer_id']) : 0;

    if (!$customer_id && !empty($params['email'])) {
        $user = get_user_by('email', sanitize_email($params['email']));
        if ($user) {
            $customer_id = $user->ID;
        }
    }

    $booking_data = [
        'start_date'  => $start_timestamp,
        'end_date'    => $end_timestamp,
        'resource_id' => $resource_id,
        'user_id'     => $customer_id,
        'all_day'     => true,
    ];

    $booking = create_wc_booking($product_id, $booking_data, $status, true);

    if (is_wp_error($booking)) {
        return wp_json_encode([
            "success" => false,
            "data" => "❌ Error creating booking: " . $booking->get_error_message()
        ]);
    }

    return wp_json_encode([
        "success" => true,
        "data" => "✅ Booking <b>#{$booking->get_id()}</b> created for <b>{$result['product']->get_name()}</b> from " .
                  date_i18n('M j, Y', $start_timestamp) . " to " .
                  date_i18n('M j, Y', $end_timestamp) . "."
    ]);
}
