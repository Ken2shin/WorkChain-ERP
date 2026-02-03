<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('account_number')->unique();
            $table->string('account_name');
            $table->string('account_type'); // asset, liability, equity, revenue, expense
            $table->string('account_subtype'); // current_asset, fixed_asset, etc
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'account_type']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('entry_number')->unique();
            $table->string('reference_type')->nullable(); // invoice, payment, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('entry_date');
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft, posted, voided
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->index(['tenant_id', 'entry_date']);
            $table->index('status');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('method_name'); // cash, check, credit_card, bank_transfer
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('payment_type'); // sales, purchase, expense
            $table->unsignedBigInteger('related_entity_id'); // invoice_id, po_id, etc
            $table->unsignedBigInteger('payment_method_id');
            $table->unsignedBigInteger('user_id');
            $table->string('payment_number')->unique();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('reference_number')->nullable();
            $table->string('status')->default('pending'); // pending, cleared, failed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->index(['tenant_id', 'payment_date']);
            $table->index('status');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id'); // account_id from chart_of_accounts
            $table->string('expense_number')->unique();
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending'); // pending, approved, rejected, paid
            $table->text('description')->nullable();
            $table->text('justification')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('category_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'expense_date']);
        });

        Schema::create('financial_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('report_type'); // balance_sheet, income_statement, cash_flow, trial_balance
            $table->date('report_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->json('data')->nullable();
            $table->unsignedBigInteger('generated_by');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('restrict');
            $table->index(['tenant_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reports');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
    }
};
