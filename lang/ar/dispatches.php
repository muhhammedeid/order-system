<?php

return [
    'title' => 'متابعة إرسال واتساب', 'kind' => 'الغرض', 'order' => 'الطلب', 'customer' => 'العميل',
    'template' => 'القالب', 'status' => 'الحالة', 'attempts' => 'محاولات الإرسال', 'resolution_attempts' => 'فشل التحقق من الرقم',
    'reason' => 'سبب الفشل', 'sent_at' => 'وقت الإرسال', 'created_at' => 'وقت الإنشاء', 'retry' => 'إعادة المحاولة قبل الإرسال',
    'kinds' => ['order_customer' => 'تحديث طلب العميل', 'order_owner' => 'إشعار المالك', 'campaign' => 'حملة منتج'],
    'statuses' => ['pending' => 'بانتظار الإرسال', 'processing' => 'جارٍ الإرسال', 'sent' => 'أُرسلت', 'failed' => 'فشل قبل الإرسال',
        'unknown' => 'نتيجة غير معروفة — لا تعِد الإرسال', 'skipped' => 'تم التخطي'],
];
