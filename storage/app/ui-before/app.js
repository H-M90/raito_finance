const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const money = value => Number(value || 0).toLocaleString('ar-SA', {minimumFractionDigits: 2, maximumFractionDigits: 2});

document.querySelectorAll('[data-sidebar-toggle]').forEach(el => el.addEventListener('click', () => {
    document.querySelector('#sidebar')?.classList.toggle('open');
    document.querySelector('.sidebar-overlay')?.classList.toggle('open');
}));



function initCustomerSuccessTabs(root) {
    const tabs = [...root.querySelectorAll('[data-cs-tab]')];
    const panels = [...root.querySelectorAll('[data-cs-panel]')];
    if (!tabs.length || !panels.length) return;

    const activate = key => {
        const valid = tabs.some(tab => tab.dataset.csTab === key) ? key : 'overview';
        tabs.forEach(tab => {
            const active = tab.dataset.csTab === valid;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(panel => {
            const active = panel.dataset.csPanel === valid;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });
        return valid;
    };

    const hashPrefix = '#customer-success-';
    const initial = window.location.hash.startsWith(hashPrefix)
        ? window.location.hash.slice(hashPrefix.length)
        : 'overview';
    activate(initial);

    tabs.forEach(tab => tab.addEventListener('click', () => {
        const key = activate(tab.dataset.csTab);
        history.replaceState(null, '', `${window.location.pathname}${window.location.search}${hashPrefix}${key}`);
    }));

    root.querySelectorAll('[data-cs-open-tab]').forEach(button => button.addEventListener('click', () => {
        const key = activate(button.dataset.csOpenTab);
        history.replaceState(null, '', `${window.location.pathname}${window.location.search}${hashPrefix}${key}`);
        root.scrollIntoView({behavior: 'smooth', block: 'start'});
    }));

    if (window.location.hash.startsWith(hashPrefix)) {
        requestAnimationFrame(() => root.scrollIntoView({block: 'start'}));
    }
}

document.querySelectorAll('[data-customer-success-tabs]').forEach(initCustomerSuccessTabs);

const customerSuccessAdvanced = document.querySelector('#customer-success-advanced');
if (customerSuccessAdvanced && (window.location.hash === '#customer-success-advanced' || window.location.hash.startsWith('#customer-success-'))) {
    customerSuccessAdvanced.open = true;
}

document.addEventListener('click', event => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) document.querySelector(opener.dataset.modalOpen)?.classList.add('open');
    if (event.target.matches('[data-modal-close]') || event.target.classList.contains('modal')) event.target.closest('.modal')?.classList.remove('open');
    const lockedOffer = event.target.closest('[data-pricing-offer][data-locked="1"]');
    if (lockedOffer) event.preventDefault();
});

// Contract station section is controlled independently from pricing logic.
// This handler is intentionally registered early so a later JavaScript error
// cannot stop the stations section from appearing when activity = stations.
const syncContractStationsVisibility = form => {
    if (!(form instanceof HTMLFormElement)) return;
    const activity = form.querySelector('[data-contract-activity]');
    const section = form.querySelector('[data-contract-stations-section]');
    if (!activity || !section) return;
    const visible = activity.value === 'stations';
    section.hidden = !visible;
    section.setAttribute('aria-hidden', visible ? 'false' : 'true');
    section.querySelectorAll('input,select,textarea').forEach(control => { control.disabled = !visible; });
    section.querySelectorAll('button').forEach(button => { button.disabled = !visible; });
};

document.querySelectorAll('[data-contract-form]').forEach(syncContractStationsVisibility);
document.addEventListener('change', event => {
    if (!event.target.matches('[data-contract-activity]')) return;
    syncContractStationsVisibility(event.target.closest('[data-contract-form]'));
});

const syncQuotationStationPlanning = form => {
    if (!(form instanceof HTMLFormElement)) return;
    const activity = form.querySelector('[data-quotation-activity]');
    const group = form.querySelector('[data-quotation-station-count]');
    if (!activity || !group) return;
    const visible = activity.value === 'stations';
    group.hidden = !visible;
    group.setAttribute('aria-hidden', visible ? 'false' : 'true');
    group.querySelectorAll('input,select,textarea').forEach(control => { control.disabled = !visible; });
};
document.querySelectorAll('[data-quotation-form]').forEach(syncQuotationStationPlanning);
document.addEventListener('change', event => {
    if (!event.target.matches('[data-quotation-activity]')) return;
    syncQuotationStationPlanning(event.target.closest('[data-quotation-form]'));
});

function optionProductIds(offer) {
    return (offer.dataset.products || '').split(',').filter(Boolean).map(Number);
}
function offerAppliesToProduct(offer, productId) {
    const ids = optionProductIds(offer);
    return ids.length === 0 || ids.includes(Number(productId));
}

