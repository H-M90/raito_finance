<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_lead_tasks')) {
            Schema::table('sales_lead_tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_lead_tasks','description')) $table->text('description')->nullable()->after('title');
                if (! Schema::hasColumn('sales_lead_tasks','team')) $table->string('team',30)->default('sales')->after('description')->index('sales_task_team_idx');
            });
        }

        if (Schema::hasTable('customer_success_tasks')) {
            Schema::table('customer_success_tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('customer_success_tasks','team')) $table->string('team',30)->default('account_management')->after('description')->index('cs_task_team_idx');
                if (! Schema::hasColumn('customer_success_tasks','type')) $table->string('type',30)->default('customer_success')->after('team')->index('cs_task_type_idx');
                if (! Schema::hasColumn('customer_success_tasks','created_by')) $table->foreignId('created_by')->nullable()->after('completion_notes')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('internal_tasks')) {
            Schema::create('internal_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('title',200);
                $table->text('description')->nullable();
                $table->string('team',30)->default('sales')->index();
                $table->string('type',30)->default('internal')->index();
                $table->string('priority',20)->default('medium')->index();
                $table->dateTime('due_at')->index();
                $table->string('status',20)->default('open')->index();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['assigned_to','status','due_at'],'internal_task_queue_idx');
                $table->index(['team','status','due_at'],'internal_task_team_queue_idx');
            });
        }

        if (Schema::hasTable('permissions')) {
            $permissions = [
                'tasks.view' => 'عرض مركز المهام',
                'tasks.create' => 'إضافة مهام',
                'tasks.update' => 'تعديل المهام',
                'tasks.complete' => 'إكمال وإعادة فتح المهام',
                'tasks.assign' => 'إسناد المهام لمستخدمين آخرين',
            ];
            foreach ($permissions as $code=>$name) {
                DB::table('permissions')->updateOrInsert(['code'=>$code],[
                    'name'=>$name,'group_name'=>'tasks','created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            if (Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
                $permissionIds = DB::table('permissions')->whereIn('code',array_keys($permissions))->pluck('id');
                foreach (DB::table('roles')->whereIn('code',['sales','customer-success'])->pluck('id') as $roleId) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_role')->insertOrIgnore(['role_id'=>$roleId,'permission_id'=>$permissionId]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('permission_role')) {
            $codes=['tasks.view','tasks.create','tasks.update','tasks.complete','tasks.assign'];
            $ids=DB::table('permissions')->whereIn('code',$codes)->pluck('id');
            DB::table('permission_role')->whereIn('permission_id',$ids)->delete();
            DB::table('permissions')->whereIn('id',$ids)->delete();
        }
        Schema::dropIfExists('internal_tasks');
        if (Schema::hasTable('customer_success_tasks')) {
            Schema::table('customer_success_tasks', function (Blueprint $table) {
                if (Schema::hasColumn('customer_success_tasks','created_by')) $table->dropConstrainedForeignId('created_by');
                if (Schema::hasColumn('customer_success_tasks','type')) $table->dropColumn('type');
                if (Schema::hasColumn('customer_success_tasks','team')) $table->dropColumn('team');
            });
        }
        if (Schema::hasTable('sales_lead_tasks')) {
            Schema::table('sales_lead_tasks', function (Blueprint $table) {
                if (Schema::hasColumn('sales_lead_tasks','team')) $table->dropColumn('team');
                if (Schema::hasColumn('sales_lead_tasks','description')) $table->dropColumn('description');
            });
        }
    }
};
