<?php

namespace App\Enums;

enum CommerceIntent: string
{
    // Phase 1 Short-Circuit Target Intents
    case ADD_TO_CART = 'add_to_cart';
    case SELECT_OPTION = 'select_option';
    case REMOVE_FROM_CART = 'remove_from_cart';
    case UPDATE_QUANTITY = 'update_quantity';

    // FSM & Existing Orchestrator Handled Intents (Preserved)
    case START_CHECKOUT = 'start_checkout';
    case PROVIDE_ADDRESS = 'provide_address';
    case CHOOSE_PICKUP = 'choose_pickup';
    case CHOOSE_DINE_IN = 'choose_dine_in';
    case CONFIRM_ORDER = 'confirm_order';
    case CHOOSE_PAYMENT_METHOD = 'choose_payment_method';
    case PROVIDE_PHONE = 'provide_phone';

    // Inquiries & General
    case ASK_DELIVERY_FEE = 'ask_delivery_fee';
    case ASK_STORE_LOCATION = 'ask_store_location';
    case ASK_FAQ = 'ask_faq';
    case ASK_PRODUCT_INFO = 'ask_product_info';
    case ASK_PRICE = 'ask_price';
    case ASK_ORDER_STATUS = 'ask_order_status';
    case CANCEL_ORDER = 'cancel_order';
    case REQUEST_HUMAN = 'request_human';
    case GENERAL_CHAT = 'general_chat';
    case UNKNOWN = 'unknown';

    public function isPhase1Eligible(): bool
    {
        return in_array($this, [
            self::ADD_TO_CART,
            self::REMOVE_FROM_CART,
            self::UPDATE_QUANTITY,
        ], true);
    }
}