function initFinancialForm(form) {
    const container = form.querySelector('[data-line-items]');
    const template = form.querySelector('template[data-line-template]');
    if (!container || !template) return;
    let index = Number(container.dataset.nextIndex || container.querySelectorAll('[data-line-row]').length);
    const cycleInput = form.querySelector('[name="billing_cycle"]');
    const customerInput = form.querySelector('[name="customer_id"]');

    const currentCycle = () => cycleInput?.value || 'one_time';
    const customerSegment = () => customerInput?.selectedOptions?.[0]?.dataset.segment || 'standard';

    const syncOffers = () => {
        const cycle = currentCycle();
        const segment = customerSegment();
        form.querySelectorAll('[data-pricing-offer]').forEach(input => {
            const card = input.closest('[data-offer-card]');
            const cycleEligible = input.dataset.cycle === 'all' || input.dataset.cycle === cycle;
            const segmentEligible = input.dataset.segment === 'all' || input.dataset.segment === segment;
            const eligible = cycleEligible && segmentEligible;
            if (input.dataset.offerType === 'startup_discount') {
                input.checked = eligible && segment === 'startup';
                input.disabled = !eligible;
                input.dataset.locked = input.checked ? '1' : '0';
            } else {
                input.disabled = !eligible;
                if (!eligible) input.checked = false;
                input.dataset.locked = '0';
            }
            card?.classList.toggle('disabled', !eligible);
            card?.classList.toggle('selected', input.checked);
        });
        const message = form.querySelector('[data-offer-message]');
        if (message) message.textContent = segment === 'startup'
            ? 'تم تطبيق خصم الشركات الناشئة تلقائيًا حسب العرض الفعال.'
            : 'اختر العروض المناسبة. خصم الشركات الناشئة يظهر تلقائيًا عند تصنيف العميل كشركة ناشئة.';
    };

    const selectedOffers = () => [...form.querySelectorAll('[data-pricing-offer]:checked:not(:disabled)')];

    const userPriceFromOption = (option, cycle) => Number(option?.dataset[cycle === 'monthly' ? 'userMonthly' : cycle === 'annual' ? 'userAnnual' : 'userOneTime'] || 0);

    const applyProductDefaults = (row, force = false) => {
        const select = row.querySelector('[data-product-select]');
        const option = select?.selectedOptions?.[0];
        if (!option?.value) return;
        const price = row.querySelector('[data-price]');
        const maintenance = row.querySelector('[data-maintenance]');
        const userPrice = row.querySelector('[data-user-price]');
        const qty = row.querySelector('[data-qty]');
        const qtyField = row.querySelector('[data-quantity-field]');
        const supportsUsers = option.dataset.userPricing === '1';
        const isModule = option.dataset.type === 'erp_module';
        const isDelegateApp = option.dataset.code === 'APP-DELEGATES';
        const fixedSingleItem = isModule || supportsUsers;
        if (qty) { qty.value = fixedSingleItem ? '1' : (qty.value || '1'); qty.disabled = fixedSingleItem; }
        if (qtyField) qtyField.hidden = fixedSingleItem;
        if (force || price.value === '') price.value = isDelegateApp ? 0 : (option.dataset.price || 0);
        price.readOnly = isDelegateApp;
        if (force || maintenance.value === '') maintenance.value = isDelegateApp ? 0 : (option.dataset.maintenance || 0);
        maintenance.readOnly = isDelegateApp;
        if (force || userPrice.value === '') userPrice.value = userPriceFromOption(option, currentCycle());
        const requestedUsers = row.querySelector('[data-requested-users]');
        if (force && supportsUsers && requestedUsers) requestedUsers.value = isDelegateApp ? 1 : 0;
        const countLabel = row.querySelector('[data-user-count-label]');
        if (countLabel) countLabel.textContent = isDelegateApp ? 'عدد المناديب' : 'المستخدمون الإضافيون المدفوعون';
        const dependency = row.querySelector('[data-product-dependency-message]');
        if (dependency) dependency.textContent = option.dataset.requiredProductId ? `يتطلب وجود ${option.dataset.requiredProductName || 'البند المرتبط'} في العقد.` : '';
        row.dataset.initialized = '1';
    };

    const updateInstallments = () => {
        form.querySelectorAll('[data-installment-row]').forEach(row => {
            const percentage = Number(row.querySelector('[data-installment-percentage]')?.value || 0);
            if (percentage > 0) row.querySelector('[data-installment-amount]').value = (Number(form.dataset.netTotal || 0) * percentage / 100).toFixed(2);
        });
    };

    const recalculate = () => {
        syncOffers();
        const offers = selectedOffers();
        const cycle = currentCycle();
        const oneTime = cycle === 'one_time';
        let subtotal = 0, manualDiscountTotal = 0, promotionalDiscountTotal = 0, maintenanceTotal = 0;

        const selectedProductIds = new Set();
        const lineStates = [];

        container.querySelectorAll('[data-line-row]').forEach(row => {
            const select = row.querySelector('[data-product-select]');
            const option = select?.selectedOptions?.[0];
            const productId = Number(option?.value || 0);
            const duplicate = productId > 0 && selectedProductIds.has(productId);
            row.classList.toggle('has-error', duplicate);
            if (productId > 0) selectedProductIds.add(productId);
            const supportsUsers = option?.dataset.userPricing === '1';
            row.querySelectorAll('[data-user-field]').forEach(el => el.classList.toggle('field-disabled', !supportsUsers));
            const requestedInput = row.querySelector('[data-requested-users]');
            const userPriceInput = row.querySelector('[data-user-price]');
            if (requestedInput) requestedInput.disabled = !supportsUsers;
            if (userPriceInput) userPriceInput.disabled = !supportsUsers;

            const isModule = option?.dataset.type === 'erp_module';
            const isDelegateApp = option?.dataset.code === 'APP-DELEGATES';
            const fixedSingleItem = isModule || supportsUsers;
            const qtyInput = row.querySelector('[data-qty]');
            const qtyField = row.querySelector('[data-quantity-field]');
            if (qtyInput) { qtyInput.disabled = fixedSingleItem; if (fixedSingleItem) qtyInput.value = '1'; }
            if (qtyField) qtyField.hidden = fixedSingleItem;
            const qty = fixedSingleItem ? 1 : Number(qtyInput?.value || 0);
            const priceInput = row.querySelector('[data-price]');
            if (isDelegateApp && priceInput) { priceInput.value = '0'; priceInput.readOnly = true; }
            else if (priceInput) priceInput.readOnly = false;
            const price = isDelegateApp ? 0 : Number(priceInput?.value || 0);
            const countLabel = row.querySelector('[data-user-count-label]');
            if (countLabel) countLabel.textContent = isDelegateApp ? 'عدد المناديب' : 'المستخدمون الإضافيون المدفوعون';
            const dependency = row.querySelector('[data-product-dependency-message]');
            if (dependency) dependency.textContent = option?.dataset.requiredProductId ? `يتطلب وجود ${option.dataset.requiredProductName || 'البند المرتبط'} في العقد.` : '';
            const requested = supportsUsers ? Math.max(0, Math.floor(Number(requestedInput?.value || 0))) : 0;
            const included = supportsUsers && oneTime ? Number(option?.dataset.includedUsers || 0) : 0;

            let freeUserOffers = offers.filter(o => o.dataset.offerType === 'free_users' && offerAppliesToProduct(o, productId) && requested >= Number(o.dataset.minUsers || 0)).sort((a,b)=>Number(a.dataset.priority||100)-Number(b.dataset.priority||100));
            const nonStackableFree = freeUserOffers.find(o => o.dataset.stackable !== '1');
            if (nonStackableFree) freeUserOffers = [nonStackableFree];
            const freeFromOffers = freeUserOffers.reduce((sum, o) => {
                const threshold = Number(o.dataset.minUsers || 0);
                const multiplier = o.dataset.repeat === '1' && threshold > 0 ? Math.floor(requested / threshold) : 1;
                return sum + Number(o.dataset.freeUsers || 0) * Math.max(0, multiplier);
            }, 0);
            const promotionalFree = Math.max(0, freeFromOffers);
            const billable = requested;
            const totalLicensed = included + billable + promotionalFree;
            const userPrice = supportsUsers ? Number(userPriceInput?.value || 0) : 0;
            const userTotal = billable * userPrice;
            const lineSubtotal = qty * price + userTotal;
            const manualDiscount = Math.min(Math.max(0, Number(row.querySelector('[data-discount]')?.value || 0)), lineSubtotal);
            let afterOffers = lineSubtotal - manualDiscount;

            let percentageOffers = offers.filter(o => ['startup_discount', 'seasonal_discount'].includes(o.dataset.offerType) && offerAppliesToProduct(o, productId)).sort((a,b)=>Number(a.dataset.priority||100)-Number(b.dataset.priority||100));
            let lineOfferDiscount = 0;
            percentageOffers.forEach(o => {
                const value = afterOffers * Number(o.dataset.discount || 0) / 100;
                lineOfferDiscount += value;
                afterOffers -= value;
            });

            const maintenanceInput = row.querySelector('[data-maintenance]');
            if ((!oneTime || isDelegateApp) && maintenanceInput) maintenanceInput.value = 0;
            if (maintenanceInput) { maintenanceInput.disabled = !oneTime; maintenanceInput.readOnly = oneTime && isDelegateApp; }
            const maintenanceRate = oneTime && !isDelegateApp ? Number(maintenanceInput?.value || 0) : 0;

            const state = {
                row,
                productId,
                lineSubtotal,
                manualDiscount,
                lineOfferDiscount,
                lineNet: Math.max(0, afterOffers),
                maintenanceRate,
            };
            lineStates.push(state);
            subtotal += lineSubtotal;
            manualDiscountTotal += manualDiscount;

            const userSummary = row.querySelector('[data-user-summary]');
            if (userSummary) userSummary.textContent = supportsUsers
                ? (isDelegateApp ? `${billable} مندوب مدفوع + ${promotionalFree} مجاني = ${totalLicensed} مندوب` : `${included} أساسي + ${billable} مدفوع + ${promotionalFree} عرض = ${totalLicensed} مستخدم`)
                : 'لا يعتمد على المستخدمين';
        });

        // A bundle discount is applied once only when every product assigned to
        // that bundle offer exists in the current quotation/contract document.
        form.querySelectorAll('[data-pricing-offer][data-offer-type="bundle_fixed_discount"]').forEach(input => input.closest('[data-offer-card]')?.classList.remove('has-error'));
        const bundleOffers = offers.filter(o => o.dataset.offerType === 'bundle_fixed_discount').sort((a,b)=>Number(a.dataset.priority||100)-Number(b.dataset.priority||100));
        bundleOffers.forEach(offer => {
            const requiredIds = optionProductIds(offer);
            const qualifies = requiredIds.length >= 2 && requiredIds.every(id => selectedProductIds.has(id));
            offer.closest('[data-offer-card]')?.classList.toggle('has-error', !qualifies);
            if (!qualifies) return;

            const eligible = lineStates.filter(state => requiredIds.includes(state.productId) && state.lineNet > 0).sort((a,b)=>a.lineNet-b.lineNet);
            const bundleNet = eligible.reduce((sum, state) => sum + state.lineNet, 0);
            const fixedDiscount = Math.min(Math.max(0, Number(offer.dataset.fixedDiscount || 0)), bundleNet);
            if (bundleNet <= 0 || fixedDiscount <= 0 || eligible.length === 0) return;

            let remaining = Math.round(fixedDiscount * 100) / 100;
            eligible.forEach((state, idx) => {
                const isLast = idx === eligible.length - 1;
                let allocation = isLast ? remaining : Math.round((fixedDiscount * (state.lineNet / bundleNet)) * 100) / 100;
                allocation = Math.min(allocation, state.lineNet, remaining);
                remaining = Math.round((remaining - allocation) * 100) / 100;
                state.lineOfferDiscount += allocation;
                state.lineNet = Math.max(0, state.lineNet - allocation);
            });
        });

        lineStates.forEach(state => {
            const maintenance = state.lineNet * state.maintenanceRate / 100;
            promotionalDiscountTotal += state.lineOfferDiscount;
            maintenanceTotal += maintenance;
            state.row.querySelector('[data-line-total]')?.replaceChildren(document.createTextNode(money(state.lineNet)));
            const offerText = state.row.querySelector('[data-line-offer]');
            if (offerText) offerText.textContent = state.lineOfferDiscount > 0 ? `وفر ${money(state.lineOfferDiscount)}` : '';
        });

        const monetaryOffers = offers.filter(o => ['startup_discount', 'seasonal_discount', 'bundle_fixed_discount'].includes(o.dataset.offerType));
        const effectiveMonetaryOffers = monetaryOffers.filter(o => {
            if (o.dataset.offerType === 'bundle_fixed_discount') {
                const required = optionProductIds(o);
                return required.length >= 2 && required.every(id => selectedProductIds.has(id));
            }
            return [...selectedProductIds].some(id => offerAppliesToProduct(o, id));
        });
        const hasStackingConflict = effectiveMonetaryOffers.length > 1 && effectiveMonetaryOffers.some(o => o.dataset.stackable !== '1');
        const message = form.querySelector('[data-offer-message]');
        if (hasStackingConflict && message) {
            message.textContent = 'لا يمكن جمع الخصومات المالية المختارة لأن أحدها غير قابل للجمع. اختر خصمًا واحدًا أو عدّل إعداد قابلية الجمع.';
        } else if (bundleOffers.some(o => o.closest('[data-offer-card]')?.classList.contains('has-error')) && message) {
            message.textContent = 'عرض الباقة المحدد لن يطبق حتى تكون كل منتجات الباقة موجودة معًا.';
        }

        const discountTotal = manualDiscountTotal + promotionalDiscountTotal;
        const net = Math.max(0, subtotal - discountTotal);
        const tax = net * Number(form.dataset.taxRate || 15) / 100;
        const commissionType = form.querySelector('[data-commission-type]')?.value || '';
        const commissionValue = Math.max(0, Number(form.querySelector('[data-commission-value]')?.value || 0));
        const commissionTotal = commissionType === 'percentage' ? net * commissionValue / 100 : commissionType === 'fixed' ? commissionValue : 0;
        const commissionLabel = form.querySelector('[data-commission-value-label]');
        if (commissionLabel) commissionLabel.textContent = commissionType === 'percentage' ? 'نسبة العمولة %' : commissionType === 'fixed' ? 'قيمة العمولة' : 'النسبة / القيمة';
        form.querySelector('[data-commission-total]')?.replaceChildren(document.createTextNode(money(commissionTotal)));
        const currencyText = form.querySelector('[name="currency"]')?.value || 'SAR';
        form.querySelector('[data-commission-currency]')?.replaceChildren(document.createTextNode(currencyText));
        const values = {
            '[data-subtotal]': subtotal,
            '[data-manual-discount-total]': manualDiscountTotal,
            '[data-promotional-discount-total]': promotionalDiscountTotal,
            '[data-discount-total]': discountTotal,
            '[data-net-total]': net,
            '[data-tax-total]': tax,
            '[data-grand-total]': net + tax,
            '[data-maintenance-total]': oneTime ? maintenanceTotal : 0,
        };
        Object.entries(values).forEach(([selector, value]) => form.querySelector(selector)?.replaceChildren(document.createTextNode(money(value))));
        form.dataset.netTotal = String(net);
        form.querySelectorAll('[data-offer-card]').forEach(card => card.classList.toggle('selected', card.querySelector('input')?.checked));
        updateInstallments();
    };

    form.querySelector('[data-add-line]')?.addEventListener('click', () => {
        container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index++));
        recalculate();
    });
    container.addEventListener('click', e => {
        if (e.target.closest('[data-remove-line]') && container.querySelectorAll('[data-line-row]').length > 1) {
            e.target.closest('[data-line-row]').remove(); recalculate();
        }
    });
    container.addEventListener('input', recalculate);
    container.addEventListener('change', e => {
        if (e.target.matches('[data-product-select]')) applyProductDefaults(e.target.closest('[data-line-row]'), true);
        recalculate();
    });
    form.querySelector('[data-offer-selector]')?.addEventListener('change', recalculate);
    customerInput?.addEventListener('change', recalculate);
    cycleInput?.addEventListener('change', () => {
        container.querySelectorAll('[data-line-row]').forEach(row => {
            const option = row.querySelector('[data-product-select]')?.selectedOptions?.[0];
            if (option?.value) row.querySelector('[data-user-price]').value = userPriceFromOption(option, currentCycle());
        });
        recalculate();
    });
    form.querySelector('[data-commission-type]')?.addEventListener('change', recalculate);
    form.querySelector('[data-commission-value]')?.addEventListener('input', recalculate);
    form.querySelector('[name="currency"]')?.addEventListener('change', recalculate);
    container.querySelectorAll('[data-line-row]').forEach(row => applyProductDefaults(row, false));
    recalculate();
}

