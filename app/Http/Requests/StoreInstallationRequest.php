<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreInstallationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['customer_id'=>'required|exists:customers,id','contract_id'=>'required|exists:contracts,id','planned_date'=>'nullable|date','installation_date'=>'nullable|date','team_name'=>'nullable|string|max:255','status'=>'required|in:planned,in_progress,completed,postponed,cancelled','handover_attachment'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240','notes'=>'nullable|string','stations'=>'required|array|min:1','stations.*.station_id'=>'required|exists:stations,id','stations.*.pts_installed'=>'nullable|boolean','stations.*.sensor_count'=>'nullable|integer|min:0','stations.*.installed_at'=>'nullable|date','stations.*.status'=>'nullable|in:planned,in_progress,completed,postponed,cancelled','stations.*.notes'=>'nullable|string']; }
}
