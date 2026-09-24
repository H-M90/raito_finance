<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Support\FinanceOptions;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class ProductController extends Controller
{
    public function index(Request $request): View { $products=Product::with('requiredProduct:id,name')->when($request->q,fn($q,$v)=>$q->where(fn($x)=>$x->where('name','like',"%$v%")->orWhere('code','like',"%$v%")))->latest()->paginate(config('finance.pagination'))->withQueryString(); return view('products.index',compact('products')); }
    public function create(): View { return view('products.form',['product'=>new Product,'types'=>FinanceOptions::productTypes(),'units'=>FinanceOptions::units(),'cycles'=>FinanceOptions::billingCycles(),'dependencyProducts'=>Product::active()->orderBy('name')->get(['id','name','code'])]); }
    public function store(StoreProductRequest $request, NumberGenerator $numbers): RedirectResponse { $data=$request->validated();$data['code']=$numbers->uniqueCode('products','PRD');Product::create($data); return redirect()->route('products.index')->with('success','تمت إضافة البند.'); }
    public function edit(Product $product): View { return view('products.form',['product'=>$product,'types'=>FinanceOptions::productTypes(),'units'=>FinanceOptions::units(),'cycles'=>FinanceOptions::billingCycles(),'dependencyProducts'=>Product::active()->where('id','!=',$product->id)->orderBy('name')->get(['id','name','code'])]); }
    public function update(StoreProductRequest $request, Product $product): RedirectResponse { $product->update($request->safe()->except('code')); return redirect()->route('products.index')->with('success','تم تحديث البند.'); }
}