document.querySelectorAll('[data-financial-form]').forEach(initFinancialForm);

// تحويل عرض مقبول إلى عقد يجب أن يحافظ على نسخة التسعير التي وافق عليها العميل.
document.querySelectorAll('[data-financial-form][data-quotation-locked="1"]').forEach(form => {
    const lockedSelectors = [
        '[name="customer_id"]', '[name="activity_type"]', '[name="billing_cycle"]', '[name="currency"]',
        '[name="pts_count"]', '[name="sensor_count"]', '[name="intermediary_id"]',
        '[name="commission_type"]', '[name="commission_value"]', '[name^="pricing_offer_ids"]', '[name^="items["]'
    ];
    form.querySelectorAll(lockedSelectors.join(',')).forEach(field => {
        field.disabled = true;
        field.setAttribute('aria-disabled', 'true');
    });
    form.querySelectorAll('[data-add-line], [data-remove-line]').forEach(button => button.hidden = true);
    form.querySelector('[data-offer-selector]')?.classList.add('quote-locked');
    form.querySelector('[data-line-items]')?.classList.add('quote-locked');
});

const applyCreatedCustomer = data => {
    document.querySelectorAll('[data-customer-select]').forEach(select => {
        const option = new Option(`${data.name}${data.segment === 'startup' ? ' · ناشئة' : ''}`, data.id, true, true);
        option.dataset.segment = data.segment || 'standard';
        select.add(option);
        select.value = data.id;
        select.dispatchEvent(new Event('change', {bubbles:true}));
        select.dispatchEvent(new CustomEvent('smart-select:refresh'));
    });
};

