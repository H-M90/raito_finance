@props([
    'customers' => collect(),
    'selected' => null,
    'locked' => false,
    'sourceType' => 'contract',
])

<div class="customer-picker-card">
    <div class="customer-picker-head">
        <div>
            <span class="customer-picker-icon">👤</span>
            <div>
                <h3>العميل</h3>
                <p>اختر عميلًا موجودًا أو أضف عميلًا جديدًا من نفس الكارت.</p>
            </div>
        </div>
        @if($locked)<span class="badge badge-info">مثبت من العرض</span>@endif
    </div>

    <div class="customer-picker-existing">
        <label class="required">اختيار العميل</label>
        <div class="reference-picker-control">
            <select class="select" name="customer_id" data-remote-url="{{ route('lookup.customers') }}" data-smart-select="1" data-customer-select required @disabled($locked)>
                <option value="">ابحث عن العميل بالاسم</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" data-segment="{{ $customer->segment }}" @selected($selected==$customer->id)>
                        {{ $customer->name }}{{ $customer->segment==='startup'?' · ناشئة':'' }}
                    </option>
                @endforeach
            </select>
            @if(!$locked && auth()->user()->hasPermission('customers.create'))
                <button class="btn btn-sm btn-outline reference-picker-add" type="button" data-toggle-customer-create>+ عميل جديد</button>
            @endif
        </div>
        @if($locked)<input type="hidden" name="customer_id" value="{{ $selected }}">@endif
    </div>

    @if(!$locked && auth()->user()->hasPermission('customers.create'))
    <div class="customer-picker-new" data-customer-new-details hidden>
        <div class="customer-picker-new-body" data-quick-customer-card data-action="{{ route('customers.quick-store') }}">
            <input type="hidden" value="{{ $sourceType }}" data-quick-field="source_type">
            <div class="form-grid quick-customer-fields">
                <div class="form-group col-4"><label class="required">اسم العميل</label><input class="input" data-quick-field="name" autocomplete="organization"></div>
                <div class="form-group col-2"><label>التصنيف</label><select class="select" data-quick-field="segment"><option value="standard">عادي</option><option value="startup">شركة ناشئة</option></select></div>
                <div class="form-group col-3"><label>الهاتف</label><input class="input" data-quick-field="phone" autocomplete="tel"></div>
                <div class="form-group col-3"><label>مسؤول التواصل</label><input class="input" data-quick-field="contact_name"></div>
                <div class="form-group col-3"><label>الدولة</label><input class="input" data-quick-field="country" value="السعودية"></div>
                <div class="form-group col-3"><label>المدينة</label><input class="input" data-quick-field="city"></div>
                <div class="form-group col-3"><label>السجل التجاري</label><input class="input" data-quick-field="commercial_registration_no"></div>
                <div class="form-group col-3"><label>الرقم الضريبي</label><input class="input" data-quick-field="tax_no"></div>
                <div class="form-group col-6"><label>البريد الإلكتروني</label><input class="input" type="email" data-quick-field="email" autocomplete="email"></div>
                <div class="form-group col-6 quick-customer-action"><label>&nbsp;</label><button class="btn btn-primary" type="button" data-quick-customer-save>حفظ العميل واختياره</button></div>
                <div class="col-12 quick-customer-message" data-form-message aria-live="polite"></div>
            </div>
        </div>
    </div>
    @endif
</div>
