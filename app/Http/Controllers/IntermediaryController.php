<?php

namespace App\Http\Controllers;

use App\Models\Intermediary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntermediaryController extends Controller
{
    public function index(Request $request): View
    {
        $intermediaries = Intermediary::query()
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($x) => $x->where('name','like',"%{$term}%")->orWhere('phone','like',"%{$term}%")))
            ->latest()->paginate(config('finance.pagination'))->withQueryString();
        $stats = ['total' => Intermediary::count(), 'active' => Intermediary::where('is_active', true)->count(), 'inactive' => Intermediary::where('is_active', false)->count()];
        return view('intermediaries.index', compact('intermediaries','stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['is_active'] = true;
        Intermediary::create($data);
        return back()->with('success','تمت إضافة الوسيط ويمكن اختياره في العقود وعروض المبيعات.');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $data['is_active'] = true;
        $intermediary = Intermediary::create($data);

        return response()->json([
            'id' => $intermediary->id,
            'name' => $intermediary->name,
            'phone' => $intermediary->phone,
            'email' => $intermediary->email,
        ], 201);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name'=>'required|string|max:255',
            'phone'=>'nullable|string|max:40',
            'email'=>'nullable|email|max:255',
            'notes'=>'nullable|string',
        ]);
    }

    public function update(Request $request, Intermediary $intermediary): RedirectResponse
    {
        $data = $request->validate(['name'=>'required|string|max:255','phone'=>'nullable|string|max:40','email'=>'nullable|email|max:255','notes'=>'nullable|string','is_active'=>'nullable|boolean']);
        $data['is_active'] = $request->boolean('is_active');
        $intermediary->update($data);
        return back()->with('success','تم تحديث بيانات الوسيط.');
    }
}