document.querySelectorAll('[data-quick-customer-form]').forEach(form => form.addEventListener('submit', async e => {
    e.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const message = form.querySelector('[data-form-message]');
    button.disabled = true; message.textContent = 'جارٍ الحفظ...';
    try {
        const response = await fetch(form.action, {method:'POST', headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}, body:new FormData(form)});
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join('، ') || data.message);
        applyCreatedCustomer(data);
        form.closest('.modal')?.classList.remove('open'); form.reset(); message.textContent = '';
    } catch (error) { message.textContent = error.message; message.className = 'text-danger'; }
    finally { button.disabled = false; }
}));

document.querySelectorAll('[data-toggle-customer-create]').forEach(button => {
    button.addEventListener('click', () => {
        const card = button.closest('.customer-picker-card');
        const panel = card?.querySelector('[data-customer-new-details]');
        if (!panel) return;
        panel.hidden = !panel.hidden;
        button.textContent = panel.hidden ? '+ عميل جديد' : 'إخفاء الإضافة';
        if (!panel.hidden) panel.querySelector('[data-quick-field="name"]')?.focus();
    });
});

document.querySelectorAll('[data-quick-customer-card]').forEach(card => {
    const button = card.querySelector('[data-quick-customer-save]');
    const message = card.querySelector('[data-form-message]');
    if (!button) return;
    button.addEventListener('click', async () => {
        const fields = [...card.querySelectorAll('[data-quick-field]')];
        const name = card.querySelector('[data-quick-field="name"]');
        message.className = 'col-12 quick-customer-message';
        if (!name?.value.trim()) {
            message.textContent = 'اكتب اسم العميل أولًا.';
            message.classList.add('text-danger');
            name?.focus();
            return;
        }
        const body = new FormData();
        fields.forEach(field => body.append(field.dataset.quickField, field.value));
        button.disabled = true;
        message.textContent = 'جارٍ حفظ العميل...';
        try {
            const response = await fetch(card.dataset.action, {method:'POST', headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}, body});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join('، ') || data.message || 'تعذر حفظ العميل.');
            applyCreatedCustomer(data);
            fields.forEach(field => {
                if (field.dataset.quickField === 'segment') field.value = 'standard';
                else if (field.dataset.quickField === 'country') field.value = 'السعودية';
                else if (field.type !== 'hidden') field.value = '';
            });
            message.textContent = `تمت إضافة ${data.name} واختياره في العقد.`;
            message.classList.add('text-success');
            document.querySelector('[name="activity_type"]')?.focus();
            const panel = card.closest('[data-customer-new-details]');
            if (panel) panel.hidden = true;
            const toggle = panel?.closest('.customer-picker-card')?.querySelector('[data-toggle-customer-create]');
            if (toggle) toggle.textContent = '+ عميل جديد';
        } catch (error) {
            message.textContent = error.message;
            message.classList.add('text-danger');
        } finally {
            button.disabled = false;
        }
    });
});

const collectionForm = document.querySelector('[data-collection-form]');
if (collectionForm) {
    const customer = collectionForm.querySelector('[name="customer_id"]');
    const contract = collectionForm.querySelector('[name="contract_id"]');
    const currency = collectionForm.querySelector('[data-collection-currency]');
    const currencyLabel = collectionForm.querySelector('[data-collection-currency-label]');
    const rows = collectionForm.querySelector('[data-allocation-rows]');

    const message = text => {
        const box=document.createElement('div'); box.className='empty-state'; box.textContent=text; rows.replaceChildren(box);
    };
    const syncContractCurrency = () => {
        const option = contract?.selectedOptions?.[0];
        const value = option?.dataset.currency || '';
        if (currency) currency.value = value;
        if (currencyLabel) currencyLabel.textContent = value || '—';
    };
    const resetContract = () => {
        if (!contract) return;
        if (contract.value && contract.selectedOptions[0]?.dataset.customer !== String(customer?.value || '')) contract.value='';
        syncContractCurrency();
        if (!contract.value) message('اختر العقد لعرض الدفعات والصيانة والملحقات المستحقة.');
    };
    const load = async () => {
        syncContractCurrency();
        if (!customer?.value) { message('اختر العميل أولًا.'); return; }
        if (!contract?.value) { message('اختر العقد لعرض الاستحقاقات المفتوحة.'); return; }
        message('جارٍ تحميل استحقاقات العقد...');
        const url=new URL(collectionForm.dataset.outstandingUrl,window.location.origin);
        url.searchParams.set('contract_id',contract.value);
        const response=await fetch(url,{headers:{Accept:'application/json'}});
        if (!response.ok) { message('تعذر تحميل استحقاقات العقد.'); return; }
        const data=await response.json();
        if(!data.length){message('لا توجد دفعات أو صيانة أو استحقاقات ملحق مفتوحة لهذا العقد.');return;}
        const fragment=document.createDocumentFragment();
        data.forEach((r,i)=>{
            const row=document.createElement('div'); row.className='allocation-row receivable-choice';
            const details=document.createElement('div');
            const heading=document.createElement('div'); heading.className='inline-actions';
            const badge=document.createElement('span'); badge.className='badge badge-info'; badge.textContent=String(r.type_label||'استحقاق');
            const strong=document.createElement('strong'); strong.textContent=String(r.name||'استحقاق');
            heading.append(badge,strong);
            const small=document.createElement('small'); small.textContent=`استحقاق ${r.due_date||'—'} · المتبقي ${money(r.remaining_amount)} ${r.currency||''}`;
            details.append(heading,small);
            const id=document.createElement('input'); id.type='hidden'; id.name=`allocations[${i}][receivable_id]`; id.value=String(r.id);
            const control=document.createElement('div'); control.className='allocation-amount-control';
            const input=document.createElement('input'); input.className='input'; input.type='number'; input.min='0'; input.max=String(r.remaining_amount); input.step='0.01'; input.name=`allocations[${i}][amount]`; input.value='0';
            const full=document.createElement('button'); full.className='btn btn-sm btn-light'; full.type='button'; full.dataset.fillReceivable='1'; full.dataset.remaining=String(r.remaining_amount); full.textContent='سداد كامل';
            control.append(input,full); row.append(details,id,control); fragment.append(row);
        }); rows.replaceChildren(fragment);
    };

    customer?.addEventListener('change',()=>{ resetContract(); });
    contract?.addEventListener('change',load);
    rows?.addEventListener('click',event=>{
        const button=event.target.closest('[data-fill-receivable]'); if(!button)return;
        const input=button.closest('.allocation-amount-control')?.querySelector('input[type="number"]');
        if(input) input.value=Number(button.dataset.remaining||0).toFixed(2);
    });
    if(collectionForm.dataset.editing !== '1' && customer?.value && contract?.value) load(); else syncContractCurrency();
}

