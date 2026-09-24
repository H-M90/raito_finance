@props(['offers'=>collect(), 'selected'=>[]])
@php($selectedIds=collect(old('pricing_offer_ids',$selected))->map(fn($id)=>(int)$id)->all())
<div class="offer-selector" data-offer-selector>
    @forelse($offers as $offer)
        @php($productIds=$offer->products->pluck('id')->join(','))
        <label class="offer-card" data-offer-card>
            <input type="checkbox" name="pricing_offer_ids[]" value="{{ $offer->id }}" @checked(in_array($offer->id,$selectedIds,true))
                   data-pricing-offer data-offer-type="{{ $offer->type }}" data-cycle="{{ $offer->billing_cycle }}"
                   data-segment="{{ $offer->customer_segment }}" data-discount="{{ $offer->discount_percentage }}" data-fixed-discount="{{ $offer->fixed_discount_amount }}"
                   data-min-users="{{ $offer->minimum_users }}" data-free-users="{{ $offer->free_users }}" data-repeat="{{ $offer->repeat_for_each_threshold?1:0 }}"
                   data-products="{{ $productIds }}" data-stackable="{{ $offer->is_stackable?1:0 }}" data-priority="{{ $offer->priority }}">
            <span class="offer-check">✓</span>
            <span><strong>{{ $offer->name }}</strong><small>
                @if($offer->type==='free_users') {{ $offer->repeat_for_each_threshold?'كل':'عند طلب' }} {{ $offer->minimum_users }} مستخدم: {{ $offer->free_users }} مجاني
                @elseif($offer->type==='bundle_fixed_discount') خصم ثابت {{ number_format((float)$offer->fixed_discount_amount,2) }} عند وجود {{ $offer->products->pluck('name')->join(' + ') }}
                @else خصم {{ number_format((float)$offer->discount_percentage,2) }}% @endif
                · {{ $offer->billing_cycle==='all'?'كل الدوريات':(
                    \App\Support\FinanceOptions::billingCycles()[$offer->billing_cycle] ?? $offer->billing_cycle) }}
            </small></span>
        </label>
    @empty
        <div class="empty-state compact">لا توجد عروض تسعير فعالة حاليًا.</div>
    @endforelse
</div>
<div class="help" data-offer-message>خصم الشركات الناشئة يُفعّل تلقائيًا للعملاء المصنفين كشركات ناشئة.</div>
