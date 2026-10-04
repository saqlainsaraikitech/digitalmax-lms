<?php
// Privacy Policy (public)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/legal.php';

$sections = [
    [
        'en_t' => 'Data We Collect',
        'en_b' => '<p>We collect only what is necessary to run your learning account:</p><ul><li><b>Identity:</b> name, email address, phone/WhatsApp number.</li><li><b>Payments:</b> Transaction ID (TID) and payment screenshots for verification.</li><li><b>Learning activity:</b> lecture progress, assignment submissions, certificates.</li></ul>',
        'ur_t' => 'ہم کون سا ڈیٹا لیتے ہیں',
        'ur_n' => '۱',
        'ur_b' => '<p>ہم صرف وہی معلومات لیتے ہیں جو آپ کے اکاؤنٹ کے لیے ضروری ہیں:</p><ul style="list-style:none; padding-right:0"><li><b>شناخت:</b> نام، ای میل، فون/واٹس ایپ نمبر۔</li><li><b>ادائیگی:</b> ٹرانزیکشن آئی ڈی اور اسکرین شاٹ (تصدیق کے لیے)۔</li><li><b>تعلیمی سرگرمی:</b> لیکچر پروگریس اور اسائنمنٹس۔</li></ul>',
    ],
    [
        'en_t' => 'How We Use It',
        'en_b' => '<ul><li>Creating and securing your student account (including single-device login).</li><li>Verifying your manual payments and enrolling you in courses/batches.</li><li>Sending batch announcements, support messages and certificates.</li></ul><p>We do <b>not</b> use your data for any unrelated purpose.</p>',
        'ur_t' => 'ڈیٹا کا استعمال',
        'ur_n' => '۲',
        'ur_b' => '<ul style="list-style:none; padding-right:0"><li>آپ کا اکاؤنٹ بنانا اور محفوظ رکھنا۔</li><li>ادائیگی کی تصدیق اور کورس میں داخلہ۔</li><li>بیچ کے اعلانات، سپورٹ اور سرٹیفکیٹ بھیجنا۔</li></ul>',
    ],
    [
        'en_t' => 'WhatsApp Communication Consent',
        'en_b' => '<p>By enrolling, you agree that DigitalMax may contact you on your provided WhatsApp number for <b>course updates, batch announcements and support</b>. You can ask to opt out of non-essential broadcasts at any time via support.</p>',
        'ur_t' => 'واٹس ایپ رابطے کی اجازت',
        'ur_n' => '۳',
        'ur_b' => '<p>داخلہ لے کر آپ اس بات سے متفق ہیں کہ ڈیجیٹل میکس آپ کے واٹس ایپ نمبر پر <b>کورس اپڈیٹس اور اعلانات</b> بھیج سکتا ہے۔</p>',
    ],
    [
        'en_t' => 'No Data Selling',
        'en_b' => '<p>Your personal data is <b>never sold, rented or shared</b> with third parties for marketing. It is visible only to the DigitalMax admin/team members who need it to operate the platform.</p>',
        'ur_t' => 'ڈیٹا فروخت نہیں کیا جاتا',
        'ur_n' => '۴',
        'ur_b' => '<p>آپ کا ذاتی ڈیٹا <b>کبھی کسی تیسری پارٹی کو فروخت</b> نہیں کیا جاتا۔ یہ صرف متعلقہ ٹیم ممبران کو نظر آتا ہے۔</p>',
    ],
    [
        'en_t' => 'Security of Receipts',
        'en_b' => '<p>Payment screenshots are stored on our <b>secure server</b> and used <b>only for payment verification</b>. They are never published or shared publicly.</p>',
        'ur_t' => 'رسیدوں کی حفاظت',
        'ur_n' => '۵',
        'ur_b' => '<p>ادائیگی کے اسکرین شاٹس <b>محفوظ سرور</b> پر رکھے جاتے ہیں اور <b>صرف تصدیق</b> کے لیے استعمال ہوتے ہیں۔</p>',
    ],
    [
        'en_t' => 'Your Rights',
        'en_b' => '<p>You may request correction or deletion of your personal data at any time by emailing <a href="mailto:support@digitalmx.pk" style="color:#c74b25; font-weight:700">support@digitalmx.pk</a>. Account deletion may require re-verification of identity.</p>',
        'ur_t' => 'آپ کے حقوق',
        'ur_n' => '۶',
        'ur_b' => '<p>آپ کسی بھی وقت اپنے ڈیٹا کی درستگی یا حذف کی درخواست <a href="mailto:support@digitalmx.pk" style="color:#c74b25; font-weight:700">support@digitalmx.pk</a> پر کر سکتے ہیں۔</p>',
    ],
];

render_legal_page('Privacy Policy', 'پرائیویسی پالیسی', $sections);
