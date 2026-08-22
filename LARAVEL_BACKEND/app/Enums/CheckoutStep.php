<?php

namespace App\Enums;

enum CheckoutStep: string
{
    case IDLE = 'idle';
    case BUILDING_CART = 'product';
    case SELECTING_VARIANT = 'variant';
    case SPECIFYING_QUANTITY = 'product_qty';
    case COLLECTING_ADDRESS = 'address';
    case REVIEWING_ORDER = 'confirm';
    case SELECTING_PAYMENT_METHOD = 'payment_method';
    case PROVIDING_PHONE = 'mpesa_phone';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case ORDER_COMPLETED = 'order_completed';
    case TRACKING_ACTIONS = 'tracking_actions';

    public static function fromLegacyStep(?string $step): self
    {
        if ($step === null || $step === '') {
            return self::IDLE;
        }

        return match ($step) {
            'product' => self::BUILDING_CART,
            'variant' => self::SELECTING_VARIANT,
            'product_qty' => self::SPECIFYING_QUANTITY,
            'address' => self::COLLECTING_ADDRESS,
            'confirm' => self::REVIEWING_ORDER,
            'payment_method' => self::SELECTING_PAYMENT_METHOD,
            'mpesa_phone' => self::PROVIDING_PHONE,
            'awaiting_payment' => self::AWAITING_PAYMENT,
            'order_completed' => self::ORDER_COMPLETED,
            'tracking_actions' => self::TRACKING_ACTIONS,
            default => self::IDLE,
        };
    }

    public function toLegacyStep(): ?string
    {
        return match ($this) {
            self::IDLE => null,
            self::BUILDING_CART => 'product',
            self::SELECTING_VARIANT => 'variant',
            self::SPECIFYING_QUANTITY => 'product_qty',
            self::COLLECTING_ADDRESS => 'address',
            self::REVIEWING_ORDER => 'confirm',
            self::SELECTING_PAYMENT_METHOD => 'payment_method',
            self::PROVIDING_PHONE => 'mpesa_phone',
            self::AWAITING_PAYMENT => 'awaiting_payment',
            self::ORDER_COMPLETED => 'order_completed',
            self::TRACKING_ACTIONS => 'tracking_actions',
        };
    }
}