document.querySelectorAll('form').forEach(form => {
    const body=form.querySelector('[data-installments]'), template=form.querySelector('template[data-installment-template]'), add=form.querySelector('[data-add-installment]');
    if(!body||!template||!add)return; let index=Number(body.dataset.nextIndex||body.querySelectorAll('[data-installment-row]').length);
    add.addEventListener('click',()=>body.insertAdjacentHTML('beforeend',template.innerHTML.replaceAll('__INDEX__',index++)));
    body.addEventListener('input',e=>{if(e.target.matches('[data-installment-percentage]')){const row=e.target.closest('[data-installment-row]');row.querySelector('[data-installment-amount]').value=(Number(form.dataset.netTotal||0)*Number(e.target.value||0)/100).toFixed(2);}});
    body.addEventListener('click',e=>{if(e.target.closest('[data-remove-installment]')&&body.querySelectorAll('[data-installment-row]').length>1)e.target.closest('[data-installment-row]').remove();});
});


document.querySelectorAll('[data-contract-form]').forEach(form => {
    const section = form.querySelector('[data-contract-stations-section]');
    const container = form.querySelector('[data-contract-stations]');
    const template = form.querySelector('template[data-contract-station-template]');
    const add = form.querySelector('[data-add-contract-station]');
    const activity = form.querySelector('[data-contract-activity]');
    if (!section || !container || !template || !add || !activity) return;

    let index = Number(container.dataset.nextIndex || container.querySelectorAll('[data-contract-station-row]').length);
    const capacityPts = form.querySelector('[data-contract-pts-capacity]');
    const capacitySensors = form.querySelector('[data-contract-sensor-capacity]');
    const allocatedPts = form.querySelector('[data-contract-pts-allocated]');
    const allocatedSensors = form.querySelector('[data-contract-sensor-allocated]');
    const capacityStatus = form.querySelector('[data-station-capacity-status]');

    const itemCapacity = () => {
        let pts = 0, sensors = 0;
        form.querySelectorAll('[data-line-row]').forEach(row => {
            const option = row.querySelector('[data-product-select]')?.selectedOptions?.[0];
            const type = option?.dataset.type;
            if (type !== 'pts' && type !== 'sensor') return;
            const qty = Math.max(0, Math.floor(Number(row.querySelector('[data-qty]')?.value || 0)));
            if (type === 'pts') pts += qty;
            if (type === 'sensor') sensors += qty;
        });
        return {pts, sensors};
    };

    const stationAllocation = () => {
        let pts = 0, sensors = 0;
        container.querySelectorAll('[data-contract-station-row]').forEach(row => {
            pts += Math.max(0, Math.floor(Number(row.querySelector('[data-station-pts]')?.value || 0)));
            sensors += Math.max(0, Math.floor(Number(row.querySelector('[data-station-sensors]')?.value || 0)));
        });
        return {pts, sensors};
    };

    const refreshCapacity = () => {
        const capacity = itemCapacity();
        const allocated = stationAllocation();
        if (capacityPts) capacityPts.textContent = String(capacity.pts);
        if (capacitySensors) capacitySensors.textContent = String(capacity.sensors);
        if (allocatedPts) allocatedPts.textContent = String(allocated.pts);
        if (allocatedSensors) allocatedSensors.textContent = String(allocated.sensors);
        const exceeded = allocated.pts > capacity.pts || allocated.sensors > capacity.sensors;
        if (capacityStatus) {
            capacityStatus.textContent = exceeded ? 'التوزيع أكبر من كميات بنود PTS / الحساسات' : 'التوزيع داخل حدود بنود PTS والحساسات';
            capacityStatus.classList.toggle('is-error', exceeded);
        }
    };

    const syncVisibility = () => {
        syncContractStationsVisibility(form);
        refreshCapacity();
    };

    add.addEventListener('click', () => {
        container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index++));
        container.lastElementChild?.querySelector('input:not([type="hidden"])')?.focus();
        refreshCapacity();
    });

    container.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-contract-station]');
        if (!button) return;
        const row = button.closest('[data-contract-station-row]');
        if (container.querySelectorAll('[data-contract-station-row]').length === 1) {
            row.querySelectorAll('input').forEach(input => { if (input.type !== 'hidden') input.value = input.matches('[type="number"]') ? '0' : ''; });
            row.querySelector('input[type="hidden"]')?.setAttribute('value','');
        } else {
            row.remove();
        }
        refreshCapacity();
    });

    form.addEventListener('input', event => {
        if (event.target.matches('[data-qty],[data-station-pts],[data-station-sensors]')) refreshCapacity();
    });
    form.addEventListener('change', event => {
        if (event.target.matches('[data-product-select]')) refreshCapacity();
        if (event.target === activity) syncVisibility();
    });

    syncVisibility();
});

document.querySelectorAll('[data-contract-items-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const row = document.getElementById(button.dataset.target);
        if (!row) return;
        const expanded = row.hidden;
        row.hidden = !expanded;
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
});

document.querySelectorAll('[data-contract-form]').forEach(form => {
    const section=form.querySelector('[data-contract-installments-section]');
    const cycle=form.querySelector('[name="billing_cycle"]');
    const legacyToggle=form.querySelector('[data-legacy-contract-toggle]');
    const legacyValue=form.querySelector('[data-legacy-contract-value]');
    if(!section||!cycle)return;
    const sync=()=>{
        const legacy=legacyToggle?.checked===true;
        const visible=cycle.value==='one_time'&&!legacy;
        section.hidden=!visible;
        section.querySelectorAll('input,select,textarea,button').forEach(control=>{ control.disabled=!visible; });
        if(legacyValue){
            legacyValue.hidden=!legacy;
            legacyValue.querySelectorAll('input,select,textarea').forEach(control=>{ control.disabled=!legacy; });
        }
    };
    cycle.addEventListener('change',sync);
    legacyToggle?.addEventListener('change',sync);
    sync();
});

document.querySelectorAll('[data-intermediary-form]').forEach(form => {
    const toggle=form.querySelector('[data-has-intermediary]');
    const panel=form.querySelector('[data-intermediary-panel]');
    if(!toggle||!panel)return;

    const panelControls=[...panel.querySelectorAll('select,input,textarea')];
    panelControls.forEach(control=>{ if(control.disabled) control.dataset.intermediaryLocked='1'; });

    const isEnabled=()=>toggle.type==='checkbox' ? toggle.checked : String(toggle.value)==='1';
    const sync=()=>{
        const visible=isEnabled();
        panel.hidden=!visible;
        form.classList.toggle('has-intermediary-enabled',visible);
        panelControls.forEach(control=>{
            control.disabled=!visible || control.dataset.intermediaryLocked==='1';
            if(control instanceof HTMLSelectElement) control.dispatchEvent(new CustomEvent('smart-select:refresh'));
        });
        if(!visible){
            const createPanel=panel.querySelector('[data-intermediary-create-panel]');
            if(createPanel)createPanel.hidden=true;
        }
    };
    toggle.addEventListener('change',sync); sync();
});

document.querySelectorAll('[data-toggle-intermediary-create]').forEach(button=>{
    button.addEventListener('click',()=>{
        const card=button.closest('[data-intermediary-panel]');
        const panel=card?.querySelector('[data-intermediary-create-panel]');
        if(!panel)return;
        panel.hidden=!panel.hidden;
        button.textContent=panel.hidden?'+ إضافة وسيط':'إخفاء الإضافة';
        if(!panel.hidden)panel.querySelector('[data-intermediary-field="name"]')?.focus();
    });
});

