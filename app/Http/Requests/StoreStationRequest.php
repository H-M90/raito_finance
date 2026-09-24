<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreStationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { $this->merge(['is_active'=>$this->boolean('is_active')]); }
    public function rules(): array { return ['customer_id'=>'required|exists:customers,id','name'=>'required|string|max:255','city'=>'nullable|string|max:100','location'=>'nullable|string|max:255','contact_name'=>'nullable|string|max:255','phone'=>'nullable|string|max:40','is_active'=>'boolean','relationship_start_date'=>'nullable|date','notes'=>'nullable|string']; }
}
