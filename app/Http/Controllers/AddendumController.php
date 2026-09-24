<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreAddendumRequest;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\PricingOffer;
use App\Models\Product;
use App\Services\AddendumService;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class AddendumController extends Controller
{
    public function create(Contract $contract): View { $contract->load('customer');return view('addendums.form',$this->formData($contract,new ContractAddendum)); }
    public function store(StoreAddendumRequest $request,Contract $contract,AddendumService $service,DocumentService $documents): RedirectResponse { $data=$request->validated();unset($data['attachment']);try{$addendum=$service->create($contract,$data);}catch(\Throwable $e){return back()->withInput()->withErrors(['addendum'=>\App\Support\SafeExceptionMessage::from($e)]);}if($request->hasFile('attachment'))$documents->attach($addendum,$request->file('attachment'),'ملحق العقد');return redirect()->route('addendums.show',[$contract,$addendum])->with('success','تم إنشاء الملحق وتفعيله.'); }
    public function show(Contract $contract,ContractAddendum $addendum): View { abort_unless($addendum->contract_id===$contract->id,404);$addendum->load('contract.customer','items.product','installments','pricingOffers','receivables','attachments');return view('addendums.show',compact('contract','addendum')); }
    public function edit(Contract $contract,ContractAddendum $addendum): View { abort_unless($addendum->contract_id===$contract->id,404);$addendum->load('items.product','installments','pricingOffers');return view('addendums.form',$this->formData($contract,$addendum)); }
    public function update(StoreAddendumRequest $request,Contract $contract,ContractAddendum $addendum,AddendumService $service,DocumentService $documents): RedirectResponse { abort_unless($addendum->contract_id===$contract->id,404);$data=$request->validated();unset($data['attachment']);try{$service->update($addendum,$data);}catch(\Throwable $e){return back()->withInput()->withErrors(['addendum'=>\App\Support\SafeExceptionMessage::from($e)]);}if($request->hasFile('attachment'))$documents->attach($addendum,$request->file('attachment'),'ملحق العقد');return redirect()->route('addendums.show',[$contract,$addendum])->with('success','تم تعديل الملحق.'); }
    public function cancel(Request $request,Contract $contract,ContractAddendum $addendum,AddendumService $service): RedirectResponse { abort_unless($addendum->contract_id===$contract->id,404);$data=$request->validate(['cancellation_reason'=>'required|string|max:1000']);try{$service->cancel($addendum,$data['cancellation_reason']);}catch(\DomainException $e){return back()->withErrors(['addendum'=>\App\Support\SafeExceptionMessage::from($e)]);}return back()->with('success','تم إلغاء الملحق.'); }
    public function reopen(Contract $contract,ContractAddendum $addendum,AddendumService $service): RedirectResponse { abort_unless($addendum->contract_id===$contract->id,404);try{$service->reopen($addendum);}catch(\DomainException $e){return back()->withErrors(['addendum'=>\App\Support\SafeExceptionMessage::from($e)]);}return back()->with('success','تمت إعادة فتح الملحق.'); }
    private function formData(Contract $contract,ContractAddendum $addendum): array { $products=Product::active()->with('requiredProduct:id,name,code')->orderBy('name')->limit(100)->get();$selected=$addendum->items?->pluck('product_id')??collect();if($selected->isNotEmpty())$products=$products->concat(Product::with('requiredProduct:id,name,code')->whereIn('id',$selected)->get())->unique('id')->values();return ['contract'=>$contract,'addendum'=>$addendum,'products'=>$products,'pricingOffers'=>PricingOffer::with('products:id')->available()->orderBy('priority')->get()]; }
}