document.querySelectorAll('[data-intermediary-create-panel]').forEach(panel=>{
    const save=panel.querySelector('[data-quick-intermediary-save]');
    const message=panel.querySelector('[data-intermediary-message]');
    if(!save)return;
    save.addEventListener('click',async()=>{
        const name=panel.querySelector('[data-intermediary-field="name"]');
        if(message){ message.className='col-12 quick-intermediary-message'; message.textContent=''; }
        if(!name?.value.trim()){
            if(message){message.textContent='اكتب اسم الوسيط أولًا.';message.classList.add('text-danger');}
            name?.focus();
            return;
        }
        const body=new FormData();
        panel.querySelectorAll('[data-intermediary-field]').forEach(field=>body.append(field.dataset.intermediaryField,field.value));
        save.disabled=true;
        if(message)message.textContent='جارٍ حفظ الوسيط...';
        try{
            const response=await fetch(panel.dataset.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body});
            const data=await response.json();
            if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join('، ')||data.message||'تعذر حفظ الوسيط.');
            const select=panel.closest('[data-intermediary-panel]')?.querySelector('[data-intermediary-select]');
            if(select){
                const existing=[...select.options].find(option=>String(option.value)===String(data.id));
                const option=existing||new Option(data.name,data.id,true,true);
                if(!existing)select.add(option);
                option.textContent=data.name;
                select.value=String(data.id);
                select.dispatchEvent(new Event('change',{bubbles:true}));
                select.dispatchEvent(new CustomEvent('smart-select:refresh'));
            }
            panel.querySelectorAll('[data-intermediary-field]').forEach(field=>field.value='');
            if(message){message.textContent=`تمت إضافة ${data.name} واختياره في العقد.`;message.classList.add('text-success');}
            window.setTimeout(()=>{
                panel.hidden=true;
                const toggleButton=panel.closest('[data-intermediary-panel]')?.querySelector('[data-toggle-intermediary-create]');
                if(toggleButton)toggleButton.textContent='+ إضافة وسيط';
            },450);
        }catch(error){
            if(message){message.textContent=error.message;message.classList.add('text-danger');}
        }finally{save.disabled=false;}
    });
});

const userPricingToggle=document.querySelector('[data-user-pricing-toggle]');
if(userPricingToggle){const sync=()=>document.querySelector('[data-user-pricing-fields]')?.classList.toggle('disabled-panel',!userPricingToggle.checked);userPricingToggle.addEventListener('change',sync);sync();}

function filterDependentSelect(select, customerId) {
    if (!select) return;
    [...select.options].forEach((option,index)=>{ if(index===0)return; option.hidden=Boolean(customerId)&&option.dataset.customer!==String(customerId); });
    if(select.selectedOptions[0]?.hidden) select.value='';
    select.dispatchEvent(new CustomEvent('smart-select:refresh'));
}
document.querySelectorAll('[data-customer-dependent-form]').forEach(form=>{
    const customer=form.querySelector('[data-parent-customer]');
    const sync=()=>form.querySelectorAll('[data-dependent-contract],[data-dependent-station]').forEach(select=>filterDependentSelect(select,customer.value));
    customer?.addEventListener('change',sync);sync();
});

const purchaseForm=document.querySelector('[data-purchase-form]');
if(purchaseForm){
    const items=purchaseForm.querySelector('[data-purchase-items]'), itemTemplate=purchaseForm.querySelector('[data-purchase-item-template]'), allocations=purchaseForm.querySelector('[data-purchase-allocations]'), allocationTemplate=purchaseForm.querySelector('[data-purchase-allocation-template]');
    let itemIndex=Number(items.dataset.nextIndex||items.children.length), allocationIndex=Number(allocations.dataset.nextIndex||allocations.children.length);
    const refreshItemOptions=()=>{const labels=[...items.querySelectorAll('[data-purchase-item-row]')].map(row=>({index:row.dataset.itemIndex,label:row.querySelector('[name$="[description]"]')?.value||`بند ${Number(row.dataset.itemIndex)+1}`})); allocations.querySelectorAll('[data-allocation-item]').forEach(select=>{const current=select.value;const options=[new Option('اختر البند','')];labels.forEach(x=>options.push(new Option(x.label,x.index)));select.replaceChildren(...options);select.value=current;});};
    const calculate=()=>{let total=0;items.querySelectorAll('[data-purchase-item-row]').forEach(row=>{const line=Number(row.querySelector('[data-purchase-qty]').value||0)*Number(row.querySelector('[data-purchase-cost]').value||0);total+=line;row.querySelector('[data-purchase-line-total]').textContent=money(line);});purchaseForm.querySelector('[data-purchase-total]').textContent=money(total);refreshItemOptions();};
    purchaseForm.querySelector('[data-add-purchase-item]')?.addEventListener('click',()=>{items.insertAdjacentHTML('beforeend',itemTemplate.innerHTML.replaceAll('__INDEX__',itemIndex));items.lastElementChild.dataset.itemIndex=itemIndex++;calculate();});
    purchaseForm.querySelector('[data-add-purchase-allocation]')?.addEventListener('click',()=>{allocations.insertAdjacentHTML('beforeend',allocationTemplate.innerHTML.replaceAll('__INDEX__',allocationIndex++));refreshItemOptions();});
    purchaseForm.addEventListener('input',calculate);purchaseForm.addEventListener('change',e=>{if(e.target.matches('[data-allocation-customer]')){const row=e.target.closest('[data-purchase-allocation-row]');filterDependentSelect(row.querySelector('[data-dependent-contract]'),e.target.value);filterDependentSelect(row.querySelector('[data-dependent-station]'),e.target.value);}calculate();});
    purchaseForm.addEventListener('click',e=>{if(e.target.closest('[data-remove-purchase-item]')&&items.children.length>1)e.target.closest('[data-purchase-item-row]').remove();if(e.target.closest('[data-remove-purchase-allocation]'))e.target.closest('[data-purchase-allocation-row]').remove();calculate();});calculate();
}

const installationForm=document.querySelector('[data-installation-form]');
if(installationForm){
    const customer=installationForm.querySelector('[name="customer_id"]'), contract=installationForm.querySelector('[name="contract_id"]'), body=installationForm.querySelector('[data-installation-stations]'), template=installationForm.querySelector('[data-installation-station-template]');let index=Number(body.dataset.nextIndex||body.children.length);
    const sync=()=>{filterDependentSelect(contract,customer?.value);body.querySelectorAll('[data-station-select]').forEach(s=>filterDependentSelect(s,customer?.value));};
    customer?.addEventListener('change',()=>{ if(contract) contract.value=''; contract?.dispatchEvent(new Event('change',{bubbles:true})); sync(); });
    contract?.addEventListener('change',()=>{ body.querySelectorAll('[data-station-select]').forEach(select=>{select.value='';select.dispatchEvent(new CustomEvent('smart-select:refresh'));}); });
    installationForm.querySelector('[data-add-installation-station]')?.addEventListener('click',()=>{body.insertAdjacentHTML('beforeend',template.innerHTML.replaceAll('__INDEX__',index++));initSmartSelects(body.lastElementChild);sync();});
    installationForm.addEventListener('click',e=>{if(e.target.closest('[data-remove-installation-station]')&&body.children.length>1)e.target.closest('[data-installation-station-row]').remove();});sync();
}

