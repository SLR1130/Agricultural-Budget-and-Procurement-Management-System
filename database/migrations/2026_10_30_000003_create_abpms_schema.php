<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 40)->default('procurement_officer')->after('password');
            $table->string('status', 20)->default('active')->after('role')->index();
            $table->string('phone', 40)->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->string('program_code', 80);
            $table->string('program_name');
            $table->string('funding_category', 120);
            $table->text('description');
            $table->decimal('authorized_amount', 14, 2);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['fiscal_period_id', 'program_code'], 'abpms_alloc_period_program_idx');
        });

        Schema::create('budget_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_allocation_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 30);
            $table->string('direction', 10);
            $table->decimal('amount', 14, 2);
            $table->string('reference')->unique();
            $table->text('description');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('procurement_request_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->timestamps();
            $table->index(['budget_allocation_id', 'entry_type']);
        });

        Schema::create('procurement_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('registration_reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('budget_allocation_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('requesting_office', 150);
            $table->text('purpose');
            $table->text('justification')->nullable();
            $table->decimal('estimated_total', 14, 2);
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['budget_allocation_id', 'status'], 'abpms_requests_allocation_status_idx');
        });

        Schema::create('procurement_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20)->default('supply');
            $table->string('description');
            $table->string('unit', 40)->default('unit');
            $table->decimal('quantity', 14, 2);
            $table->decimal('estimated_unit_price', 14, 2);
            $table->decimal('estimated_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order_number')->unique();
            $table->foreignId('procurement_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('procurement_suppliers')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_amount', 14, 2);
            $table->date('ordered_on')->nullable();
            $table->date('expected_delivery_on')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['procurement_request_id', 'status'], 'abpms_orders_request_status_idx');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procurement_request_item_id')->constrained()->restrictOnDelete();
            $table->string('item_type', 20)->default('supply');
            $table->string('description');
            $table->string('unit', 40)->default('unit');
            $table->decimal('ordered_quantity', 14, 2);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->decimal('received_quantity', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('procurement_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_reference')->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->date('received_on');
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });

        Schema::create('procurement_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_reference')->unique();
            $table->string('movement_type', 30);
            $table->string('item_description');
            $table->decimal('quantity', 14, 2);
            $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('procurement_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('procurement_receipt_items', function (Blueprint $table) {
            $table->foreign('inventory_movement_id')->references('id')->on('inventory_movements')->nullOnDelete();
        });

        Schema::create('supply_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->string('item_description');
            $table->decimal('quantity_received', 14, 2);
            $table->decimal('quantity_available', 14, 2);
            $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('procurement_receipt_id')->constrained()->restrictOnDelete();
            $table->date('expires_on')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->string('name');
            $table->date('acquired_on');
            $table->decimal('acquisition_cost', 14, 2);
            $table->string('purchase_order_reference')->nullable();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['procurement_request_id', 'created_at'], 'abpms_status_request_created_idx');
            $table->index(['purchase_order_id', 'created_at'], 'abpms_status_order_created_idx');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::table('budget_transactions', function (Blueprint $table) {
            $table->foreign('procurement_request_id')->references('id')->on('procurement_requests')->nullOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('procurement_status_histories');
        Schema::dropIfExists('equipment_assets');
        Schema::dropIfExists('supply_batches');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('procurement_receipt_items');
        Schema::dropIfExists('procurement_receipts');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('procurement_request_items');
        Schema::dropIfExists('procurement_requests');
        Schema::dropIfExists('procurement_suppliers');
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'phone', 'last_login_at']);
        });
    }
};
