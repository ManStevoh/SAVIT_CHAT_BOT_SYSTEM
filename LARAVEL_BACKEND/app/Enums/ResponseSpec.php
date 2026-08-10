<?php

namespace App\Enums;

enum ResponseSpec: string
{
    case CART_SUMMARY = 'cart_summary';
    case PROMPT_VARIANT_SELECTION = 'prompt_variant_selection';
    case PROMPT_DELIVERY_ADDRESS = 'prompt_delivery_address';
    case REPROMPT_DELIVERY_ADDRESS = 'reprompt_delivery_address';
    case PROMPT_ORDER_CONFIRMATION = 'prompt_order_confirmation';
    case PROMPT_PAYMENT_SELECTION = 'prompt_payment_selection';
    case PROMPT_MPESA_PHONE = 'prompt_mpesa_phone';
    case ORDER_RECEIPT_CONFIRMATION = 'order_receipt_confirmation';
    case PAYMENT_INSTRUCTIONS = 'payment_instructions';
    case MPESA_PUSH_SENT_NOTICE = 'mpesa_push_sent_notice';
    case CLARIFICATION_NEEDED = 'clarification_needed';
    case GENERAL_ASSIST = 'general_assist';
}
