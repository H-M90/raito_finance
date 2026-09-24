<?php

use App\Http\Controllers\AddendumController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankStatementController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\CustomerSuccessController;
use App\Http\Controllers\CustomerSuccessDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountVoucherController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\IntermediaryCommissionController;
use App\Http\Controllers\IntermediaryController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\PricingOfferController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SalesLeadController;
use App\Http\Controllers\TaskCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
});
Route::middleware(['auth', 'active-session', 'own-records'])->group(function () {
    Route::get('/', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::prefix('lookup')->name('lookup.')->group(function () {
        Route::get('customers', [LookupController::class, 'customers'])->middleware('permission:customers.view')->name('customers');
        Route::get('products', [LookupController::class, 'products'])->middleware('permission:products.view')->name('products');
        Route::get('contracts', [LookupController::class, 'contracts'])->middleware('permission:contracts.view')->name('contracts');
        Route::get('stations', [LookupController::class, 'stations'])->middleware('permission:stations.view')->name('stations');
    });

    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', [TaskCenterController::class, 'index'])->middleware('permission:tasks.view')->name('index');
        Route::get('/targets', [TaskCenterController::class, 'targets'])->middleware('permission:tasks.create')->name('targets');
        Route::post('/', [TaskCenterController::class, 'store'])->middleware('permission:tasks.create')->name('store');
        Route::put('/{source}/{task}', [TaskCenterController::class, 'update'])->middleware('permission:tasks.update')->name('update');
        Route::post('/{source}/{task}/complete', [TaskCenterController::class, 'complete'])->middleware('permission:tasks.complete')->name('complete');
        Route::post('/{source}/{task}/reopen', [TaskCenterController::class, 'reopen'])->middleware('permission:tasks.complete')->name('reopen');
    });

    Route::prefix('sales')->name('sales-leads.')->group(function () {
        Route::get('/leads', [SalesLeadController::class, 'index'])->middleware('permission:sales-leads.view')->name('index');
        Route::get('/leads/tasks', [SalesLeadController::class, 'tasks'])->middleware('permission:sales-leads.tasks')->name('tasks');
        Route::get('/leads/create', [SalesLeadController::class, 'create'])->middleware('permission:sales-leads.create')->name('create');
        Route::post('/leads', [SalesLeadController::class, 'store'])->middleware('permission:sales-leads.create')->name('store');
        Route::post('/leads/bulk-update', [SalesLeadController::class, 'bulkUpdate'])->middleware('permission:sales-leads.update')->name('bulk-update');
        Route::get('/leads/{salesLead}', [SalesLeadController::class, 'show'])->middleware('permission:sales-leads.view')->name('show');
        Route::get('/leads/{salesLead}/edit', [SalesLeadController::class, 'edit'])->middleware('permission:sales-leads.update')->name('edit');
        Route::put('/leads/{salesLead}', [SalesLeadController::class, 'update'])->middleware('permission:sales-leads.update')->name('update');
        Route::post('/leads/{salesLead}/notes', [SalesLeadController::class, 'addNote'])->middleware('permission:sales-leads.update')->name('notes.store');
        Route::post('/leads/{salesLead}/interests', [SalesLeadController::class, 'addInterest'])->middleware('permission:sales-leads.update')->name('interests.store');
        Route::delete('/leads/{salesLead}/interests/{interest}', [SalesLeadController::class, 'removeInterest'])->middleware('permission:sales-leads.update')->name('interests.destroy');
        Route::post('/leads/{salesLead}/tasks', [SalesLeadController::class, 'addTask'])->middleware('permission:sales-leads.tasks')->name('tasks.store');
        Route::post('/leads/{salesLead}/tasks/{task}/complete', [SalesLeadController::class, 'completeTask'])->middleware('permission:sales-leads.tasks')->name('tasks.complete');
        Route::post('/leads/{salesLead}/convert', [SalesLeadController::class, 'convert'])->middleware('permission:sales-leads.convert')->name('convert');
    });

    Route::post('/customers/quick', [CustomerController::class, 'quickStore'])->middleware('permission:customers.create')->name('customers.quick-store');
    Route::get('/customers/{customer}/statement', [StatementController::class, 'show'])->middleware('permission:reports.statements')->name('customers.statement');
    Route::get('/customers/{customer}/statement/print', [StatementController::class, 'print'])->middleware('permission:reports.exports')->name('customers.statement.print');
    Route::get('/customers/{customer}/statement/excel', [StatementController::class, 'excel'])->middleware('permission:reports.exports')->name('customers.statement.excel');
    Route::get('/customers/{customer}/statement/pdf', [StatementController::class, 'pdf'])->middleware('permission:reports.exports')->name('customers.statement.pdf');
    Route::get('/customers/{customer}/history', CustomerHistoryController::class)->middleware('permission:customers.view')->name('customers.history');
    Route::resource('customers', CustomerController::class)->except(['destroy'])->middlewareFor(['index', 'show'], 'permission:customers.view')->middlewareFor(['create', 'store'], 'permission:customers.create')->middlewareFor(['edit', 'update'], 'permission:customers.update');

    Route::resource('products', ProductController::class)->except(['show', 'destroy'])->middlewareFor('index', 'permission:products.view')->middlewareFor(['create', 'store'], 'permission:products.create')->middlewareFor(['edit', 'update'], 'permission:products.update');
    Route::resource('pricing-offers', PricingOfferController::class)->except(['show', 'destroy'])->middlewareFor('index', 'permission:pricing-offers.view')->middlewareFor(['create', 'store'], 'permission:pricing-offers.create')->middlewareFor(['edit', 'update'], 'permission:pricing-offers.update');

    Route::get('/quotations/report', [QuotationController::class, 'report'])->middleware('permission:quotations.reports')->name('quotations.report');
    Route::post('/quotations/{quotation}/revise', [QuotationController::class, 'revise'])->middleware('permission:quotations.update')->name('quotations.revise');
    Route::patch('/quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->middleware('permission:quotations.status')->name('quotations.status');
    Route::resource('quotations', QuotationController::class)->except(['destroy'])->middlewareFor(['index', 'show'], 'permission:quotations.view')->middlewareFor(['create', 'store'], 'permission:quotations.create')->middlewareFor(['edit', 'update'], 'permission:quotations.update');

    Route::resource('contracts', ContractController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])->middlewareFor(['index', 'show'], 'permission:contracts.view')->middlewareFor(['create', 'store'], 'permission:contracts.create')->middlewareFor(['edit', 'update'], 'permission:contracts.update');
    Route::post('/contracts/{contract}/cancel', [ContractController::class, 'cancel'])->middleware('permission:contracts.cancel')->name('contracts.cancel');
    Route::post('/contracts/{contract}/reopen', [ContractController::class, 'reopen'])->middleware('permission:contracts.reopen')->name('contracts.reopen');
    Route::post('/contracts/{contract}/generate-historical', [ContractController::class, 'generateHistorical'])->middleware('permission:contracts.update')->name('contracts.generate-historical');
    Route::post('/contracts/{contract}/items/{item}/stop', [ContractController::class, 'stopItem'])->middleware('permission:contracts.update')->name('contracts.items.stop');
    Route::put('/contracts/{contract}/items/{item}', [ContractController::class, 'updateItem'])->middleware('permission:contracts.update')->name('contracts.items.update');
    Route::post('/contracts/{contract}/items/{item}/reopen', [ContractController::class, 'reopenItem'])->middleware('permission:contracts.update')->name('contracts.items.reopen');
    Route::post('/contracts/{contract}/maintenance-setting', [ContractController::class, 'updateMaintenance'])->middleware('permission:contracts.update')->name('contracts.maintenance-setting');
    Route::get('/contracts/{contract}/addendums/create', [AddendumController::class, 'create'])->middleware('permission:addendums.create')->name('addendums.create');
    Route::post('/contracts/{contract}/addendums', [AddendumController::class, 'store'])->middleware('permission:addendums.create')->name('addendums.store');
    Route::get('/contracts/{contract}/addendums/{addendum}', [AddendumController::class, 'show'])->middleware('permission:addendums.view')->name('addendums.show');
    Route::get('/contracts/{contract}/addendums/{addendum}/edit', [AddendumController::class, 'edit'])->middleware('permission:addendums.update')->name('addendums.edit');
    Route::put('/contracts/{contract}/addendums/{addendum}', [AddendumController::class, 'update'])->middleware('permission:addendums.update')->name('addendums.update');
    Route::post('/contracts/{contract}/addendums/{addendum}/cancel', [AddendumController::class, 'cancel'])->middleware('permission:addendums.cancel')->name('addendums.cancel');
    Route::post('/contracts/{contract}/addendums/{addendum}/reopen', [AddendumController::class, 'reopen'])->middleware('permission:addendums.reopen')->name('addendums.reopen');

    Route::resource('stations', StationController::class)->only(['index', 'edit', 'update'])->middlewareFor('index', 'permission:stations.view')->middlewareFor(['edit', 'update'], 'permission:stations.update');
    Route::resource('installations', InstallationController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])->middlewareFor(['index', 'show'], 'permission:installations.view')->middlewareFor(['create', 'store'], 'permission:installations.create')->middlewareFor(['edit', 'update'], 'permission:installations.update');
    Route::post('/installations/{installation}/cancel', [InstallationController::class, 'cancel'])->middleware('permission:installations.cancel')->name('installations.cancel');
    Route::post('/installations/{installation}/reopen', [InstallationController::class, 'reopen'])->middleware('permission:installations.reopen')->name('installations.reopen');

    Route::get('/receivables/{receivable}/payment', [ReceivableController::class, 'paymentCreate'])->middleware('permission:collections.create')->name('receivables.payment.create');
    Route::post('/receivables/{receivable}/payment', [ReceivableController::class, 'paymentStore'])->middleware('permission:collections.create')->name('receivables.payment.store');
    Route::resource('receivables', ReceivableController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middlewareFor('index', 'permission:receivables.view')->middlewareFor(['create', 'store'], 'permission:receivables.create')->middlewareFor(['edit', 'update'], 'permission:receivables.update');
    Route::get('/collections/outstanding', [CollectionController::class, 'outstanding'])->middleware('permission:collections.view')->name('collections.outstanding');
    Route::resource('collections', CollectionController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middlewareFor('index', 'permission:collections.view')->middlewareFor(['create', 'store'], 'permission:collections.create')->middlewareFor(['edit', 'update'], 'permission:collections.update');
    Route::post('/collections/{collection}/cancel', [CollectionController::class, 'cancel'])->middleware('permission:collections.cancel')->name('collections.cancel');
    Route::post('/collections/{collection}/reopen', [CollectionController::class, 'reopen'])->middleware('permission:collections.reopen')->name('collections.reopen');
    Route::resource('discount-vouchers', DiscountVoucherController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middlewareFor('index', 'permission:discount-vouchers.view')->middlewareFor(['create', 'store'], 'permission:discount-vouchers.create')->middlewareFor(['edit', 'update'], 'permission:discount-vouchers.update');
    Route::post('/discount-vouchers/{discountVoucher}/cancel', [DiscountVoucherController::class, 'cancel'])->middleware('permission:discount-vouchers.cancel')->name('discount-vouchers.cancel');
    Route::post('/discount-vouchers/{discountVoucher}/reopen', [DiscountVoucherController::class, 'reopen'])->middleware('permission:discount-vouchers.reopen')->name('discount-vouchers.reopen');

    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])->middlewareFor(['index', 'show'], 'permission:purchases.view')->middlewareFor(['create', 'store'], 'permission:purchases.create')->middlewareFor(['edit', 'update'], 'permission:purchases.update')->middlewareFor('destroy', 'permission:purchases.delete');
    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->middlewareFor('index', 'permission:expenses.view')->middlewareFor(['create', 'store'], 'permission:expenses.create')->middlewareFor(['edit', 'update'], 'permission:expenses.update')->middlewareFor('destroy', 'permission:expenses.delete');
    Route::post('/expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->middleware('permission:expenses.cancel')->name('expenses.cancel');
    Route::post('/expenses/{expense}/reopen', [ExpenseController::class, 'reopen'])->middleware('permission:expenses.reopen')->name('expenses.reopen');
    Route::prefix('bank-statements')->name('bank-statements.')->group(function () {
        Route::get('/', [BankStatementController::class, 'index'])->middleware('permission:bank-statements.view')->name('index');
        Route::post('/', [BankStatementController::class, 'upload'])->middleware('permission:bank-statements.create')->name('upload');
        Route::get('/{batch}/mapping', [BankStatementController::class, 'mapping'])->middleware('permission:bank-statements.update')->name('mapping');
        Route::post('/{batch}/parse', [BankStatementController::class, 'parse'])->middleware('permission:bank-statements.update')->name('parse');
        Route::get('/{batch}', [BankStatementController::class, 'show'])->middleware('permission:bank-statements.view')->name('show');
        Route::put('/{batch}/rows/{row}', [BankStatementController::class, 'updateRow'])->middleware('permission:bank-statements.update')->name('rows.update');
        Route::get('/{batch}/outstanding', [BankStatementController::class, 'outstanding'])->middleware('permission:bank-statements.update')->name('outstanding');
        Route::post('/{batch}/approve', [BankStatementController::class, 'approve'])->middleware('permission:bank-statements.approve')->name('approve');
        Route::get('/{batch}/original', [BankStatementController::class, 'original'])->middleware('permission:bank-statements.view')->name('original');
        Route::delete('/{batch}', [BankStatementController::class, 'destroy'])->middleware('permission:bank-statements.delete')->name('destroy');
    });
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update'])->middlewareFor('index', 'permission:suppliers.view')->middlewareFor('store', 'permission:suppliers.create')->middlewareFor('update', 'permission:suppliers.update');
    Route::post('/intermediaries/quick', [IntermediaryController::class, 'quickStore'])->middleware('permission:intermediaries.create')->name('intermediaries.quick-store');
    Route::resource('intermediaries', IntermediaryController::class)->only(['index', 'store', 'update'])->middlewareFor('index', 'permission:intermediaries.view')->middlewareFor('store', 'permission:intermediaries.create')->middlewareFor('update', 'permission:intermediaries.update');
    Route::get('/intermediary-commissions', [IntermediaryCommissionController::class, 'index'])->middleware('permission:intermediaries.commissions')->name('intermediary-commissions.index');
    Route::post('/intermediary-commissions/{commission}/pay', [IntermediaryCommissionController::class, 'pay'])->middleware('permission:intermediaries.pay-commissions')->name('intermediary-commissions.pay');


    Route::get('/customer-follow-up', CustomerSuccessDashboardController::class)->middleware('permission:customer-success.dashboard')->name('customer-success.dashboard');
    // Compatibility link: the customer-specific 360 view now lives inside the customer file itself.
    Route::get('/customer-success/customers/{customer}', [CustomerSuccessController::class, 'show'])->middleware('permission:customer-success.view')->name('customer-success.show');
    Route::prefix('customers/{customer}/follow-up')->name('customer-success.')->group(function () {
        Route::put('/profile', [CustomerSuccessController::class, 'updateProfile'])->middleware('permission:customer-success.update')->name('profile.update');
        Route::post('/stage', [CustomerSuccessController::class, 'transition'])->middleware('permission:customer-success.update')->name('transition');
        Route::post('/satisfaction', [CustomerSuccessController::class, 'health'])->middleware('permission:customer-success.health.update')->name('health');
        Route::post('/next-follow-up', [CustomerSuccessController::class, 'storeFollowUp'])->middleware('permission:customer-success.update')->name('followups.store');
        Route::post('/alerts', [CustomerSuccessController::class, 'storeFlag'])->middleware('permission:customer-success.flags.manage')->name('flags.store');
        Route::post('/alerts/{flag}/resolve', [CustomerSuccessController::class, 'resolveFlag'])->middleware('permission:customer-success.flags.manage')->name('flags.resolve');
        Route::post('/opportunities', [CustomerSuccessController::class, 'storeSignal'])->middleware('permission:customer-success.signals.manage')->name('signals.store');
        Route::post('/opportunities/{signal}/close', [CustomerSuccessController::class, 'closeSignal'])->middleware('permission:customer-success.signals.manage')->name('signals.close');
        Route::post('/contacts', [CustomerSuccessController::class, 'storeContact'])->middleware('permission:customer-success.contacts.manage')->name('contacts.store');
        Route::post('/plans', [CustomerSuccessController::class, 'runPlaybook'])->middleware('permission:customer-success.playbooks.run')->name('playbooks.run');
        Route::post('/tasks/{task}/complete', [CustomerSuccessController::class, 'completeTask'])->middleware('permission:customer-success.tasks.complete')->name('tasks.complete');
        Route::put('/tasks/{task}/assign', [CustomerSuccessController::class, 'assignTask'])->middleware('permission:customer-success.tasks.assign')->name('tasks.assign');
        Route::post('/plans/{run}/complete', [CustomerSuccessController::class, 'completeRun'])->middleware('permission:customer-success.playbooks.manage')->name('runs.complete');
    });

    Route::get('/reports/customer-products-due', [ReportController::class, 'customerProductsDue'])->middleware('permission:reports.contracts')->name('reports.customer-products-due');
    Route::post('/reports/customer-products-due/generate', [ReportController::class, 'generateCustomerProductsDue'])->middleware('permission:contracts.update')->name('reports.customer-products-due.generate');
    Route::get('/reports/profitability', [ReportController::class, 'profitability'])->middleware('permission:reports.profitability')->name('reports.profitability');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:reports.audit')->name('audit.index');
    Route::get('/attachments/{attachment}', [DocumentController::class, 'download'])->middleware('permission:attachments.download')->name('attachments.download');
    Route::delete('/attachments/{attachment}', [DocumentController::class, 'destroy'])->middleware('permission:attachments.delete')->name('attachments.destroy');

    Route::prefix('imports')->name('imports.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->middleware('permission:imports.view')->name('index');
        Route::get('/template', [ImportController::class, 'template'])->middleware('permission:imports.view')->name('template');
        Route::post('/', [ImportController::class, 'upload'])->middleware('permission:imports.create')->name('upload');
        Route::get('/{batch}/mapping', [ImportController::class, 'mapping'])->middleware('permission:imports.create')->name('mapping');
        Route::put('/{batch}/mapping', [ImportController::class, 'updateMapping'])->middleware('permission:imports.create')->name('mapping.update');
        Route::get('/{batch}', [ImportController::class, 'show'])->middleware('permission:imports.view')->name('show');
        Route::get('/{batch}/status', [ImportController::class, 'status'])->middleware('permission:imports.view')->name('status');
        Route::get('/{batch}/errors', [ImportController::class, 'errorFile'])->middleware('permission:imports.view')->name('errors');
        Route::put('/{batch}/rows/{row}', [ImportController::class, 'updateRow'])->middleware('permission:imports.create')->name('rows.update');
        Route::post('/{batch}/commit', [ImportController::class, 'commit'])->middleware('permission:imports.commit')->name('commit');
        Route::post('/{batch}/rollback', [ImportController::class, 'rollback'])->middleware('permission:imports.cancel')->name('rollback');
        Route::delete('/{batch}', [ImportController::class, 'destroy'])->middleware('permission:imports.cancel')->name('destroy');
    });

    Route::get('/admin/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('admin.users.index');
    Route::post('/admin/users',[UserController::class, 'store'])->middleware('permission:users.create')->name('admin.users.store');
    Route::put('/admin/users/{user}',[UserController::class, 'update'])->middleware('permission:users.update')->name('admin.users.update');
    Route::get('/admin/roles',[RoleController::class, 'index'])->middleware('permission:users.roles')->name('admin.roles.index');
    Route::put('/admin/roles/{role}',[RoleController::class, 'update'])->middleware('permission:users.roles')->name('admin.roles.update');
    Route::post('/logout',[AuthController::class, 'logout'])->name('logout');
});