// Keep the promotion form focused on the fields that match the selected rule.
document.querySelectorAll('[data-pricing-offer-form]').forEach(form => {
    const type = form.querySelector('[data-offer-type]');
    const segment = form.querySelector('[data-offer-segment]');
    const cycle = form.querySelector('[data-offer-cycle]');
    const discount = form.querySelector('[data-offer-discount]');
    const fixedDiscount = form.querySelector('[data-offer-fixed-discount]');
    const help = form.querySelector('[data-offer-discount-help]');
    const startupDiscount = Number(form.dataset.startupDiscount || 50);
    const sync = () => {
        const isFreeUsers = type?.value === 'free_users';
        const isStartup = type?.value === 'startup_discount';
        const isBundle = type?.value === 'bundle_fixed_discount';
        const isPercentage = ['startup_discount', 'seasonal_discount'].includes(type?.value || '');
        form.querySelectorAll('[data-discount-fields]').forEach(el => el.classList.toggle('field-hidden', !isPercentage));
        form.querySelectorAll('[data-fixed-discount-fields]').forEach(el => el.classList.toggle('field-hidden', !isBundle));
        form.querySelectorAll('[data-free-user-fields]').forEach(el => el.classList.toggle('field-hidden', !isFreeUsers));
        if (isStartup) {
            if (segment) { segment.value = 'startup'; segment.disabled = true; }
            if (cycle) { cycle.value = 'all'; cycle.disabled = true; }
            if (discount) { discount.value = startupDiscount.toFixed(2); discount.readOnly = true; }
            if (help) help.textContent = `قاعدة ثابتة: الشركات الناشئة تحصل على خصم ${startupDiscount}% لجميع دوريات الفوترة.`;
        } else {
            if (segment) segment.disabled = false;
            if (cycle) cycle.disabled = false;
            if (discount) discount.readOnly = false;
            if (help) help.textContent = 'حدد نسبة الخصم الموسمي. خصم الشركات الناشئة مضبوط تلقائيًا بواسطة النظام.';
        }
        if (fixedDiscount) fixedDiscount.required = isBundle;
    };
    type?.addEventListener('change', sync);
    sync();
});

// V0.6.9: official Select2 + Bootstrap 5 theme. No custom combobox implementation.
const select2Instances = new WeakSet();

const select2Placeholder = select => select.options[0]?.value === ''
    ? (select.options[0]?.textContent?.trim() || 'اختر')
    : 'اختر';

const copyLookupMetadataToSelectedOption = (select, item) => {
    const option = select.selectedOptions?.[0];
    if (!option || !item) return;
    Object.entries(item).forEach(([key, value]) => {
        if (['id', 'text', 'element', 'selected', 'disabled'].includes(key) || value === null || value === undefined || typeof value === 'object') return;
        const dataKey = key.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
        option.dataset[dataKey] = String(value);
    });
};

const select2RequestParams = (select, params) => {
    const query = { q: params.term || '' };
    const form = select.closest('form');
    const parentCustomer = form?.querySelector('[data-parent-customer], [name="customer_id"]');
    if (parentCustomer && parentCustomer !== select && parentCustomer.value) query.customer_id = parentCustomer.value;
    if (select.dataset.activityTypeFilter) query.activity_type = select.dataset.activityTypeFilter;
    if (select.matches('[data-station-select]')) {
        const parentContract = form?.querySelector('[name="contract_id"]');
        if (parentContract?.value) query.contract_id = parentContract.value;
    }
    return query;
};

const initSelect2 = select => {
    if (!(select instanceof HTMLSelectElement) || select2Instances.has(select)) return;
    if (!window.jQuery?.fn?.select2) return;

    const $ = window.jQuery;
    const $select = $(select);
    const isReference = Boolean(select.dataset.remoteUrl || select.dataset.smartSelect === '1');
    const hasBlankOption = select.options[0]?.value === '';
    const modalParent = select.closest('.modal-card');
    const options = {
        theme: 'bootstrap-5',
        dir: 'rtl',
        width: '100%',
        placeholder: select2Placeholder(select),
        allowClear: isReference && !select.required && hasBlankOption,
        minimumResultsForSearch: isReference ? 0 : Infinity,
        language: {
            noResults: () => 'لا توجد نتائج',
            searching: () => 'جارٍ البحث...',
            inputTooShort: () => 'اكتب حرفين على الأقل للبحث',
            errorLoading: () => 'تعذر تحميل النتائج',
            loadingMore: () => 'جارٍ تحميل المزيد...'
        },
        templateResult: data => data.element?.hidden ? null : data.text,
    };

    if (modalParent) options.dropdownParent = $(modalParent);

    if (select.dataset.remoteUrl) {
        options.minimumInputLength = 0;
        options.ajax = {
            url: select.dataset.remoteUrl,
            dataType: 'json',
            delay: 250,
            cache: true,
            headers: { Accept: 'application/json' },
            data: params => select2RequestParams(select, params),
            processResults: data => ({
                results: (Array.isArray(data) ? data : []).map(item => ({ ...item, id: String(item.id), text: String(item.text) }))
            })
        };
    }

    $select.select2(options);
    select2Instances.add(select);

    $select.on('select2:select', event => {
        copyLookupMetadataToSelectedOption(select, event.params?.data);
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    $select.on('select2:clear', () => select.dispatchEvent(new Event('change', { bubbles: true })));
    select.addEventListener('smart-select:refresh', () => $select.trigger('change.select2'));

    new MutationObserver(() => {
        $select.prop('disabled', select.disabled).trigger('change.select2');
    }).observe(select, { attributes: true, attributeFilter: ['disabled'] });
};

const initSelect2s = root => {
    if (root instanceof HTMLSelectElement && root.matches('select.select, select[data-remote-url], select[data-smart-select="1"]')) initSelect2(root);
    root.querySelectorAll?.('select.select, select[data-remote-url], select[data-smart-select="1"]').forEach(initSelect2);
};

const bootSelect2 = () => {
    initSelect2s(document);
    new MutationObserver(mutations => mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
        if (node instanceof Element) initSelect2s(node);
    }))).observe(document.body, { childList: true, subtree: true });
};

if (window.jQuery?.fn?.select2) bootSelect2();
else window.addEventListener('load', bootSelect2, { once: true });

