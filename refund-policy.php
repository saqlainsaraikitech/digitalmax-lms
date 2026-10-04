<?php
// Refund & Cancellation Policy (public)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/legal.php';

$sections = [
    [
        'en_t' => 'Nature of Digital Products',
        'en_b' => '<p>All courses, master prompts, templates, winning product sheets, and video lectures provided on DigitalMax LMS are non-tangible, irrevocable digital goods. Once access to the student dashboard, lecture curriculum, or private batch communities is granted, <b>NO REFUNDS</b> will be issued under general circumstances.</p>',
        'ur_t' => 'ڈیجیٹل پراڈکٹس کی نوعیت',
        'ur_n' => '۱',
        'ur_b' => '<p>ڈیجیٹل میکس (DigitalMax LMS) پر فراہم کردہ تمام کورسز، ویڈیو لیکچرز، وننگ پروڈکٹ شیٹس اور ماسٹر پرامپٹس ناقابلِ واپسی (Non-refundable) ڈیجیٹل مواد پر مشتمل ہیں۔ ایک بار جب آپ کو اسٹوڈنٹ ڈیش بورڈ، لیکچرز یا آفیشل واٹس ایپ کمیونٹی کی رسائی مل جاتی ہے تو عام حالات میں فیس کسی صورت واپس نہیں کی جائے گی۔</p>',
    ],
    [
        'en_t' => 'Strict "No Refund" Conditions',
        'en_b' => '<p>Refund requests will be strictly rejected under the following scenarios:</p><ul><li><b>Change of Mind:</b> You simply decided not to pursue the course, lost interest, or do not have enough time.</li><li><b>Content Consumed:</b> You have accessed, viewed, or downloaded course materials, lectures, or resources.</li><li><b>Rule Violations & Bans:</b> If your account is suspended or permanently banned from the LMS or WhatsApp community due to a violation of our Code of Conduct (e.g., inboxing students, spamming, self-promotion, or leaking content).</li><li><b>Technical Limitations on Student\'s End:</b> Inability to watch lectures due to your own slow internet connection, incompatible personal devices, or local restrictions.</li></ul><div class="warn">Zero-tolerance policy: banned accounts are never eligible for a refund.</div>',
        'ur_t' => 'جن صورتوں میں ریفنڈ بالکل نہیں ملے گا',
        'ur_n' => '۲',
        'ur_b' => '<p>مندرجہ ذیل صورتوں میں ریفنڈ کی درخواست مکمل طور پر مسترد کر دی جائے گی:</p><ul style="list-style:none; padding-right:0"><li><b>ارادہ بدل جانا (Change of Mind):</b> اگر آپ کورس شروع کرنے کے بعد اپنا ارادہ بدل لیتے ہیں، وقت کی کمی کا بہانہ بناتے ہیں یا کورس جاری نہیں رکھنا چاہتے۔</li><li><b>کورس مواد دیکھ لینا:</b> اگر آپ نے ایک یا ایک سے زائد لیکچرز دیکھ لیے ہیں یا کورس کے ریسورسز/فائلز ڈاؤن لوڈ کر لی ہیں۔</li><li><b>اصولوں کی خلاف ورزی اور بلاک ہونا:</b> اگر آپ کا اکاؤنٹ واٹس ایپ کمیونٹی کے قوانین کی خلاف ورزی (مثلاً کسی کو پرسنل میسج کرنا، اپنی چیزیں بیچنا، یا لیکچرز کا مواد چوری کرنا) کی بنا پر مستقل بلاک کر دیا جائے تو زیرو ٹالرنس پالیسی کے تحت کوئی فیس واپس نہیں ہوگی۔</li><li><b>طالب علم کے اپنے تکنیکی مسائل:</b> آپ کے انٹرنیٹ کی خرابی، موبائل/کمپیوٹر کی خرابی یا ذاتی وجوہات پر فیس واپس نہیں ہوگی۔</li></ul>',
    ],
    [
        'en_t' => 'Exceptional Circumstances (Eligible for Refund / Adjustment)',
        'en_b' => '<p>A refund or fee adjustment is only considered in rare cases:</p><ul><li><b>Accidental Duplicate Payment:</b> If you were charged twice for the same course or sent two transactions via EasyPaisa/JazzCash by mistake (verified via Transaction ID).</li><li><b>Batch Transfer:</b> If requested at least 48 hours <b>BEFORE</b> the batch starts, you may request to transfer your fee to the upcoming batch instead of a refund.</li></ul>',
        'ur_t' => 'استثنائی صورتیں (جہاں ریفنڈ یا ایڈجسٹمنٹ ممکن ہے)',
        'ur_n' => '۳',
        'ur_b' => '<p>صرف مندرجہ ذیل مخصوص حالات میں درخواست پر غور کیا جائے گا:</p><ul style="list-style:none; padding-right:0"><li><b>غلطی سے دو بار ادائیگی (Duplicate Payment):</b> اگر غلطی سے ایزی پیسہ یا جاز کیش کے ذریعے ایک ہی کورس کی فیس دو بار ٹرانسفر ہو گئی ہو (ٹرانزیکشن آئی ڈی کی تصدیق کے بعد اضافی رقم واپس کر دی جائے گی)۔</li><li><b>بیچ منتقلی (Batch Transfer):</b> اگر کلاسز یا بیچ شروع ہونے سے کم از کم 48 گھنٹے پہلے اطلاع دی جائے، تو فیس واپس کرنے کے بجائے آپ کا داخلہ اگلے آنے والے بیچ میں منتقل کیا جا سکتا ہے۔</li></ul>',
    ],
    [
        'en_t' => 'How to Request Assistance',
        'en_b' => '<p>For duplicate payment resolution, contact our official support within 24 hours of transaction with valid payment receipts (TID & Screenshot):</p><div class="contact"><b>Email:</b> <a href="mailto:support@digitalmx.pk">support@digitalmx.pk</a><br><b>Official WhatsApp:</b> +92 3XX XXXXXXX</div>',
        'ur_t' => 'رابطہ برائے مدد',
        'ur_n' => '۴',
        'ur_b' => '<p>ڈپلیکیٹ ادائیگی یا ٹیکنیکل مسائل کے حل کے لیے 24 گھنٹوں کے اندر اصل رسید (TID اور اسکرین شاٹ) کے ساتھ آفیشل سپورٹ پر رابطہ کریں:</p><div class="contact urdu" dir="rtl" style="text-align:right"><b>ای میل:</b> <a href="mailto:support@digitalmx.pk" dir="ltr">support@digitalmx.pk</a><br><b>آفیشل واٹس ایپ:</b> <span dir="ltr">+92 3XX XXXXXXX</span></div>',
    ],
];

render_legal_page('Refund & Cancellation Policy', 'ریفنڈ اور کینسیلیشن پالیسی', $sections);
