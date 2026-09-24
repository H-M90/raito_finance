<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_success_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('default_attention', 20)->default('normal');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('customer_success_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('lifecycle_status_id')->nullable()->constrained('customer_success_statuses')->nullOnDelete();
            $table->string('health', 30)->default('NEUTRAL')->index();
            $table->string('attention_level', 20)->default('normal')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('next_review_at')->nullable()->index();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('go_live_at')->nullable();
            $table->dateTime('stabilized_at')->nullable();
            $table->dateTime('churned_at')->nullable();
            $table->dateTime('last_health_change_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['lifecycle_status_id', 'attention_level']);
        });

        Schema::create('customer_attention_flag_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 160);
            $table->string('default_severity', 20)->default('medium');
            $table->unsignedSmallInteger('default_days')->nullable();
            $table->string('source', 40)->default('manual');
            $table->string('playbook_code', 60)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('customer_playbooks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 180);
            $table->string('trigger_type', 30)->index();
            $table->string('trigger_code', 60)->index();
            $table->string('owner_role', 80)->nullable();
            $table->unsignedInteger('sla_hours')->nullable();
            $table->text('exit_criteria')->nullable();
            $table->string('result_code', 60)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['trigger_type', 'trigger_code']);
        });

        Schema::create('customer_playbook_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playbook_id')->constrained('customer_playbooks')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('due_offset_hours')->nullable();
            $table->string('default_priority', 20)->default('normal');
            $table->timestamps();
            $table->unique(['playbook_id', 'sort_order']);
        });

        Schema::create('customer_playbook_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playbook_id')->constrained('customer_playbooks')->restrictOnDelete();
            $table->string('trigger_type', 30);
            $table->string('trigger_code', 60);
            $table->unsignedBigInteger('trigger_id')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result_code', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['trigger_type', 'trigger_code', 'trigger_id']);
        });

        Schema::create('customer_attention_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flag_type_id')->constrained('customer_attention_flag_types')->restrictOnDelete();
            $table->string('severity', 20)->default('medium')->index();
            $table->string('source', 40)->default('manual')->index();
            $table->string('status', 20)->default('open')->index();
            $table->dateTime('opened_at')->useCurrent();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('playbook_run_id')->nullable()->constrained('customer_playbook_runs')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status', 'severity']);
        });

        Schema::create('customer_commercial_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60)->index();
            $table->string('status', 20)->default('active')->index();
            $table->string('source', 40)->default('manual');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->dateTime('due_at')->nullable()->index();
            $table->foreignId('playbook_run_id')->nullable()->constrained('customer_playbook_runs')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status', 'code']);
        });

        Schema::create('customer_success_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playbook_run_id')->nullable()->constrained('customer_playbook_runs')->cascadeOnDelete();
            $table->foreignId('playbook_step_id')->nullable()->constrained('customer_playbook_steps')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_notes')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'status', 'due_at']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('customer_success_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40)->index();
            $table->string('event_code', 80)->index();
            $table->nullableMorphs('source');
            $table->dateTime('occurred_at')->useCurrent()->index();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at')->nullable()->index();
            $table->timestamps();
            $table->index(['customer_id', 'occurred_at']);
        });

        Schema::create('customer_success_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('customer_success_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('customer_success_statuses')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['customer_id', 'changed_at']);
        });

        Schema::table('customer_contacts', function (Blueprint $table) {
            $table->string('role_code', 40)->nullable()->after('name')->index();
            $table->string('email')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('is_primary')->index();
            $table->date('started_at')->nullable()->after('is_active');
            $table->date('ended_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_contacts', function (Blueprint $table) {
            $table->dropIndex(['role_code']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['role_code', 'email', 'is_active', 'started_at', 'ended_at']);
        });
        Schema::dropIfExists('customer_success_status_history');
        Schema::dropIfExists('customer_success_events');
        Schema::dropIfExists('customer_success_tasks');
        Schema::dropIfExists('customer_commercial_signals');
        Schema::dropIfExists('customer_attention_flags');
        Schema::dropIfExists('customer_playbook_runs');
        Schema::dropIfExists('customer_playbook_steps');
        Schema::dropIfExists('customer_playbooks');
        Schema::dropIfExists('customer_attention_flag_types');
        Schema::dropIfExists('customer_success_profiles');
        Schema::dropIfExists('customer_success_statuses');
    }
};
