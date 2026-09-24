<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_leads', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('city', 100)->nullable();
            $table->string('sector', 120)->nullable()->index();
            $table->string('source', 60)->nullable()->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 30)->default('lead')->index();
            $table->string('rating', 20)->default('medium')->index();
            $table->boolean('responded')->default(false)->index();
            $table->string('qualification', 20)->default('evaluating')->index();
            $table->string('priority', 20)->default('medium')->index();
            $table->boolean('favorite')->default(false)->index();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->string('next_follow_up_type', 30)->nullable();
            $table->string('next_follow_up_priority', 20)->nullable();
            $table->string('next_follow_up_title', 200)->nullable();
            $table->dateTime('last_activity_at')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->unique()->constrained('customers')->nullOnDelete();
            $table->dateTime('converted_at')->nullable()->index();
            $table->text('lost_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_id', 'stage'], 'sales_leads_owner_stage_idx');
            $table->index(['stage', 'qualification', 'rating'], 'sales_leads_pipeline_idx');
            $table->index(['next_follow_up_at', 'stage'], 'sales_leads_followup_idx');
        });

        Schema::create('sales_lead_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->string('name', 160);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['sales_lead_id', 'name'], 'sales_lead_interest_unique');
        });

        Schema::create('sales_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->string('type', 50)->index();
            $table->text('description');
            $table->dateTime('occurred_at')->useCurrent()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sales_lead_id', 'occurred_at'], 'sales_lead_activity_time_idx');
        });

        Schema::create('sales_lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sales_lead_id', 'created_at'], 'sales_lead_note_time_idx');
        });

        Schema::create('sales_lead_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('type', 30)->default('call')->index();
            $table->string('priority', 20)->default('medium')->index();
            $table->dateTime('due_at')->index();
            $table->string('status', 20)->default('open')->index();
            $table->boolean('is_follow_up')->default(false)->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['assigned_to', 'status', 'due_at'], 'sales_lead_task_queue_idx');
            $table->index(['sales_lead_id', 'status'], 'sales_lead_task_status_idx');
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
            $permissions = [
                'sales-leads.view' => 'عرض العملاء المحتملين ورحلة المبيعات',
                'sales-leads.create' => 'إضافة عميل محتمل',
                'sales-leads.update' => 'تحديث رحلة العميل المحتمل',
                'sales-leads.assign' => 'تعيين مسؤول للعميل المحتمل',
                'sales-leads.convert' => 'تحويل العميل المحتمل إلى عميل',
                'sales-leads.tasks' => 'إدارة مهام ومتابعات المبيعات',
            ];
            foreach ($permissions as $code => $name) {
                DB::table('permissions')->updateOrInsert(
                    ['code' => $code],
                    ['name' => $name, 'group_name' => 'sales-leads', 'created_at' => now(), 'updated_at' => now()]
                );
            }
            $permissionIds = DB::table('permissions')->whereIn('code', array_keys($permissions))->pluck('id');
            $salesRoleId = DB::table('roles')->where('code', 'sales')->value('id');
            if ($salesRoleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore(['role_id' => $salesRoleId, 'permission_id' => $permissionId]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('permission_role')) {
            $codes = ['sales-leads.view','sales-leads.create','sales-leads.update','sales-leads.assign','sales-leads.convert','sales-leads.tasks'];
            $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }
        Schema::dropIfExists('sales_lead_tasks');
        Schema::dropIfExists('sales_lead_notes');
        Schema::dropIfExists('sales_lead_activities');
        Schema::dropIfExists('sales_lead_interests');
        Schema::dropIfExists('sales_leads');
    }
};
