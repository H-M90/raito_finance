<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerHistoryController extends Controller
{
    public function __invoke(Request $request, Customer $customer, CustomerHistoryService $service): View
    {
        $history = $service->generate($customer,$request->input('from'),$request->input('to'),$request->input('type'));
        $types = ['quotation'=>'عروض المبيعات','contract'=>'العقود','addendum'=>'الملحقات','installation'=>'التركيبات','receivable'=>'الاستحقاقات','collection'=>'سندات القبض','discount_voucher'=>'سندات الخصم','purchase'=>'المشتريات','expense'=>'المصروفات'];
        return view('customers.history',compact('customer','history','types'));
    }
}
