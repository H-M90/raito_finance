@extends('layouts.app')
@php
$editing = $contract->exists;
$source = $editing ? $contract : $quotation;
$selectedCustomer = old('customer_id', $source?->customer_id ?? request('customer_id'));
$cycle = old('billing_cycle', $source?->billing_cycle ?? 'one_time');
$currency = old('currency', $source?->currency ?? config('finance.default_currency'));
$activity = old('activity_type', $source?->activity_type ?? 'erp');
$rows = old('items', $source?->items?->map(fn($i) => $i->only(['product_id','quantity','requested_users','user_unit_price','unit_price','discount_value','maintenance_rate','notes']))->all() ?: [['product_id'=>'','quantity'=>1,'requested_users'=>0,'user_unit_price'=>'','unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0]]);
$selectedOffers = old('pricing_offer_ids', $source?->pricingOffers?->pluck('id')->all() ?: []);
$legacyContract = old('is_imported', $contract->is_imported);
$manualContractTotal = old('manual_contract_total', $contract->is_imported ? number_format((float) $contract->grand_total, 2, '.', '') : '');
$installments = old('installments', $editing
    ? $contract->installments->map(fn($i) => $i->only(['name','percentage','due_date','net_amount','notes']))->map(fn($r) => array_merge($r,['due_date'=>$r['due_date']?->format('Y-m-d')]))->all()
    : [['name'=>'الدفعة الأولى','percentage'=>100,'due_date'=>today()->format('Y-m-d'),'net_amount'=>'']]);
$stationRows = old('stations', $editing
    ? $contract->stations->map(fn($s) => [
        'id'=>$s->id,
        'name'=>$s->name,
        'city'=>$s->city,
        'location'=>$s->location,
        'contact_name'=>$s->contact_name,
        'phone'=>$s->phone,
        'relationship_start_date'=>$s->relationship_start_date?->format('Y-m-d'),
        'notes'=>$s->notes,
        'pts_count'=>(int)($s->pivot?->pts_count ?? 0),
        'sensor_count'=>(int)($s->pivot?->sensor_count ?? 0),
    ])->all()
    : [['id'=>'','name'=>'','city'=>'','location'=>'','contact_name'=>'','phone'=>'','relationship_start_date'=>'','notes'=>'','pts_count'=>0,'sensor_count'=>0]]);
$hasIntermediary = (string) old('has_intermediary', filled($source?->intermediary_id) ? '1' : '0');
@endphp

@section('title', $editing ? 'تعديل عقد' : 'عقد جديد')
@section('page-title', $editing ? 'تعديل العقد '.$contract->number : 'إنشاء عقد')
@section('page-subtitle', $editing
    ? 'بيانات العميل والعقد والبنود والمحطات في مسار واحد واضح'
    : ($quotation ? 'تحويل عرض المبيعات '.$quotation->number.' إلى عقد مفعل' : 'اختيار العميل ثم إدخال بيانات العقد والبنود والدفعات'))

@section('content')
<form method="post" enctype="multipart/form-data"
      action="{{ $editing ? route('contracts.update',$contract) : route('contracts.store') }}"
      class="enterprise-form contract-editor" data-financial-form data-contract-form data-intermediary-form data-tax-rate="{{ config('finance.tax_rate') }}"
      @if(!$editing && $quotation) data-quotation-locked="1" @endif>
    @csrf
    @if($editing) @method('put') @endif
    @if(!$editing && $quotation)<input type="hidden" name="sales_quotation_id" value="{{ $quotation->id }}">@endif

    @if(!$editing && $quotation)
        <div class="alert alert-info quotation-lock-notice">
            <strong>التسعير مثبت من عرض المبيعات {{ $quotation->number }}.</strong>
            العميل والبنود والأسعار والخصومات والعملة مثبتة من العرض المقبول. يمكنك استكمال الدومين، بداية الخدمة، المحطات والدفعات.
            <a href="{{ route('quotations.show',$quotation) }}" target="_blank">فتح العرض</a>
        </div>
    @endif

    <x-customer-picker-card :customers="$customers" :selected="$selectedCustomer" :locked="(!$editing && (bool)$quotation)" source-type="contract" />

    <div class="form-section contract-main-card">
        <div class="form-section-title">
            <div><h3>بيانات العقد</h3><div class="help">رقم العقد يتم إنشاؤه تلقائيًا عند الحفظ ولا يحتاج أي إدخال يدوي.</div></div>
            <span class="badge badge-success">يُفعل فور الحفظ</span>
        </div>
        <div class="form-grid">
            <div class="form-group col-3">
                <label class="required">نوع العقد</label>
                <select class="select" name="activity_type" data-contract-activity @disabled(!$editing && $quotation)>
                    @foreach($activities as $v=>$l)<option value="{{ $v }}" @selected($activity===$v)>{{ $l }}</option>@endforeach
                </select>
                @if(!$editing && $quotation)<input type="hidden" name="activity_type" value="{{ $activity }}">@endif
            </div>
            <div class="form-group col-3">
                <label>الدومين</label>
                <input class="input" name="domain" value="{{ old('domain',$contract->domain) }}" placeholder="example.com" inputmode="url">
            </div>
            <div class="form-group col-3">
                <label class="required">تاريخ العقد</label>
                <input class="input" type="date" name="contract_date" value="{{ old('contract_date',$source?->contract_date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group col-3">
                <label class="required">بداية الخدمة</label>
                <input class="input" type="date" name="service_start_date" value="{{ old('service_start_date',$source?->service_start_date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group col-3">
                <label>الفوترة</label>
                <select class="select" name="billing_cycle" @disabled(!$editing && $quotation)>
                    @foreach($cycles as $v=>$l)<option value="{{ $v }}" @selected($cycle===$v)>{{ $l }}</option>@endforeach
                </select>
                @if(!$editing && $quotation)<input type="hidden" name="billing_cycle" value="{{ $cycle }}">@endif
            </div>
            <div class="form-group col-3">
                <label>العملة</label>
                <select class="select" name="currency" @disabled(!$editing && $quotation)>
                    @foreach($currencies as $v=>$l)<option value="{{ $v }}" @selected($currency===$v)>{{ $l }}</option>@endforeach
                </select>
                @if(!$editing && $quotation)<input type="hidden" name="currency" value="{{ $currency }}">@endif
            </div>
            <x-reference-picker label="مسؤول المبيعات" name="sales_owner_id" wrapper-class="col-3">
                <option value="">غير محدد</option>
                @foreach($salesOwners as $owner)<option value="{{ $owner->id }}" @selected(old('sales_owner_id',$source?->sales_owner_id)==$owner->id)>{{ $owner->name }}</option>@endforeach
            </x-reference-picker>
        </div>
    </div>

    @if (!$quotation)
        <div class="form-section">
            <div class="form-grid">
                <div class="form-group col-12">
                    <label class="switch-label"><input type="checkbox" name="is_imported" value="1" data-legacy-contract-toggle @checked($legacyContract)> عقد قديم: أدخل قيمة العقد يدويًا ولا تطابق الدفعات</label>
                    <div class="help">لا يولد النظام دفعات أو استحقاقات تلقائية من جدول الدفعات في هذا الوضع؛ أضف الاستحقاقات المطلوبة يدويًا من شاشة الاستحقاقات.</div>
                </div>
                <div class="form-group col-4" data-legacy-contract-value @if (! $legacyContract) hidden @endif>
                    <label for="manual_contract_total">قيمة العقد الإجمالية (شاملة الضريبة)</label>
                    <input id="manual_contract_total" class="input" type="number" min="0.01" step="0.01" name="manual_contract_total" value="{{ $manualContractTotal }}" @if (! $legacyContract) disabled @endif>
                    @error('manual_contract_total')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
            </div>
        </div>
    @endif

    <div class="form-section intermediary-switch-section">
        <div class="form-section-title intermediary-heading">
            <div>
                <h3>الوسيط</h3>
                <div class="help">فعّل الخيار فقط لو العقد تم عن طريق وسيط. عند الإيقاف تختفي كل بيانات الوسيط والعمولة ولا يتم حفظها.</div>
            </div>
            <label class="toggle-switch" title="تفعيل أو إلغاء الوسيط">
                @if(!(!$editing && $quotation))<input type="hidden" name="has_intermediary" value="0">@endif
                <input type="checkbox" name="has_intermediary" value="1" data-has-intermediary @checked($hasIntermediary==='1') @disabled(!$editing && $quotation)>
                <span class="toggle-switch-track" aria-hidden="true"><span class="toggle-switch-thumb"></span></span>
                <span class="toggle-switch-text"><span data-toggle-off>Off</span><span data-toggle-on>On</span></span>
            </label>
            @if(!$editing && $quotation)<input type="hidden" name="has_intermediary" value="{{ $hasIntermediary }}">@endif
        </div>

        <div class="intermediary-card" data-intermediary-panel @if($hasIntermediary!=='1') hidden @endif>
            <div class="intermediary-card-head">
                <div><strong>بيانات الوسيط والعمولة</strong><small>اختر وسيطًا موجودًا أو أضف وسيطًا جديدًا وسيتم اختياره تلقائيًا.</small></div>
                @if(!(!$editing && $quotation) && auth()->user()->hasPermission('intermediaries.create'))
                    <button class="btn btn-sm btn-outline" type="button" data-toggle-intermediary-create>+ إضافة وسيط</button>
                @endif
            </div>
            <div class="form-grid">
                <div class="form-group col-4 reference-picker-field">
                    <label class="required">الوسيط</label>
                    <select class="select" name="intermediary_id" data-smart-select="1" data-intermediary-select @disabled(!$editing && $quotation)>
                        <option value="">اختر الوسيط</option>
                        @foreach($intermediaries as $i)<option value="{{ $i->id }}" @selected(old('intermediary_id',$source?->intermediary_id)==$i->id)>{{ $i->name }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group col-2"><label>نوع العمولة</label><select class="select" name="commission_type" data-commission-type @disabled(!$editing && $quotation)><option value="percentage" @selected(old('commission_type',$source?->commission_type)==='percentage')>نسبة %</option><option value="fixed" @selected(old('commission_type',$source?->commission_type)==='fixed')>مبلغ ثابت</option></select></div>
                <div class="form-group col-2"><label data-commission-value-label>النسبة / القيمة</label><input class="input" type="number" min="0" step=".01" name="commission_value" data-commission-value value="{{ old('commission_value',$source?->commission_value) }}" @disabled(!$editing && $quotation)></div>
                <div class="form-group col-4"><label>قيمة العمولة المحسوبة</label><div class="commission-value-card"><strong data-commission-total>{{ number_format((float)($source?->commission_total??0),2) }}</strong><span data-commission-currency>{{ $currency }}</span><small>من صافي العقد بعد الخصم وقبل الضريبة</small></div></div>
                <input type="hidden" name="commission_due_basis" value="collection">
            </div>

            @if(!(!$editing && $quotation) && auth()->user()->hasPermission('intermediaries.create'))
                <div class="quick-intermediary-panel" data-intermediary-create-panel data-action="{{ route('intermediaries.quick-store') }}" hidden>
                    <div class="quick-intermediary-head"><strong>إضافة وسيط جديد</strong><span>يُحفظ فورًا ثم يتم اختياره في العقد تلقائيًا.</span></div>
                    <div class="form-grid">
                        <div class="form-group col-4"><label class="required">اسم الوسيط</label><input class="input" data-intermediary-field="name" autocomplete="name"></div>
                        <div class="form-group col-3"><label>الهاتف</label><input class="input" data-intermediary-field="phone" autocomplete="tel"></div>
                        <div class="form-group col-3"><label>البريد الإلكتروني</label><input class="input" type="email" data-intermediary-field="email" autocomplete="email"></div>
                        <div class="form-group col-10"><label>ملاحظات</label><input class="input" data-intermediary-field="notes"></div>
                        <div class="form-group col-2 action-align"><button class="btn btn-primary" type="button" data-quick-intermediary-save>حفظ واختيار</button></div>
                        <div class="col-12 quick-intermediary-message" data-intermediary-message aria-live="polite"></div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-title"><h3>العروض والخصومات</h3>@if(auth()->user()->hasPermission('pricing-offers.view'))<a class="btn btn-sm btn-light" href="{{ route('pricing-offers.index') }}" target="_blank">إدارة العروض</a>@endif</div>
        <x-pricing-offers :offers="$pricingOffers" :selected="$selectedOffers" />
    </div>

    <div class="form-section">
        <div class="form-section-title">
            <div><h3>بنود العقد</h3><div class="help">الموديول لا يحتوي على كمية؛ يتم تحديد المستخدمين الإضافيين فقط. الكمية تظهر للأجهزة مثل PTS والحساسات وباقي البنود الكمية.</div></div>
            <button class="btn btn-sm btn-outline" type="button" data-add-line>+ بند</button>
        </div>
        <x-pricing-items :products="$products" :rows="$rows" />
        <x-financial-totals />
    </div>

    <div class="form-section contract-stations-section" data-contract-stations-section @if($activity!=='stations') hidden @endif>
        <div class="form-section-title station-section-title">
            <div>
                <h3>محطات العميل</h3>
                <div class="help">بيانات المحطة اختيارية. كل محطة يمكن أن يكون عليها PTS واحد كحد أقصى، بينما الحساسات يمكن أن تكون متعددة. الإجمالي المتاح يأتي فقط من بنود PTS والحساسات.</div>
            </div>
            <button class="btn btn-sm btn-outline" type="button" data-add-contract-station>+ إضافة محطة</button>
        </div>
        <div class="station-capacity-strip">
            <div><span>PTS في بنود العقد</span><strong data-contract-pts-capacity>0</strong></div>
            <div><span>PTS موزع</span><strong data-contract-pts-allocated>0</strong></div>
            <div><span>الحساسات في البنود</span><strong data-contract-sensor-capacity>0</strong></div>
            <div><span>الحساسات موزعة</span><strong data-contract-sensor-allocated>0</strong></div>
            <div class="station-capacity-status" data-station-capacity-status>التوزيع داخل حدود البنود</div>
        </div>

        <div class="contract-stations" data-contract-stations data-next-index="{{ count($stationRows) }}">
            @foreach($stationRows as $i=>$station)
                <div class="contract-station-row" data-contract-station-row>
                    <input type="hidden" name="stations[{{ $i }}][id]" value="{{ $station['id']??'' }}">
                    <div class="station-name-field"><label>اسم المحطة</label><input class="input" name="stations[{{ $i }}][name]" value="{{ $station['name']??'' }}" placeholder="اختياري"></div>
                    <div><label>المدينة</label><input class="input" name="stations[{{ $i }}][city]" value="{{ $station['city']??'' }}"></div>
                    <div><label>الموقع</label><input class="input" name="stations[{{ $i }}][location]" value="{{ $station['location']??'' }}"></div>
                    <div><label>مسؤول المحطة</label><input class="input" name="stations[{{ $i }}][contact_name]" value="{{ $station['contact_name']??'' }}"></div>
                    <div><label>الهاتف</label><input class="input" name="stations[{{ $i }}][phone]" value="{{ $station['phone']??'' }}"></div>
                    <div class="station-device-field"><label>PTS بالمحطة</label><select class="select" name="stations[{{ $i }}][pts_count]" data-station-pts><option value="0" @selected(empty($station['pts_count']))>لا</option><option value="1" @selected(!empty($station['pts_count']))>نعم</option></select></div>
                    <div class="station-device-field"><label>عدد الحساسات</label><input class="input" type="number" min="0" step="1" name="stations[{{ $i }}][sensor_count]" value="{{ $station['sensor_count']??0 }}" data-station-sensors></div>
                    <button class="remove-row" type="button" data-remove-contract-station aria-label="حذف المحطة"><x-ui-icon name="x" /></button>
                </div>
            @endforeach
        </div>
        <template data-contract-station-template>
            <div class="contract-station-row" data-contract-station-row>
                <input type="hidden" name="stations[__INDEX__][id]" value="">
                <div class="station-name-field"><label>اسم المحطة</label><input class="input" name="stations[__INDEX__][name]" placeholder="اختياري"></div>
                <div><label>المدينة</label><input class="input" name="stations[__INDEX__][city]"></div>
                <div><label>الموقع</label><input class="input" name="stations[__INDEX__][location]"></div>
                <div><label>مسؤول المحطة</label><input class="input" name="stations[__INDEX__][contact_name]"></div>
                <div><label>الهاتف</label><input class="input" name="stations[__INDEX__][phone]"></div>
                <div class="station-device-field"><label>PTS بالمحطة</label><select class="select" name="stations[__INDEX__][pts_count]" data-station-pts><option value="0">لا</option><option value="1">نعم</option></select></div>
                <div class="station-device-field"><label>عدد الحساسات</label><input class="input" type="number" min="0" step="1" name="stations[__INDEX__][sensor_count]" value="0" data-station-sensors></div>
                <button class="remove-row" type="button" data-remove-contract-station><x-ui-icon name="x" /></button>
            </div>
        </template>
    </div>

    <div class="form-section" data-contract-installments-section @if ($legacyContract) hidden @endif>
        <div class="form-section-title"><div><h3>دفعات العقد وتواريخ الاستحقاق</h3><div class="help">في الدفع مرة واحدة يجب أن يساوي مجموع الدفعات صافي قيمة العقد بالكامل.</div></div><button class="btn btn-sm btn-outline" type="button" data-add-installment>+ دفعة</button></div>
        <div class="table-wrap"><table><thead><tr><th>الدفعة</th><th>النسبة</th><th>الاستحقاق</th><th>المبلغ قبل الضريبة</th><th></th></tr></thead><tbody data-installments data-next-index="{{ count($installments) }}">@foreach($installments as $i=>$r)<tr data-installment-row><td><input class="input" name="installments[{{ $i }}][name]" value="{{ $r['name']??'' }}"></td><td><input class="input" type="number" min="0" max="100" step=".01" name="installments[{{ $i }}][percentage]" value="{{ $r['percentage']??'' }}" data-installment-percentage></td><td><input class="input" type="date" name="installments[{{ $i }}][due_date]" value="{{ $r['due_date']??'' }}"></td><td><input class="input" type="number" min=".01" step=".01" name="installments[{{ $i }}][net_amount]" value="{{ $r['net_amount']??'' }}" data-installment-amount></td><td><button class="remove-row" type="button" data-remove-installment><x-ui-icon name="x" /></button></td></tr>@endforeach</tbody></table></div>
        <template data-installment-template><tr data-installment-row><td><input class="input" name="installments[__INDEX__][name]" value="دفعة جديدة"></td><td><input class="input" type="number" min="0" max="100" step=".01" name="installments[__INDEX__][percentage]" data-installment-percentage></td><td><input class="input" type="date" name="installments[__INDEX__][due_date]" value="{{ today()->format('Y-m-d') }}"></td><td><input class="input" type="number" min=".01" step=".01" name="installments[__INDEX__][net_amount]" data-installment-amount></td><td><button class="remove-row" type="button" data-remove-installment><x-ui-icon name="x" /></button></td></tr></template>
    </div>

    <div class="form-section">
        <div class="form-grid">
            <div class="form-group col-3"><label>بداية الاحتساب</label><input class="input" type="date" name="calculation_start_date" value="{{ old('calculation_start_date',$contract->calculation_start_date?->format('Y-m-d')) }}"><div class="help">لو العقد قديم والبيانات الدقيقة متاحة من تاريخ أحدث، اختر أول تاريخ موثوق. عند الحفظ يولد النظام الاستحقاقات الدورية من هذا التاريخ فقط بدون الرجوع لبداية العقد.</div></div>
            <div class="form-group col-3"><label>الصيانة مدفوعة حتى</label><input class="input" type="date" name="maintenance_paid_until" value="{{ old('maintenance_paid_until',$contract->maintenance_paid_until?->format('Y-m-d')) }}"></div>
            <div class="form-group col-2"><label>رصيد دفعات افتتاحي</label><input class="input" type="number" min="0" step=".01" name="opening_receivable_balance" value="{{ old('opening_receivable_balance',$contract->opening_receivable_balance??0) }}"></div>
            <div class="form-group col-2"><label>رصيد صيانة افتتاحي</label><input class="input" type="number" min="0" step=".01" name="opening_maintenance_balance" value="{{ old('opening_maintenance_balance',$contract->opening_maintenance_balance??0) }}"></div>
            <div class="form-group col-2"><label>مرفق العقد</label><input class="input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
            <div class="form-group col-12"><label>ملاحظات</label><textarea class="textarea" name="notes">{{ old('notes',$contract->notes) }}</textarea></div>
        </div>
    </div>

    <div class="form-actions"><button class="btn btn-primary">{{ $editing ? 'حفظ التعديلات' : 'إنشاء وتفعيل العقد' }}</button><a class="btn btn-light" href="{{ $editing ? route('contracts.show',$contract) : route('contracts.index') }}">إلغاء</a></div>
</form>
@endsection
