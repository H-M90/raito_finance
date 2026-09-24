<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_statement_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('currency', 3)->default('SAR')->index();
            $table->string('sheet_name')->nullable();
            $table->unsignedSmallInteger('header_row')->default(1);
            $table->json('mapping')->nullable();
            $table->json('detected_headers')->nullable();
            $table->json('preview_rows')->nullable();
            $table->string('status', 20)->default('mapping')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_statement_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->date('transaction_date')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('reference_no')->nullable()->index();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('direction', 10)->nullable()->index();
            $table->string('classification', 20)->default('unclassified')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('expense_description')->nullable();
            $table->string('beneficiary')->nullable();
            $table->foreignId('expense_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('expense_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('collection_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->text('collection_notes')->nullable();
            $table->json('allocation_data')->nullable();
            $table->json('source_payload')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('generated_type')->nullable();
            $table->unsignedBigInteger('generated_id')->nullable();
            $table->timestamps();
            $table->unique(['bank_statement_batch_id', 'row_number'], 'bank_statement_batch_row_unique');
            $table->index(['generated_type', 'generated_id'], 'bank_statement_generated_idx');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('bank_statement_row_id')->nullable()->unique()->after('customer_id')->constrained('bank_statement_rows')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bank_statement_row_id')->nullable()->unique()->after('expense_category_id')->constrained('bank_statement_rows')->nullOnDelete();
        });

        $permissionCodes=['bank-statements.view','bank-statements.create','bank-statements.update','bank-statements.approve','bank-statements.delete'];
        foreach($permissionCodes as $code){
            DB::table('permissions')->updateOrInsert(['code'=>$code],['name'=>str_replace('.',' ',$code),'group_name'=>'bank-statements','created_at'=>now(),'updated_at'=>now()]);
        }
        $permissionIds=DB::table('permissions')->whereIn('code',$permissionCodes)->pluck('id');
        foreach(DB::table('roles')->whereIn('code',['admin','finance'])->pluck('id') as $roleId){
            foreach($permissionIds as $permissionId) DB::table('permission_role')->insertOrIgnore(['role_id'=>$roleId,'permission_id'=>$permissionId]);
        }
        $viewerId=DB::table('roles')->where('code','viewer')->value('id');
        $viewId=DB::table('permissions')->where('code','bank-statements.view')->value('id');
        if($viewerId&&$viewId)DB::table('permission_role')->insertOrIgnore(['role_id'=>$viewerId,'permission_id'=>$viewId]);
    }

    public function down(): void
    {
        $permissionIds=DB::table('permissions')->where('group_name','bank-statements')->pluck('id');
        if($permissionIds->isNotEmpty())DB::table('permission_role')->whereIn('permission_id',$permissionIds)->delete();
        DB::table('permissions')->where('group_name','bank-statements')->delete();
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('bank_statement_row_id'));
        Schema::table('collections', fn (Blueprint $table) => $table->dropConstrainedForeignId('bank_statement_row_id'));
        Schema::dropIfExists('bank_statement_rows');
        Schema::dropIfExists('bank_statement_batches');
    }
};
