<dialog id="{{ $dialogId }}" class="customer-reassess-dialog" aria-labelledby="{{ $dialogId }}-title">
    <form method="post" action="{{ route('customer-success.transition', $customer) }}">
        @csrf
        <div class="customer-reassess-head">
            <strong id="{{ $dialogId }}-title">إعادة تقييم {{ $customer->name }}</strong>
            <button type="button" class="icon-action" data-reassess-close aria-label="إغلاق نافذة إعادة التقييم"><x-ui-icon name="x" /></button>
        </div>
        <div class="form-group"><label>حالة العميل</label><select class="select" name="status_code" required>
            @foreach($statuses as $status)
                <option value="{{ $status->code }}" @selected($profile?->lifecycleStatus?->code===$status->code)>{{ $status->name }}</option>
            @endforeach
        </select></div>
        <div class="form-group"><label>سبب التغيير</label><input class="input" name="reason" maxlength="500" placeholder="اختياري"></div>
        <div class="customer-reassess-actions"><button type="button" class="btn btn-light" data-reassess-close>إلغاء</button><button class="btn btn-primary" type="submit">حفظ التقييم</button></div>
    </form>
</dialog>
