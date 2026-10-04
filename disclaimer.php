<?php
// Earnings & Results Disclaimer (public)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/legal.php';

$sections = [
    [
        'en_t' => 'No Guarantee of Income',
        'en_b' => '<p>Any screenshots, figures or case studies shown in our courses, ads or website (for example "$10k in sales", TikTok views, or revenue dashboards) are <b>educational examples only</b>. They are not a promise, guarantee or projection of what you will earn.</p><p>Every student\'s results depend on their own effort, consistency, advertising budget, product selection, and market conditions — which are different for everyone.</p>',
        'ur_t' => 'آمدنی کی کوئی گارنٹی نہیں',
        'ur_n' => '۱',
        'ur_b' => '<p>کورسز، اشتہارات یا ویب سائٹ پر دکھائے گئے تمام اسکرین شاٹس اور اعداد (مثلاً "$10k سیلز" یا ٹک ٹاک ویوز) <b>صرف تعلیمی مثالیں</b> ہیں۔ یہ اس بات کی ضمانت نہیں کہ آپ بھی اتنا ہی کمائیں گے۔</p><p>ہر طالب علم کے نتائج اس کی اپنی محنت، مستقل مزاجی، اشتہاری بجٹ اور مارکیٹ کے حالات پر منحصر ہیں۔</p>',
    ],
    [
        'en_t' => 'Business Risks You Accept',
        'en_b' => '<p>By enrolling, you acknowledge that:</p><ul><li><b>eCommerce risks:</b> paid advertising can result in losses, products can fail, and ad accounts can be restricted.</li><li><b>TikTok risks:</b> accounts can be banned, shadow-banned or disqualified from monetization programs due to TikTok\'s own policy changes — which are outside our control.</li><li>DigitalMax LMS is <b>not liable</b> for any financial loss, lost profit, or account penalty you may experience while applying this training.</li></ul>',
        'ur_t' => 'کاروباری خطرات جنہیں آپ تسلیم کرتے ہیں',
        'ur_n' => '۲',
        'ur_b' => '<p>داخلہ لے کر آپ تسلیم کرتے ہیں کہ:</p><ul style="list-style:none; padding-right:0"><li><b>ای کامرس کے خطرات:</b> اشتہارات پر پیسہ ڈوب سکتا ہے، پروڈکٹ ناکام ہو سکتی ہے۔</li><li><b>ٹک ٹاک کے خطرات:</b> ٹک ٹاک کی اپنی پالیسیوں کی وجہ سے اکاؤنٹ بین یا مونیٹائزیشن سے نااہل ہو سکتا ہے — یہ ہمارے اختیار سے باہر ہے۔</li><li>اس ٹریننگ پر عمل کرتے ہوئے کسی بھی مالی نقصان کا <b>ڈیجیٹل میکس ذمہ دار نہیں</b> ہوگا۔</li></ul>',
    ],
    [
        'en_t' => 'Training, Not a Get-Rich-Quick Scheme',
        'en_b' => '<p>What we sell is <b>education and practical training</b> — not a job, not an investment scheme, and not a "become rich overnight" promise. Your outcome depends on how seriously and consistently you apply what you learn.</p><div class="warn">If anyone guarantees you fixed monthly income from this course, that claim is false — please report it to our support team.</div>',
        'ur_t' => 'یہ ٹریننگ ہے، راتوں رات امیر بننے کا فارمولا نہیں',
        'ur_n' => '۳',
        'ur_b' => '<p>ہم جو بیچتے ہیں وہ <b>تعلیم اور عملی ٹریننگ</b> ہے — نہ نوکری، نہ انویسٹمنٹ اسکیم، اور نہ "راتوں رات امیر" بننے کا وعدہ۔ آپ کا نتیجہ اس پر منحصر ہے کہ آپ کتنی سنجیدگی اور مستقل مزاجی سے سیکھے ہوئے کو اپناتے ہیں۔</p>',
    ],
    [
        'en_t' => 'Testimonials Are Not Typical',
        'en_b' => '<p>Student results, testimonials or earning proofs shared anywhere (website, WhatsApp, social media) represent individual experiences. They are <b>not typical</b> and should not be interpreted as average expected earnings.</p>',
        'ur_t' => 'نتائج کی مثالیں معمول نہیں',
        'ur_n' => '۴',
        'ur_b' => '<p>ویب سائٹ یا سوشل میڈیا پر شیئر کیے گئے طلبہ کے نتائج انفرادی تجربات ہیں۔ یہ <b>معمول کے نتائج نہیں</b> اور انہیں اوسط آمدنی نہ سمجھا جائے۔</p>',
    ],
];

render_legal_page('Earnings & Results Disclaimer', 'آمدنی اور نتائج سے متعلق اعلامیہ', $sections);
