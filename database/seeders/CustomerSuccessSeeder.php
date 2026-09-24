<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAttentionFlagType;
use App\Models\CustomerSuccessProfile;
use App\Models\CustomerPlaybook;
use App\Models\CustomerSuccessStatus;
use Illuminate\Database\Seeder;

class CustomerSuccessSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['NEW','عميل جديد','high'],['ACTIVATING','تحت التفعيل','high'],['STABILIZING','فترة الاستقرار','high'],['STABLE','مستقر','normal'],
            ['LOW_ADOPTION','استخدام منخفض','high'],['DORMANT','خامل / متوقف','high'],['AT_RISK','معرض للفقد','critical'],['CHURNED','مفقود / منتهي','closed'],
        ];
        foreach ($statuses as $i => [$code,$name,$attention]) {
            CustomerSuccessStatus::updateOrCreate(['code'=>$code], ['name'=>$name,'default_attention'=>$attention,'sort_order'=>$i+1,'is_active'=>true]);
        }

        $flags = [
            ['ACCOUNTANT_CHANGED','تغيير المحاسب','high',30,'manual','PB_ACCOUNTANT_CHANGED'],
            ['FINANCE_MANAGER_CHANGED','تغيير المدير المالي','high',30,'manual','PB_FIN_MGR_CHANGED'],
            ['MANAGEMENT_CHANGED','تغيير الإدارة/صاحب القرار','high',45,'manual','PB_MANAGEMENT_CHANGED'],
            ['PROCESS_CHANGED','تغيير إجراءات العمل','high',null,'manual','PB_PROCESS_CHANGED'],
            ['COMPANY_EXPANSION','توسع الشركة','medium',90,'manual','PB_EXPANSION'],
            ['LIMITED_USAGE','استخدام محدود للميزات','medium',30,'manual','PB_LIMITED_USAGE'],
            ['LOW_USAGE','انخفاض الاستخدام','high',30,'telemetry','PB_LOW_USAGE'],
            ['REPEATED_COMPLAINTS','شكاوى متكررة','high',30,'support','PB_REPEATED_COMPLAINTS'],
            ['CRITICAL_ISSUE','مشكلة حرجة','critical',null,'support','PB_CRITICAL_ISSUE'],
            ['HIGH_SUPPORT_LOAD','دعم زائد / ضعف تمكين','medium',30,'support','PB_HIGH_SUPPORT'],
            ['RENEWAL_DUE','تجديد قريب','high',null,'contracts','PB_RENEWAL'],
            ['PAYMENT_OVERDUE','متأخر ماليًا','high',null,'receivables','PB_PAYMENT_OVERDUE'],
            ['CANCELLATION_REQUESTED','يريد الإلغاء','critical',null,'manual','PB_CANCEL_REQ'],
        ];
        foreach ($flags as [$code,$name,$severity,$days,$source,$playbook]) {
            CustomerAttentionFlagType::updateOrCreate(['code'=>$code], compact('name') + ['default_severity'=>$severity,'default_days'=>$days,'source'=>$source,'playbook_code'=>$playbook,'is_active'=>true]);
        }

        $pbs = [
            ['PB_NEW_CUSTOMER','عميل جديد','event','FIRST_CONTRACT','فريق نجاح العملاء',24,'بدء التجهيز الفعلي للنظام','ACTIVATING','ترحيب بالعميل؛تحديد صاحب القرار والمستخدمين الرئيسيين؛تحديد أهداف العميل؛تحديد الجدول الزمني؛تحديد البيانات المطلوبة؛تحديد موعد التدريب؛تأكيد قنوات الدعم؛متابعة كل 3–5 أيام'],
            ['PB_ACTIVATION','تحت التفعيل','lifecycle','ACTIVATING','التنفيذ ونجاح العملاء',48,'تشغيل فعلي ناجح بدون مشكلة حرجة','STABILIZING','مراجعة قائمة التجهيز؛ترحيل البيانات؛إعداد المستخدمين والصلاحيات؛التدريب؛اختبار دورة عمل كاملة؛معالجة البنود المتأخرة؛تثبيت موعد التشغيل الفعلي'],
            ['PB_STABILIZATION','فترة الاستقرار','lifecycle','STABILIZING','فريق نجاح العملاء',168,'استخدام منتظم وعدم وجود مشاكل مؤثرة','STABLE','مراجعة الاستخدام أسبوعيًا؛التأكد من عدم عودة العمليات إلى إكسل؛تحليل الأسئلة المتكررة؛تدريب إضافي؛قياس الرضا بعد أسبوعين وبعد شهر؛حصر الخصائص غير المستخدمة'],
            ['PB_STABLE_REVIEW','مراجعة عميل مستقر','review','STABLE','مدير الحساب',1440,'تحديد موعد المراجعة القادمة وإغلاق المهام','STABLE','مراجعة المشاكل؛مراجعة الاستخدام؛سؤال ما الذي يتم خارج النظام؛مراجعة تغييرات النشاط؛مراجعة الأشخاص الأساسيين؛مراجعة فرص التوسع'],
            ['PB_ACCOUNTANT_CHANGED','تغيير المحاسب','flag','ACCOUNTANT_CHANGED','فريق نجاح العملاء',48,'استخدام طبيعي لمدة أسبوعين وعدم وجود مشاكل جوهرية','CLOSE_FLAG','تسجيل المحاسب الجديد؛إعداد المستخدم والصلاحيات؛تقييم خبرته؛تدريب مختصر؛تسليم المواد الإرشادية؛متابعة بعد أسبوع؛مراجعة بعد 30 يوم'],
            ['PB_FIN_MGR_CHANGED','تغيير المدير المالي','flag','FINANCE_MANAGER_CHANGED','مدير الحساب',48,'اعتماد العلاقة وخطة العمل','CLOSE_FLAG','اجتماع تعارف؛عرض الوضع الحالي والتخصيصات؛فهم أولوياته؛مراجعة الأنظمة السابقة؛تسجيل اعتراضات؛مراجعة الاحتياجات؛متابعة خلال أسبوعين'],
            ['PB_MANAGEMENT_CHANGED','تغيير الإدارة','flag','MANAGEMENT_CHANGED','مدير الحساب',48,'وجود راعٍ واضح داخل العميل وخطة متابعة','CLOSE_FLAG','تحديد صاحب القرار الجديد؛مراجعة إدارية؛عرض قيمة النظام الحالية؛مراجعة المخاطر؛مراجعة أولويات الإدارة؛تثبيت راعٍ جديد داخل العميل'],
            ['PB_PROCESS_CHANGED','تغيير إجراءات العمل','flag','PROCESS_CHANGED','الاستشاري ونجاح العملاء',72,'اعتماد العملية الجديدة','CLOSE_FLAG','تحليل إجراءات العمل؛توثيق الوضع القديم والجديد؛تحليل الفجوات؛التهيئة والتخصيص؛خطة تنفيذ؛اختبار قبول المستخدم؛التدريب؛متابعة بعد أسبوع من التشغيل'],
            ['PB_EXPANSION','توسع الشركة','flag','COMPANY_EXPANSION','المبيعات ومدير الحساب',72,'تحويلها إلى فرصة بيع أو إغلاق السبب','EXPANSION_SALE','فهم الفرع أو المخزن أو الشركة أو النشاط الجديد؛تحليل أثر التوسع؛تحديد الموديولات والمستخدمين والتكاملات المطلوبة؛تقدير الاحتياج؛عرض توضيحي؛عرض سعر؛متابعة'],
            ['PB_LIMITED_USAGE','استخدام محدود','flag','LIMITED_USAGE','فريق نجاح العملاء',120,'ارتفاع معدل الاستخدام أو إثبات عدم الحاجة','CLOSE_FLAG','حصر الخصائص غير المستخدمة؛اختيار 2–3 خصائص مفيدة فقط؛عرض توضيحي قصير؛تدريب؛قياس الاستخدام بعد أسبوعين'],
            ['PB_LOW_USAGE','استخدام منخفض','flag','LOW_USAGE','فريق نجاح العملاء',48,'تحسن الاستخدام أو تصعيد','STABLE_OR_AT_RISK','تأكيد ما إذا كان النشاط قل أم أن النظام لا يستخدم؛كشف الرجوع إلى إكسل؛فحص تغير المسؤول؛فحص وجود منافس؛خطة معالجة؛متابعة أسبوعية لمدة شهر'],
            ['PB_DORMANT','عميل خامل','lifecycle','DORMANT','مدير الحساب',48,'عودة الاستخدام أو قرار نهائي','STABILIZING_OR_AT_RISK','التواصل مع صاحب القرار؛تحديد السبب؛خطة استعادة الاستخدام؛تدريب أو إعادة تجهيز؛متابعة أسبوع؛تحديد العودة أو خطر فقد العميل'],
            ['PB_REPEATED_COMPLAINTS','شكاوى متكررة','flag','REPEATED_COMPLAINTS','مسؤول نجاح العملاء',24,'حل الجذر + رضا مقبول','CLOSE_FLAG','تجميع الشكاوى؛تحليل السبب الجذري؛تعيين مسؤول واحد؛خطة تصحيح بمواعيد؛تحديثات دورية؛مكالمة بعد الإغلاق؛إعادة قياس الرضا'],
            ['PB_CRITICAL_ISSUE','مشكلة حرجة','flag','CRITICAL_ISSUE','مسؤول الدعم ومدير الحساب',1,'عودة التشغيل وإغلاق تحليل السبب الجذري','CLOSE_FLAG','تصعيد تقني؛إخطار العميل؛تحديثات دورية؛حل مؤقت؛تحليل السبب الجذري؛منع التكرار؛اتصال إداري عند الضرر الكبير'],
            ['PB_HIGH_SUPPORT','دعم زائد','flag','HIGH_SUPPORT_LOAD','فريق نجاح العملاء',72,'انخفاض الطلبات المتكررة','CLOSE_FLAG','تحليل آخر طلبات الدعم؛تصنيفها؛تحديد فجوة التدريب؛جلسة جماعية؛مواد مساعدة؛تعيين مستخدم رئيسي؛قياس طلبات الدعم بعد شهر'],
            ['PB_VERY_SATISFIED','عميل راضٍ جدًا','health','VERY_SATISFIED','مدير الحساب',120,'تم الطلب أو تأجيله بتاريخ واضح','SIGNALS','توثيق القيمة المحققة؛طلب شهادة من العميل؛طلب ترشيح مناسب لشركة أخرى؛مراجعة فرص التوسع؛عدم طلب ترشيح لمجرد إغلاق طلب دعم'],
            ['PB_REFERRAL','مرشح لترشيح عميل جديد','signal','REFERRAL_READY','المبيعات ومدير الحساب',120,'تم الترشيح أو لم يتم أو تم تأجيله','CLOSE_SIGNAL','تحديد العلاقات المناسبة؛طلب تقديم مباشر للشركة المرشحة وليس مجرد رقم؛تسجيل الترشيح؛متابعة النتيجة؛تطبيق مكافأة البرنامج إن وجدت'],
            ['PB_UPSELL','زيادة الاشتراك أو المستخدمين','signal','UPSELL','المبيعات',72,'تمت أو لم تتم','CLOSE_SIGNAL','تحديد سبب الفرصة؛تقدير القيمة؛تحديد صاحب القرار؛عرض توضيحي عند الحاجة؛عرض سعر؛متابعة؛تسجيل سبب النجاح أو عدم الإتمام'],
            ['PB_CROSS_SELL','بيع خدمة أو موديول إضافي','signal','CROSS_SELL','المبيعات',72,'تمت أو لم تتم','CLOSE_SIGNAL','تحديد مشكلة فعلية؛ربطها بالموديول المناسب؛عرض توضيحي على سيناريو العميل؛عرض سعر؛متابعة'],
            ['PB_AMBASSADOR','عميل سفير','signal','AMBASSADOR','مدير الحساب',720,'مراجعة دورية مستمرة','KEEP_ACTIVE','متابعة شخصية؛أولوية في إدارة العلاقة؛الحصول على ملاحظات مبكرة؛برنامج الترشيحات؛دراسة حالة؛فعاليات وندوات عبر الإنترنت'],
            ['PB_DISSATISFIED','غير راضٍ','health','DISSATISFIED','مسؤول نجاح العملاء',24,'عودة رضا العميل إلى مستوى محايد أو أفضل','CLOSE_RISK','الاستماع للعميل؛تسجيل سبب عدم الرضا: المنتج أو الدعم أو التنفيذ أو السعر أو العلاقة؛تحديد المسؤول؛خطة إصلاح بتاريخ؛متابعة؛قياس الرضا بعد الحل'],
            ['PB_AT_RISK','معرض للفقد','lifecycle','AT_RISK','مدير الحساب ومسؤول نجاح العملاء',24,'خروج الخطر أو طلب إلغاء','STABLE_OR_CHURN','مراجعة مستوى الخطر؛التواصل مع صاحب القرار؛تحديد السبب؛خطة احتفاظ بالعميل؛معالجة أهم مشكلة؛متابعة أسبوعية؛تصعيد للحسابات الكبيرة'],
            ['PB_CANCEL_REQ','طلب إلغاء','flag','CANCELLATION_REQUESTED','مدير الحساب',8,'تم الاحتفاظ أو إلغاء نهائي','STABLE_OR_CHURNED','تسجيل طلب الإلغاء؛التواصل مع العميل؛تحديد السبب الحقيقي؛تحديد ما إذا كان القرار نهائيًا؛اقتراح حل يعالج السبب فقط؛مراجعة الالتزامات؛تسجيل المنافس؛مقابلة ختامية'],
            ['PB_CHURNED','عميل مفقود','lifecycle','CHURNED','مدير الحساب',120,'توثيق كامل','CLOSED','تسجيل سبب فقد العميل؛تسجيل المنافس؛تسجيل الإيراد المفقود؛تقييم قابلية الإصلاح؛مراجعة داخلية؛تحديد موعد مناسب لإعادة التواصل'],
            ['PB_RENEWAL','تجديد قريب','flag','RENEWAL_DUE','مدير الحساب',1440,'تم التجديد أو أصبح العميل معرضًا للفقد','RENEWAL_RESULT','قبل 60 يومًا: مراجعة الحساب والرضا؛قبل 45 يومًا: حل المشاكل؛قبل 30 يومًا: إرسال عرض التجديد؛قبل 15 يومًا: متابعة؛قبل 7 أيام: تصعيد'],
            ['PB_PAYMENT_OVERDUE','متأخر ماليًا','flag','PAYMENT_OVERDUE','المالية ومدير الحساب',48,'السداد أو خطة دفع معتمدة','CLOSE_FLAG','التواصل المالي؛تحديد سبب التأخير؛تسجيل وعد بالسداد؛متابعة؛إخطار مدير الحساب؛فتح تنبيه خطر إذا كان السبب مرتبطًا بالرضا أو الخدمة؛تصعيد حسب السياسة'],
        ];

        foreach ($pbs as [$code,$name,$triggerType,$triggerCode,$owner,$sla,$exit,$result,$actions]) {
            $pb = CustomerPlaybook::updateOrCreate(['code'=>$code], [
                'name'=>$name,'trigger_type'=>$triggerType,'trigger_code'=>$triggerCode,'owner_role'=>$owner,'sla_hours'=>$sla,
                'exit_criteria'=>$exit,'result_code'=>$result,'is_active'=>true,
            ]);
            $steps = array_values(array_filter(array_map('trim', preg_split('/[؛;]/u', $actions))));
            $stepCount = max(1, count($steps));
            foreach ($steps as $i => $action) {
                $pb->steps()->updateOrCreate(['sort_order'=>$i+1],[
                    'title'=>$action,'due_offset_hours'=>$this->stepDueOffset($sla,$i,$stepCount),'default_priority'=>$this->priority($sla),
                ]);
            }
        }

        Customer::query()->chunkById(200, function ($customers): void {
            foreach ($customers as $customer) {
                if (CustomerSuccessProfile::where('customer_id', $customer->id)->exists()) continue;
                $latestStart = $customer->contracts()->where('status','active')->max('service_start_date');
                $statusCode = 'NEW';
                if ($latestStart) {
                    $date = \Illuminate\Support\Carbon::parse($latestStart);
                    $statusCode = $date->isFuture() ? 'ACTIVATING' : ($date->diffInDays(today()) <= 60 ? 'STABILIZING' : 'STABLE');
                }
                $status = CustomerSuccessStatus::where('code',$statusCode)->firstOrFail();
                CustomerSuccessProfile::create([
                    'customer_id'=>$customer->id,
                    'lifecycle_status_id'=>$status->id,
                    'health'=>'NEUTRAL',
                    'attention_level'=>$status->default_attention,
                    'owner_id'=>$customer->sales_owner_id ?: $customer->contracts()->whereNotNull('sales_owner_id')->latest('id')->value('sales_owner_id'),
                    'next_review_at'=>$statusCode==='STABLE'?now()->addDays(60):now()->addDays(14),
                ]);
            }
        });
    }

    private function stepDueOffset(?int $sla, int $index, int $stepCount): ?int
    {
        if (! $sla) return null;
        return max(1, (int) ceil((($index + 1) / max(1, $stepCount)) * $sla));
    }

    private function priority(?int $sla): string
    {
        return match (true) { $sla !== null && $sla <= 24 => 'critical', $sla !== null && $sla <= 72 => 'high', default => 'normal' };
    }
}
