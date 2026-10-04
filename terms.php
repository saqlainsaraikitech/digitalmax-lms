<?php
// Terms of Service (public)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/legal.php';

$sections = [
    [
        'en_t' => 'Account & Access',
        'en_b' => '<ul><li>Accounts are created <b>only by the admin</b> — there is no public signup. Your login credentials are personal and must not be shared with anyone.</li><li><b>Single-device policy:</b> only one device may stay logged in at a time. Logging in from a second device will log out the first.</li><li>You are responsible for keeping your password confidential.</li></ul>',
        'ur_t' => 'اکاؤنٹ اور رسائی',
        'ur_n' => '۱',
        'ur_b' => '<ul style="list-style:none; padding-right:0"><li>اکاؤنٹ <b>صرف ایڈمن</b> بناتا ہے — خود سے رجسٹریشن نہیں ہوتی۔ لاگ اِن کی تفصیلات کسی سے شیئر نہ کریں۔</li><li><b>سنگل ڈیوائس پالیسی:</b> ایک وقت میں صرف ایک ڈیوائس لاگ اِن رہ سکتی ہے۔</li><li>پاس ورڈ کی حفاظت آپ کی ذمہ داری ہے۔</li></ul>',
    ],
    [
        'en_t' => 'Intellectual Property (IP) Protection',
        'en_b' => '<p>All video lectures, master prompts, templates, winning product sheets and course materials are the exclusive property of <b>DigitalMax</b>.</p><ul><li>Recording, downloading-for-redistribution, re-selling, uploading to drives, or sharing with friends is <b>strictly prohibited</b>.</li><li>Such acts are an offense under copyright law and will result in a <b>permanent ban with no refund</b>, plus legal action.</li></ul>',
        'ur_t' => 'علمی ملکیت کا تحفظ',
        'ur_n' => '۲',
        'ur_b' => '<p>تمام ویڈیو لیکچرز، ماسٹر پرامپٹس اور شیٹس <b>ڈیجیٹل میکس کی ملکیت</b> ہیں۔</p><ul style="list-style:none; padding-right:0"><li>انہیں ریکارڈ کرنا، ڈرائیو میں ڈال کر بیچنا یا دوستوں سے شیئر کرنا <b>سخت منع</b> ہے۔</li><li>خلاف ورزی پر <b>بغیر ریفنڈ مستقل بلاک</b> اور قانونی کارروائی ہوگی۔</li></ul>',
    ],
    [
        'en_t' => 'Code of Conduct',
        'en_b' => '<p>Inside the LMS and WhatsApp communities you must:</p><ul><li>Never send personal messages (DMs) to fellow students without permission.</li><li>Never spam, abuse, or promote your own products/services.</li><li>Keep all discussion course-related and respectful.</li></ul><p>Full rules: <a href="index.php" style="color:#c74b25">Community Guidelines</a> shown before joining any group.</p>',
        'ur_t' => 'ضابطہ اخلاق',
        'ur_n' => '۳',
        'ur_b' => '<ul style="list-style:none; padding-right:0"><li>بغیر اجازت کسی طالب علم کو پرسنل میسج نہ کریں۔</li><li>اسپام، بدتمیزی یا اپنی پروڈکٹس کی پروموشن منع ہے۔</li><li>گفتگو کورس سے متعلق اور باادب رکھیں۔</li></ul>',
    ],
    [
        'en_t' => 'Account Termination',
        'en_b' => '<p>DigitalMax reserves the <b>sole right</b> to suspend or permanently terminate any account — with or without prior notice — for rule violations including inboxing, abuse, spam, self-promotion, or piracy. Terminated accounts are <b>not eligible for any refund</b>.</p>',
        'ur_t' => 'اکاؤنٹ کی بندش',
        'ur_n' => '۴',
        'ur_b' => '<p>قوانین کی خلاف ورزی (ان باکسنگ، بدتمیزی، اسپام، پروموشن یا چوری) پر ڈیجیٹل میکس کو <b>بغیر نوٹس اکاؤنٹ بند</b> کرنے کا مکمل حق حاصل ہے۔ بند اکاؤنٹس کو <b>کوئی ریفنڈ نہیں</b> ملے گا۔</p>',
    ],
    [
        'en_t' => 'Age & Legal Capacity',
        'en_b' => '<p>Students must be at least <b>18 years old</b>, or enrolling with a parent/guardian\'s permission. By paying the fee you confirm you meet this requirement.</p>',
        'ur_t' => 'عمر کی شرط',
        'ur_n' => '۵',
        'ur_b' => '<p>طالب علم کی عمر کم از کم <b>18 سال</b> ہونی چاہیے، یا والدین کی اجازت سے داخلہ لیا گیا ہو۔</p>',
    ],
    [
        'en_t' => 'Payments',
        'en_b' => '<p>Payments are collected manually via EasyPaisa/JazzCash/Bank and verified by our team against your Transaction ID and screenshot. All fees are <b>strictly non-refundable once access is granted</b> — see our <a href="refund-policy.php" style="color:#c74b25; font-weight:700">Refund Policy</a>.</p>',
        'ur_t' => 'ادائیگیاں',
        'ur_n' => '۶',
        'ur_b' => '<p>فیس ایزی پیسہ/جاز کیش/بینک کے ذریعے وصول کر کے ٹرانزیکشن آئی ڈی سے تصدیق کی جاتی ہے۔ رسائی ملنے کے بعد فیس <b>ناقابلِ واپسی</b> ہے — <a href="refund-policy.php" style="color:#c74b25; font-weight:700">ریفنڈ پالیسی</a> دیکھیں۔</p>',
    ],
];

render_legal_page('Terms of Service', 'شرائطِ استعمال', $sections);
