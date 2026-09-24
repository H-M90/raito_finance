<?php
namespace App\Services;

use App\Models\Contract;
use App\Models\Installation;
use App\Models\Station;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InstallationService
{
    public function __construct(
        private NumberGenerator $numbers,
        private TimelineService $timeline,
        private DeviceQuantityService $deviceQuantities,
    ) {}

    public function create(array $data): Installation
    {
        return DB::transaction(fn () => $this->persist(new Installation, $data));
    }

    public function update(Installation $installation, array $data): Installation
    {
        if ($installation->status === 'cancelled') {
            throw new \DomainException('أعد فتح عملية التركيب قبل تعديلها.');
        }

        return DB::transaction(function () use ($installation, $data) {
            $installation->stations()->delete();
            return $this->persist($installation, $data);
        });
    }

    private function persist(Installation $installation, array $data): Installation
    {
        $contract = Contract::findOrFail($data['contract_id']);
        if ($contract->status !== 'active') throw new \DomainException('لا يمكن تسجيل تركيب على عقد ملغي.');
        if ($contract->activity_type !== 'stations') throw new \DomainException('عمليات التركيب متاحة لعقود المحطات فقط.');
        if ((int) $contract->customer_id !== (int) $data['customer_id']) throw new \DomainException('العقد لا يخص العميل المختار.');

        $rows = collect($data['stations'] ?? [])->filter(fn ($r) => ! empty($r['station_id']))->values();
        if ($rows->isEmpty()) throw new \DomainException('أضف محطة واحدة على الأقل.');
        if ($rows->pluck('station_id')->map(fn ($id) => (int) $id)->duplicates()->isNotEmpty()) throw new \DomainException('لا يمكن تكرار نفس المحطة داخل عملية التركيب.');

        $this->validateStationAllocations($contract, $rows, $installation->exists ? $installation->id : null, $data['status'] ?? 'planned');

        $installation->fill([
            'number' => $installation->exists ? $installation->number : $this->numbers->unique('installations', 'INS'),
            'customer_id' => $data['customer_id'],
            'contract_id' => $contract->id,
            'planned_date' => $data['planned_date'] ?? null,
            'installation_date' => $data['installation_date'] ?? null,
            'team_name' => $data['team_name'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'handover_attachment' => $data['handover_attachment'] ?? $installation->handover_attachment,
            'notes' => $data['notes'] ?? null,
            'created_by' => $installation->created_by ?: auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $installation->save();

        foreach ($rows as $row) {
            $installation->stations()->create([
                'station_id' => $row['station_id'],
                'pts_installed' => ! empty($row['pts_installed']),
                'sensor_count' => max(0, (int) ($row['sensor_count'] ?? 0)),
                'installed_at' => $row['installed_at'] ?? $data['installation_date'] ?? null,
                'status' => $row['status'] ?? 'completed',
                'notes' => $row['notes'] ?? null,
            ]);
        }

        $this->timeline->record(
            $installation->customer_id,
            'installation',
            $installation->wasRecentlyCreated ? 'إنشاء عملية تركيب' : 'تعديل عملية تركيب',
            $installation->number,
            $installation,
            $contract->id,
            route('installations.show', $installation),
            $installation->installation_date ?? $installation->planned_date,
        );

        return $installation->load('customer', 'contract', 'stations.station');
    }

    private function validateStationAllocations(Contract $contract, Collection $rows, ?int $excludeInstallationId = null, string $installationStatus = 'planned'): void
    {
        $stationIds = $rows->pluck('station_id')->map(fn ($id) => (int) $id)->unique()->values();
        $stations = Station::whereIn('id', $stationIds)->get()->keyBy('id');
        $allocations = DB::table('contract_station')
            ->where('contract_id', $contract->id)
            ->whereIn('station_id', $stationIds)
            ->get()->keyBy('station_id');

        foreach ($stationIds as $stationId) {
            $station = $stations->get($stationId);
            if (! $station || (int) $station->customer_id !== (int) $contract->customer_id) {
                throw new \DomainException('إحدى المحطات لا تتبع عميل العقد.');
            }
            if (! $allocations->has($stationId)) {
                throw new \DomainException('إحدى المحطات غير مضافة إلى هذا العقد. أضفها أو اربطها بالعقد أولًا.');
            }
        }

        $existingQuery = DB::table('installation_stations')
            ->join('installations', 'installations.id', '=', 'installation_stations.installation_id')
            ->where('installations.contract_id', $contract->id)
            ->where('installations.status', '!=', 'cancelled')
            ->where('installation_stations.status', '!=', 'cancelled');
        if ($excludeInstallationId) $existingQuery->where('installations.id', '!=', $excludeInstallationId);

        $existingByStation = $existingQuery
            ->selectRaw('installation_stations.station_id, SUM(CASE WHEN installation_stations.pts_installed = 1 THEN 1 ELSE 0 END) AS pts_installed_total, SUM(installation_stations.sensor_count) AS sensors_installed_total')
            ->groupBy('installation_stations.station_id')
            ->get()->keyBy('station_id');

        $effectiveRows = $installationStatus === 'cancelled'
            ? collect()
            : $rows->filter(fn ($row) => ($row['status'] ?? 'completed') !== 'cancelled');

        $newPtsTotal = 0;
        $newSensorsTotal = 0;
        foreach ($effectiveRows as $row) {
            $stationId = (int) $row['station_id'];
            $allocation = $allocations->get($stationId);
            $ptsAllowed = ! empty($allocation?->pts_count) ? 1 : 0;
            $sensorsAllowed = max(0, (int) ($allocation?->sensor_count ?? 0));
            $ptsRequested = ! empty($row['pts_installed']) ? 1 : 0;
            $sensorsRequested = max(0, (int) ($row['sensor_count'] ?? 0));
            $existing = $existingByStation->get($stationId);
            $existingPts = (int) ($existing?->pts_installed_total ?? 0);
            $existingSensors = (int) ($existing?->sensors_installed_total ?? 0);

            if ($ptsRequested && $ptsAllowed !== 1) {
                throw new \DomainException('لا يمكن تركيب PTS في محطة غير مخصص لها PTS داخل العقد.');
            }
            if ($ptsRequested && $existingPts >= 1) {
                throw new \DomainException('تم تركيب PTS بالفعل في إحدى المحطات المختارة؛ المحطة الواحدة لا تحتوي إلا على PTS واحد.');
            }
            if ($existingSensors + $sensorsRequested > $sensorsAllowed) {
                throw new \DomainException('عدد الحساسات المركبة في إحدى المحطات يتجاوز العدد المخصص لها في العقد.');
            }

            $newPtsTotal += $ptsRequested;
            $newSensorsTotal += $sensorsRequested;
        }

        $capacity = $this->deviceQuantities->totalCapacity($contract);
        $existingPtsTotal = (int) $existingByStation->sum(fn ($row) => (int) $row->pts_installed_total);
        $existingSensorsTotal = (int) $existingByStation->sum(fn ($row) => (int) $row->sensors_installed_total);
        if ($existingPtsTotal + $newPtsTotal > (int) $capacity['pts']) throw new \DomainException('عدد أجهزة PTS يتجاوز بنود PTS في العقد والملحقات الفعالة.');
        if ($existingSensorsTotal + $newSensorsTotal > (int) $capacity['sensor']) throw new \DomainException('عدد الحساسات يتجاوز بنود الحساسات في العقد والملحقات الفعالة.');
    }

    public function cancel(Installation $installation, string $reason): Installation
    {
        $installation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $reason,
            'updated_by' => auth()->id(),
        ]);
        $this->timeline->record($installation->customer_id, 'installation', 'إلغاء عملية تركيب', "{$installation->number} · {$reason}", $installation, $installation->contract_id, route('installations.show', $installation));
        return $installation;
    }

    public function reopen(Installation $installation): Installation
    {
        if ($installation->status !== 'cancelled') return $installation;

        return DB::transaction(function () use ($installation) {
            $installation->loadMissing(['contract', 'stations']);
            $contract = $installation->contract;
            if (! $contract || $contract->status !== 'active') throw new \DomainException('لا يمكن إعادة فتح التركيب لأن العقد غير فعال.');

            $rows = $installation->stations->map(fn ($row) => [
                'station_id' => $row->station_id,
                'pts_installed' => (bool) $row->pts_installed,
                'sensor_count' => (int) $row->sensor_count,
                'status' => $row->status,
            ]);
            $this->validateStationAllocations($contract, $rows, $installation->id, 'planned');

            $installation->update([
                'status' => 'planned',
                'reopened_at' => now(),
                'reopened_by' => auth()->id(),
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
                'updated_by' => auth()->id(),
            ]);
            $this->timeline->record($installation->customer_id, 'installation', 'إعادة فتح عملية تركيب', $installation->number, $installation, $installation->contract_id, route('installations.show', $installation));
            return $installation;
        });
    }
}
