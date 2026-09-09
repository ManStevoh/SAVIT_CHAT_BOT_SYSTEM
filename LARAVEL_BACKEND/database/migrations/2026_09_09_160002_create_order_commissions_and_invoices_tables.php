<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commission_invoices')) {
            Schema::create('commission_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('invoice_number')->unique();
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedInteger('orders_count')->default(0);
                $table->decimal('gross_sales', 12, 2)->default(0.00);
                $table->decimal('amount_due', 10, 2)->default(0.00);
                $table->string('status')->default('unpaid')->index(); // unpaid, paid, overdue, waived
                $table->timestamp('due_date')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_reference')->nullable();
                $table->string('payment_method')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_commissions')) {
            Schema::create('order_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete()->unique();
                $table->decimal('order_total', 10, 2);
                $table->decimal('commission_rate', 5, 2);
                $table->decimal('commission_amount', 10, 2);
                $table->string('payment_method')->nullable();
                $table->string('collection_mode')->default('manual_direct')->index(); // automatic_split, manual_direct
                $table->string('settlement_status')->default('accrued')->index(); // settled, accrued, invoiced, paid, waived, refunded
                $table->timestamp('settled_at')->nullable();
                $table->foreignId('commission_invoice_id')->nullable()->constrained('commission_invoices')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_commissions');
        Schema::dropIfExists('commission_invoices');
    }
};
