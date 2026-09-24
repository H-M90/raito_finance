<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreStationRequest;
use App\Models\Customer;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class StationController extends Controller
{
    private function customers(?int $selected=null)
    {
        $customers=Customer::active()->orderBy('name')->limit(50)->get(['id','name']);
        if($selected)$customers=$customers->concat(Customer::whereKey($selected)->get(['id','name']))->unique('id')->values();
        return $customers;
    }
    public function index(Request $request): View { $stations=Station::with('customer:id,name')->when($request->customer_id,fn($q,$v)=>$q->where('customer_id',$v))->when($request->q,fn($q,$v)=>$q->where('name','like',"%$v%"))->latest()->paginate(config('finance.pagination'))->withQueryString(); return view('stations.index',['stations'=>$stations,'customers'=>$this->customers($request->integer('customer_id')?:null)]); }
    public function edit(Station $station): View { $station->load('customer:id,name');return view('stations.form',['station'=>$station,'customers'=>$this->customers($station->customer_id)]); }
    public function update(StoreStationRequest $request, Station $station): RedirectResponse { $data=$request->safe()->except('code');$data['customer_id']=$station->customer_id;$station->update($data); return redirect()->route('stations.index')->with('success','تم تحديث المحطة.'); }
}
