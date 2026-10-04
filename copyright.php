<?php
// Copyright & Anti-Piracy Policy (public)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/legal.php';

$sections = [
    [
        'en_t' => 'Ownership',
        'en_b' => '<p>All content on DigitalMax LMS — video lectures, master prompts, templates, sheets, graphics and text — is the exclusive copyrighted property of <b>DigitalMax</b>. All rights reserved.</p>',
        'ur_t' => 'ملکیت',
        'ur_n' => '۱',
        'ur_b' => '<p>ڈیجیٹل میکس کا تمام مواد — ویڈیو لیکچرز، ماسٹر پرامپٹس، شیٹس — <b>ڈیجیٹل میکس کی کاپی رائٹ ملکیت</b> ہے۔</p>',
    ],
    [
        'en_t' => 'Dynamic Watermarking Acknowledgment',
        'en_b' => '<p>By watching lectures, you acknowledge that <b>your phone number is printed as a watermark on the videos you watch</b>.</p><div class="warn">If a leaked video is found anywhere, it will be traced back through this watermark — followed by a <b>permanent ban, no refund, and a legal complaint with the FIA Cybercrime Wing</b>.</div>',
        'ur_t' => 'واٹر مارک کی تصدیق',
        'ur_n' => '۲',
        'ur_b' => '<p>لیکچرز دیکھ کر آپ تسلیم کرتے ہیں کہ <b>آپ کا فون نمبر ویڈیو پر واٹر مارک</b> کے طور پر چھپا ہوتا ہے۔</p><div class="warn urdu" dir="rtl" style="text-align:right">اگر کوئی ویڈیو لیک ہوئی تو اسی نمبر سے آپ کی شناخت ہوگی — <b>مستقل بلاک، بغیر ریفنڈ، اور FIA سائبر کرائم ونگ میں شکایت</b> درج ہوگی۔</div>',
    ],
    [
        'en_t' => 'Prohibited Actions',
        'en_b' => '<p>The following are strictly prohibited and treated as piracy:</p><ul><li>Screen-recording, downloading-for-sharing, or re-uploading lectures anywhere.</li><li>Re-selling or "sharing" course access, logins, or materials with others.</li><li>Removing or hiding watermarks.</li></ul>',
        'ur_t' => 'ممنوعہ اعمال',
        'ur_n' => '۳',
        'ur_b' => '<ul style="list-style:none; padding-right:0"><li>لیکچرز کی اسکرین ریکارڈنگ یا دوبارہ اپ لوڈ کرنا۔</li><li>کورس کی رسائی یا مواد بیچنا/بانٹنا۔</li><li>واٹر مارک ہٹانا یا چھپانا۔</li></ul><p>یہ سب <b>چوری (Piracy)</b> کے زمرے میں آتے ہیں۔</p>',
    ],
    [
        'en_t' => 'Takedown & Legal Action',
        'en_b' => '<p>Unauthorized copying, re-selling or distribution will be pursued under the <b>Pakistan Copyright Ordinance, 1962</b>, including takedown notices to platforms and criminal complaints where applicable. DigitalMax actively monitors leak channels.</p>',
        'ur_t' => 'قانونی کارروائی',
        'ur_n' => '۴',
        'ur_b' => '<p>بغیر اجازت کاپی یا فروخت پر <b>پاکستان کاپی رائٹ آرڈیننس 1962</b> کے تحت کارروائی ہوگی، بشمول پلیٹ فارمز کو ٹیک ڈاؤن نوٹس اور فوجداری شکایت۔</p>',
    ],
];

render_legal_page('Copyright & Anti-Piracy Policy', 'کاپی رائٹ اور اینٹی پائریسی پالیسی', $sections);
