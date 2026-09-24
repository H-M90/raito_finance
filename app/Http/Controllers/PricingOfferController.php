<?php
namespace App\Http\Controllers;
use App\Http\Requests\StorePricingOfferRequest;
use App\Models\PricingOffer;
use App\Models\Product;
use App\Support\FinanceOptions;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class PricingOfferController extends Controller
{
    public function index(Request $request): View { $offers=PricingOffer::withCount('products')->when($request->q,fn($q,$v)=>$q->where(fn($x)=>$x->where('name','like',"%$v%")->orWhere('code','like',"%$v%")))->latest()->paginate(config('finance.pagination'))->withQueryString(); return view('pricing-offers.index',compact('offers')); }
    public function create(): View { return $this->form(new PricingOffer); }
    public function store(StorePricingOfferRequest $request, NumberGenerator $numbers): RedirectResponse { $data=$request->safe()->except('product_ids');$data['code']=$numbers->uniqueCode('pricing_offers','OFF');$offer=PricingOffer::create($data); $offer->products()->sync($request->validated('product_ids',[])); return redirect()->route('pricing-offers.index')->with('success','تم إنشاء عرض التسعير.'); }
    public function edit(PricingOffer $pricingOffer): View { $pricingOffer->load('products'); return $this->form($pricingOffer); }
    public function update(StorePricingOfferRequest $request, PricingOffer $pricingOffer): RedirectResponse { $pricingOffer->update($request->safe()->except(['product_ids','code'])); $pricingOffer->products()->sync($request->validated('product_ids',[])); return redirect()->route('pricing-offers.index')->with('success','تم تحديث عرض التسعير.'); }
    private function form(PricingOffer $offer): View { $selected=$offer->exists?$offer->products()->pluck('products.id'):collect();$products=Product::active()->orderBy('name')->limit(100)->get();if($selected->isNotEmpty())$products=$products->concat(Product::whereIn('id',$selected)->get())->unique('id')->values();return view('pricing-offers.form',['offer'=>$offer,'products'=>$products,'cycles'=>['all'=>'كل الدوريات']+FinanceOptions::billingCycles()]); }
}
