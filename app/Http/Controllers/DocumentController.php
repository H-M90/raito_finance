<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Collection;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\Customer;
use App\Models\DiscountVoucher;
use App\Models\Expense;
use App\Models\Installation;
use App\Models\Purchase;
use App\Models\SalesQuotation;
use App\Support\OwnRecordVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function download(Request $request, Attachment $attachment): BinaryFileResponse
    {
        $attachable = $attachment->attachable;
        abort_unless($attachable && $this->canAccess($request, $attachable, false), 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return response()->download(
            Storage::disk('local')->path($attachment->path),
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $attachable = $attachment->attachable;
        abort_unless($attachable && $this->canAccess($request, $attachable, true), 403);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'تم حذف المرفق.');
    }

    private function canAccess(Request $request, Model $attachable, bool $editing): bool
    {
        $permission = match (true) {
            $attachable instanceof Customer => $editing ? 'customers.update' : 'customers.view',
            $attachable instanceof SalesQuotation => $editing ? 'quotations.update' : 'quotations.view',
            $attachable instanceof Contract => $editing ? 'contracts.update' : 'contracts.view',
            $attachable instanceof ContractAddendum => $editing ? 'addendums.update' : 'addendums.view',
            $attachable instanceof Collection => $editing ? 'collections.update' : 'collections.view',
            $attachable instanceof DiscountVoucher => $editing ? 'discount-vouchers.update' : 'discount-vouchers.view',
            $attachable instanceof Purchase => $editing ? 'purchases.update' : 'purchases.view',
            $attachable instanceof Expense => $editing ? 'expenses.update' : 'expenses.view',
            $attachable instanceof Installation => $editing ? 'installations.update' : 'installations.view',
            default => null,
        };

        return $permission !== null
            && $request->user()?->hasPermission($permission)
            && OwnRecordVisibility::allows($attachable, $request->user());
    }
}
