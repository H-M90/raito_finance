<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstallationRequest;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Installation;
use App\Models\Station;
use App\Services\DeviceQuantityService;
use App\Services\DocumentService;
use App\Services\InstallationService;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallationController extends Controller
{
    public function index(Request $request): View
    {
        $installations = OwnRecordVisibility::apply(Installation::with(['customer:id,name', 'contract:id,number', 'attachments']))->withCount('stations')->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('number', 'like', "%$v%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$v%"))))->latest()->paginate(config('finance.pagination'))->withQueryString();

        return view('installations.index', compact('installations'));
    }

    public function create(Request $request): View
    {
        return view('installations.form', $this->formData(new Installation, $request->integer('contract_id') ?: null));
    }

    public function edit(Installation $installation): View
    {
        $installation->load('stations');

        return view('installations.form', $this->formData($installation, $installation->contract_id));
    }

    private function formData(Installation $installation, ?int $selectedContract): array
    {
        $selectedContractModel = $selectedContract
            ? Contract::where('activity_type', 'stations')->where('status', 'active')->find($selectedContract)
            : null;
        $customerId = $installation->customer_id ?: $selectedContractModel?->customer_id;

        $customers = Customer::active()->orderBy('name')->limit(50)->get(['id', 'name']);
        if ($customerId) {
            $customers = $customers->concat(Customer::whereKey($customerId)->get(['id', 'name']))->unique('id')->values();
        }

        $contractQuery = fn () => Contract::where('activity_type', 'stations')->where('status', 'active')
            ->select(['id', 'number', 'customer_id', 'pts_count', 'sensor_count'])
            ->withSum(['addendums as active_addendum_pts_count' => fn ($q) => $q->where('status', 'active')], 'pts_count')
            ->withSum(['addendums as active_addendum_sensor_count' => fn ($q) => $q->where('status', 'active')], 'sensor_count');
        $contracts = $contractQuery()->when($customerId, fn ($q) => $q->where('customer_id', $customerId))->orderByDesc('contract_date')->limit(100)->get();
        if ($selectedContractModel) {
            $contracts = $contracts->concat($contractQuery()->whereKey($selectedContractModel->id)->get())->unique('id')->values();
        }

        $stationIds = $installation->stations?->pluck('station_id') ?? collect();
        $stations = collect();
        if ($selectedContractModel) {
            $stations = $selectedContractModel->stations()->where('stations.is_active', true)->orderBy('stations.name')->get(['stations.id', 'stations.name', 'stations.customer_id']);
        }
        if ($stationIds->isNotEmpty()) {
            $stations = $stations->concat(Station::whereIn('id', $stationIds)->get(['id', 'name', 'customer_id']))->unique('id')->values();
        }

        return [
            'installation' => $installation,
            'customers' => $customers,
            'contracts' => $contracts,
            'stations' => $stations,
            'selectedContract' => $selectedContract,
        ];
    }

    public function store(StoreInstallationRequest $request, InstallationService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->safe()->except('handover_attachment');
        try {
            $installation = $service->create($data);
            if ($request->hasFile('handover_attachment')) {
                $documents->attach($installation, $request->file('handover_attachment'), 'محضر التركيب');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['installation' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return redirect()->route('installations.show', $installation)->with('success', 'تم تسجيل عملية التركيب.');
    }

    public function update(StoreInstallationRequest $request, Installation $installation, InstallationService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->safe()->except('handover_attachment');
        try {
            $service->update($installation, $data);
            if ($request->hasFile('handover_attachment')) {
                $documents->attach($installation, $request->file('handover_attachment'), 'محضر التركيب');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['installation' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return redirect()->route('installations.show', $installation)->with('success', 'تم تعديل عملية التركيب.');
    }

    public function show(Installation $installation, DeviceQuantityService $deviceQuantities): View
    {
        $installation->load(['customer', 'contract', 'stations.station', 'attachments']);
        $capacity = $deviceQuantities->totalCapacity($installation->contract);
        $allowedPts = $capacity['pts'];
        $allowedSensors = $capacity['sensor'];

        return view('installations.show', compact('installation', 'allowedPts', 'allowedSensors'));
    }

    public function cancel(Request $request, Installation $installation, InstallationService $service): RedirectResponse
    {
        $data = $request->validate(['cancellation_reason' => 'required|string|max:1000']);
        $service->cancel($installation, $data['cancellation_reason']);

        return back()->with('success', 'تم إلغاء عملية التركيب.');
    }

    public function reopen(Installation $installation, InstallationService $service): RedirectResponse
    {
        $service->reopen($installation);

        return back()->with('success','تمت إعادة فتح عملية التركيب.');
    }
}