// Bank statement review: classify each bank line, complete its data, then post the whole batch once.
document.querySelectorAll('[data-bank-row-form]').forEach(form => {
    const classification = form.querySelector('[data-bank-classification]');
    const expensePanel = form.querySelector('[data-bank-expense-panel]');
    const collectionPanel = form.querySelector('[data-bank-collection-panel]');
    const collectionCustomer = form.querySelector('[data-bank-collection-customer]');
    const allocations = form.querySelector('[data-bank-allocations]');
    const summary = form.querySelector('[data-bank-allocation-summary]');
    const feedback = form.querySelector('[data-bank-allocation-feedback]');
    const amount = Number(form.dataset.amount || 0);
    const outstandingUrl = form.dataset.outstandingUrl;
    const expenseCustomer = form.querySelector('[name="expense_customer_id"]');
    const expenseContract = form.querySelector('[name="expense_contract_id"]');

    const syncPanels = () => {
        const type = classification?.value;
        expensePanel?.classList.toggle('field-hidden', type !== 'expense');
        collectionPanel?.classList.toggle('field-hidden', type !== 'collection');
    };

    const refreshAllocationSummary = () => {
        const total = [...form.querySelectorAll('[data-bank-allocation-amount]')].reduce((sum,input)=>sum+Number(input.value||0),0);
        const difference = Math.round((amount-total)*100)/100;
        if (summary) summary.textContent = `موزع ${money(total)} من ${money(amount)}${Math.abs(difference)>0.009?` · متبقي ${money(difference)}`:' · مكتمل'}`;
    };

    const createAllocationRow = (receivable, value, index) => {
        const row = document.createElement('div'); row.className='allocation-row'; row.dataset.bankAllocationRow='';
        const info = document.createElement('div');
        const title = document.createElement('strong'); title.textContent = `${receivable.number} · ${receivable.name}`;
        const small = document.createElement('small'); small.textContent = `متبقي ${money(receivable.remaining_amount)} · استحقاق ${receivable.due_date}`;
        const hidden = document.createElement('input'); hidden.type='hidden'; hidden.name=`allocations[${index}][receivable_id]`; hidden.value=receivable.id;
        info.append(title, small, hidden);
        const input = document.createElement('input'); input.className='input'; input.type='number'; input.step='0.01'; input.min='0'; input.max=receivable.remaining_amount; input.name=`allocations[${index}][amount]`; input.value=Number(value).toFixed(2); input.dataset.bankAllocationAmount='';
        const remove = document.createElement('button'); remove.type='button'; remove.className='btn btn-sm btn-danger'; remove.dataset.bankRemoveAllocation=''; remove.textContent='حذف';
        row.append(info,input,remove); return row;
    };

    classification?.addEventListener('change', syncPanels);
    expenseCustomer?.addEventListener('change',()=>expenseContract && filterDependentSelect(expenseContract,expenseCustomer.value));
    if (expenseContract && expenseCustomer) filterDependentSelect(expenseContract,expenseCustomer.value);
    collectionCustomer?.addEventListener('change',()=>{ if(allocations) allocations.replaceChildren(); refreshAllocationSummary(); });
    form.querySelector('[data-bank-load-receivables]')?.addEventListener('click', async () => {
        if (!collectionCustomer?.value) { if(feedback){feedback.textContent='اختر العميل أولًا.';feedback.className='bank-inline-feedback is-error';} return; }
        if(feedback){feedback.textContent='جارٍ تحميل الاستحقاقات...';feedback.className='bank-inline-feedback';}
        const url = new URL(outstandingUrl, window.location.origin); url.searchParams.set('customer_id', collectionCustomer.value);
        const response = await fetch(url,{headers:{Accept:'application/json'}});
        if (!response.ok) { if(feedback){feedback.textContent='تعذر تحميل استحقاقات العميل. حاول مرة أخرى.';feedback.className='bank-inline-feedback is-error';} return; }
        const items = await response.json();
        let remaining = amount; const nodes=[]; let index=0;
        for (const item of items) {
            if (remaining <= 0.009) break;
            const available = Number(item.remaining_amount || 0); if (available <= 0) continue;
            const value = Math.min(remaining, available); nodes.push(createAllocationRow(item,value,index++)); remaining = Math.round((remaining-value)*100)/100;
        }
        allocations?.replaceChildren(...nodes); refreshAllocationSummary();
        if (feedback) {
            feedback.textContent = remaining > 0.009
                ? `إجمالي الاستحقاقات المتاحة أقل من التحصيل بمقدار ${money(remaining)}. لا يمكن اعتماد دفع زائد.`
                : 'تم توزيع مبلغ الحركة بالكامل على الاستحقاقات الأقدم.';
            feedback.className = `bank-inline-feedback ${remaining > 0.009 ? 'is-error' : 'is-success'}`;
        }
    });
    form.addEventListener('input', event => { if (event.target.matches('[data-bank-allocation-amount]')) refreshAllocationSummary(); });
    form.addEventListener('click', event => { const btn=event.target.closest('[data-bank-remove-allocation]'); if(btn){btn.closest('[data-bank-allocation-row]')?.remove();refreshAllocationSummary();} });
    syncPanels(); refreshAllocationSummary();
});


// Show validation state on the visible Select2 control only after validation is attempted.
document.addEventListener('invalid', event => {
    if (event.target instanceof HTMLSelectElement && event.target.classList.contains('select2-hidden-accessible')) {
        event.target.nextElementSibling?.classList.add('is-invalid');
    }
}, true);
document.addEventListener('change', event => {
    if (event.target instanceof HTMLSelectElement && event.target.classList.contains('select2-hidden-accessible') && event.target.value) {
        event.target.nextElementSibling?.classList.remove('is-invalid');
    }
});

// Shared UX helpers: branded confirmations, submit locking, printing and long-form protection.
let pendingConfirmationForm = null;
const confirmModal = document.querySelector('[data-confirm-modal]');
const confirmMessage = confirmModal?.querySelector('[data-confirm-message]');
const closeConfirmModal = () => { confirmModal?.classList.remove('open'); confirmModal?.setAttribute('aria-hidden','true'); pendingConfirmationForm=null; };
confirmModal?.querySelectorAll('[data-confirm-cancel]').forEach(button => button.addEventListener('click', closeConfirmModal));
confirmModal?.addEventListener('click', event => { if(event.target === confirmModal) closeConfirmModal(); });
document.addEventListener('keydown', event => { if(event.key === 'Escape' && confirmModal?.classList.contains('open')) closeConfirmModal(); });
confirmModal?.querySelector('[data-confirm-accept]')?.addEventListener('click', () => {
    const form = pendingConfirmationForm;
    if (!form) return closeConfirmModal();
    confirmModal.classList.remove('open'); confirmModal.setAttribute('aria-hidden','true'); pendingConfirmationForm=null;
    form.dataset.confirmed = '1'; form.requestSubmit();
});

document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const message = form.dataset.confirm;
    if (message && form.dataset.confirmed !== '1') {
        event.preventDefault(); pendingConfirmationForm=form;
        if(confirmMessage) confirmMessage.textContent=message;
        confirmModal?.classList.add('open'); confirmModal?.setAttribute('aria-hidden','false');
        confirmModal?.querySelector('[data-confirm-accept]')?.focus();
        return;
    }
    delete form.dataset.confirmed;
    if (form.dataset.submitLock !== undefined || form.matches('[data-financial-form], [data-bank-row-form], [data-purchase-form], [data-installation-form]')) {
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => {
            button.disabled = true; button.setAttribute('aria-busy','true');
            if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
            button.textContent = 'جار الحفظ...';
        });
    }
    form.dataset.submitted = '1';
});

document.querySelectorAll('[data-print-page]').forEach(button => button.addEventListener('click', () => window.print()));
if (document.body?.dataset.autoPrint === '1') window.addEventListener('load', () => window.print(), {once:true});

const importMonitor = document.querySelector('[data-import-monitor]');
if (importMonitor?.dataset.statusUrl) {
    const terminal = ['reviewing','failed','completed','rolled_back'];
    const poll = async () => {
        try {
            const response = await fetch(importMonitor.dataset.statusUrl,{headers:{Accept:'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            const progress=document.querySelector('[data-import-progress]'), status=document.querySelector('[data-import-status]');
            if(progress) progress.textContent=`${data.progress_percentage}%`;
            if(status) status.textContent=data.status;
            if(terminal.includes(data.status)) { window.location.reload(); return; }
        } catch (_) { /* keep the page usable if a single poll fails */ }
        window.setTimeout(poll,2500);
    };
    window.setTimeout(poll,1200);
}

const protectedForms = document.querySelectorAll('[data-financial-form], [data-bank-row-form], [data-purchase-form], [data-installation-form], .import-row-form');
const dirtyForms = new Set();
protectedForms.forEach(form => {
    const markDirty = () => { if(form.dataset.submitted !== '1') dirtyForms.add(form); };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    form.addEventListener('submit', () => { dirtyForms.delete(form); });
    form.addEventListener('reset', () => { dirtyForms.delete(form); });
});
window.addEventListener('beforeunload', event => { if(dirtyForms.size>0){ event.preventDefault(); event.returnValue=''; } });
