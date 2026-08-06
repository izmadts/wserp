@extends('layouts.admin')

@section('title', 'Golden Customer Guide')
@section('page-title', 'Golden Customer Guide (Urdu)')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">

<div class="flex flex-col lg:flex-row gap-6" style="font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif;">

    <!-- Table of Contents -->
    <div class="lg:w-64 flex-shrink-0" style="direction: ltr;">
        <div class="bg-white rounded-xl shadow-card p-4 lg:sticky lg:top-4">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Contents</p>
            <nav class="space-y-1 text-sm" style="direction: rtl;">
                <a href="#kya-hai" class="block px-3 py-1.5 rounded-lg text-gray-700 hover:bg-yellow-50 hover:text-yellow-700"><i class="fas fa-crown w-4 ml-1 text-gray-400"></i> گولڈن کلب کیا ہے</a>
                <a href="#membership" class="block px-3 py-1.5 rounded-lg text-gray-700 hover:bg-yellow-50 hover:text-yellow-700"><i class="fas fa-user-plus w-4 ml-1 text-gray-400"></i> ممبرشپ اور تصدیق</a>
                <a href="#zaroori-usool" class="block px-3 py-1.5 rounded-lg text-red-700 hover:bg-red-50"><i class="fas fa-exclamation-circle w-4 ml-1 text-red-400"></i> اہم اصول - پیمنٹ اور پوائنٹس</a>
                <a href="#points" class="block px-3 py-1.5 rounded-lg text-gray-700 hover:bg-yellow-50 hover:text-yellow-700"><i class="fas fa-coins w-4 ml-1 text-gray-400"></i> پوائنٹس کیسے ملتے ہیں</a>
                <a href="#tiers" class="block px-3 py-1.5 rounded-lg text-gray-700 hover:bg-yellow-50 hover:text-yellow-700"><i class="fas fa-medal w-4 ml-1 text-gray-400"></i> ممبرشپ لیول (سلور/گولڈ/پلاٹینم)</a>
                <a href="#redeem" class="block px-3 py-1.5 rounded-lg text-gray-700 hover:bg-yellow-50 hover:text-yellow-700"><i class="fas fa-gift w-4 ml-1 text-gray-400"></i> پوائنٹس ریڈیم کرنا</a>
                <a href="#lucky-draw" class="block px-3 py-1.5 rounded-lg text-gray-700 hover:bg-yellow-50 hover:text-yellow-700"><i class="fas fa-dice w-4 ml-1 text-gray-400"></i> لکی ڈرا</a>
                <a href="#referral" class="block px-3 py-1.5 rounded-lg text-gray-700 hover:bg-yellow-50 hover:text-yellow-700"><i class="fas fa-user-friends w-4 ml-1 text-gray-400"></i> ریفرل بونس</a>
                <a href="#notes" class="block px-3 py-1.5 rounded-lg text-gray-700 hover:bg-yellow-50 hover:text-yellow-700"><i class="fas fa-info-circle w-4 ml-1 text-gray-400"></i> ضروری نوٹس</a>
            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0 space-y-6" dir="rtl" lang="ur">

        <section id="kya-hai" class="bg-white rounded-xl shadow-card p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3"><i class="fas fa-crown text-yellow-500 ml-2"></i>گولڈن کلب کیا ہے؟</h2>
            <p class="text-gray-700 leading-8">
                گولڈن کلب ہمارے سسٹم کا کسٹمر لائلٹی پروگرام ہے۔ اس کا مقصد یہ ہے کہ جو کسٹمر ہم سے باقاعدگی سے خریداری
                کرتا ہے، اسے پوائنٹس، ممبرشپ لیول، لکی ڈرا انٹریز اور ریفرل بونس کی صورت میں فائدہ ملے۔ جیسے جیسے کسٹمر کی
                خریداری بڑھتی ہے، اس کا لیول (سلور → گولڈ → پلاٹینم) بھی خودکار طور پر بڑھتا جاتا ہے۔
            </p>
        </section>

        <section id="membership" class="bg-white rounded-xl shadow-card p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3"><i class="fas fa-user-plus text-blue-500 ml-2"></i>ممبرشپ اور تصدیق</h2>
            <p class="text-gray-700 leading-8 mb-3">
                جب بھی کوئی نیا کسٹمر رجسٹر ہوتا ہے (چاہے ایجنٹ کے ذریعے ہو یا موبائل ایپ سے خود) - وہ خودکار طور پر
                گولڈن کلب کا رکن بن جاتا ہے اور شروع میں "سلور" لیول پر ہوتا ہے۔ لیکن صرف رجسٹر ہونا کافی نہیں ہے۔
            </p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-blue-900 font-semibold mb-1"><i class="fas fa-shield-check ml-1"></i> تصدیق (Verification) ضروری ہے</p>
                <p class="text-blue-800 text-sm leading-7">
                    جب تک ایڈمن کسٹمر کے اکاؤنٹ کو "Verify" نہ کرے، اس کسٹمر کو گولڈن کلب کا کوئی فائدہ نہیں ملے گا - نہ
                    پوائنٹس، نہ لکی ڈرا انٹری، کچھ نہیں۔ صرف ایڈمن ہی کسی کسٹمر کو ویریفائی کر سکتا ہے
                    (Admin → Golden Club → Customers یا Pending Verification سے)۔ ایجنٹ خود کسی کسٹمر کو ویریفائی نہیں کر
                    سکتا۔
                </p>
            </div>
        </section>

        <section id="zaroori-usool" class="bg-white rounded-xl shadow-card p-6 border-2 border-red-200">
            <h2 class="text-xl font-bold text-red-700 mb-3"><i class="fas fa-exclamation-circle ml-2"></i>اہم اصول - پیمنٹ اور پوائنٹس کا تعلق</h2>
            <p class="text-gray-700 leading-8 mb-3">
                یہ سب سے اہم بات ہے جو ہر سیل ایجنٹ کو معلوم ہونی چاہیے:
            </p>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-3">
                <p class="text-red-900 font-bold leading-8">
                    کسی بھی سیل پر کسٹمر کو گولڈن کلب پوائنٹس اُسی وقت ملیں گے جب اُس سیل پر واقعی پیمنٹ ریکارڈ کی گئی ہو
                    اور سیل مکمل طور پر "Paid" ہو جائے - صرف اسٹیٹس "Paid" لکھ دینے سے پوائنٹس نہیں ملیں گے۔
                </p>
            </div>
            <p class="text-gray-700 leading-8 mb-2"><strong>صحیح طریقہ:</strong></p>
            <ul class="list-disc pr-6 space-y-2 text-gray-700 leading-7">
                <li>نئی سیل بناتے وقت اسٹیٹس "Draft" یا "Confirmed" میں سے کوئی ایک رکھیں۔</li>
                <li>اگر کسٹمر نے اُسی وقت پیمنٹ دی ہے تو نیچے "Amount Received Now" والے خانے میں وصول شدہ رقم لکھیں
                    (پوری رقم کے لیے "Pay in Full" بٹن دبائیں) - سسٹم خود بخود پیمنٹ ریکارڈ کرے گا، اسٹیٹس "Paid" ہو
                    جائے گا، اور کسٹمر کو پوائنٹس مل جائیں گے۔</li>
                <li>اگر پیمنٹ بعد میں ملے تو سیل کو "Confirmed" رکھیں اور جب پیمنٹ ملے تو "Add Payment" کا بٹن استعمال
                    کریں۔</li>
            </ul>
            <p class="text-gray-500 text-sm leading-7 mt-3">
                نوٹ: پہلے اس سسٹم میں ایک خرابی تھی جس کی وجہ سے نئی سیل بناتے وقت اگر "Paid" اسٹیٹس منتخب کر لیا جاتا تو
                کسٹمر کو پوائنٹس نہیں ملتے تھے، چاہے سیل واقعی مکمل ادا شدہ ہی کیوں نہ ہو۔ اب یہ ٹھیک کر دیا گیا ہے، اور
                "Paid" اسٹیٹس فارم سے منتخب کرنا ممکن ہی نہیں - یہ صرف اوپر بتائے گئے طریقے سے خودکار طے ہوتا ہے۔
            </p>
        </section>

        <section id="points" class="bg-white rounded-xl shadow-card p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3"><i class="fas fa-coins text-amber-500 ml-2"></i>پوائنٹس کیسے ملتے ہیں</h2>
            <p class="text-gray-700 leading-8 mb-3">
                جب کسی ویریفائیڈ کسٹمر کی سیل مکمل طور پر ادا (Paid) ہو جاتی ہے تو اسے خریداری کی رقم کے حساب سے پوائنٹس
                ملتے ہیں:
            </p>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center">
                <p class="text-amber-900 font-bold text-lg">ہر روپے 100 خریداری پر = 1 پوائنٹ</p>
                <p class="text-amber-700 text-xs mt-1">(یہ ریٹ ایڈمن Settings → Golden Club سے تبدیل کر سکتا ہے)</p>
            </div>
            <p class="text-gray-600 text-sm leading-7 mt-3">
                یاد رہے: اگر سیل واپس (Return) ہو جائے تو پہلے دیے گئے پوائنٹس یا ممبرشپ لیول واپس نہیں لیے جاتے - یہ
                کسٹمر کے پاس ہی رہتے ہیں۔
            </p>
        </section>

        <section id="tiers" class="bg-white rounded-xl shadow-card p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3"><i class="fas fa-medal text-purple-500 ml-2"></i>ممبرشپ لیول (سلور / گولڈ / پلاٹینم)</h2>
            <p class="text-gray-700 leading-8 mb-4">
                کسٹمر کی کل تاحیات خریداری (Lifetime Purchase) کی بنیاد پر اس کا لیول خودکار طے ہوتا ہے:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="border border-gray-200 rounded-lg p-4 text-center">
                    <i class="fas fa-medal text-gray-400 text-2xl mb-2"></i>
                    <p class="font-bold text-gray-700">سلور</p>
                    <p class="text-sm text-gray-500">شروع سے (ڈیفالٹ)</p>
                </div>
                <div class="border border-yellow-300 bg-yellow-50 rounded-lg p-4 text-center">
                    <i class="fas fa-medal text-yellow-500 text-2xl mb-2"></i>
                    <p class="font-bold text-yellow-700">گولڈ</p>
                    <p class="text-sm text-yellow-700">روپے 5,00,000+ خریداری پر</p>
                </div>
                <div class="border border-blue-300 bg-blue-50 rounded-lg p-4 text-center">
                    <i class="fas fa-medal text-blue-500 text-2xl mb-2"></i>
                    <p class="font-bold text-blue-700">پلاٹینم</p>
                    <p class="text-sm text-blue-700">روپے 10,00,000+ خریداری پر</p>
                </div>
            </div>
            <p class="text-gray-500 text-sm leading-7 mt-4">
                یہ حدیں (تھریش ہولڈز) ایڈمن Settings → Golden Club سے تبدیل کر سکتا ہے۔ لیول کا کسٹمر کی قیمتوں یا رعایت
                پر کوئی خودکار اثر نہیں ہوتا - یہ صرف ایک درجہ/بیج ہے۔ اصل فائدہ پوائنٹس اور لکی ڈرا کی صورت میں ملتا ہے۔
            </p>
        </section>

        <section id="redeem" class="bg-white rounded-xl shadow-card p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3"><i class="fas fa-gift text-pink-500 ml-2"></i>پوائنٹس ریڈیم کرنا</h2>
            <p class="text-gray-700 leading-8 mb-3">
                کسٹمر اپنے جمع شدہ پوائنٹس، ایڈمن کی طرف سے بنائے گئے "ریوارڈز" کیٹلاگ میں سے کسی چیز کے بدلے استعمال کر
                سکتا ہے۔ ایجنٹ بھی اپنے کسٹمر کی طرف سے ریڈیم کر سکتا ہے (Agent → Golden Club → Rewards)۔
            </p>
            <ol class="list-decimal pr-6 space-y-2 text-gray-700 leading-7">
                <li>کسٹمر یا ایجنٹ کوئی ریوارڈ منتخب کرتا ہے - پوائنٹس فوراً کاٹ لیے جاتے ہیں اور درخواست "Pending" حالت
                    میں چلی جاتی ہے۔</li>
                <li>ایڈمن درخواست کو "Approve" کرتا ہے۔</li>
                <li>جب چیز کسٹمر تک پہنچ جائے تو ایڈمن اسے "Delivered" مارک کرتا ہے۔</li>
                <li>اگر درخواست منسوخ کرنی ہو تو "Cancel" کرنے سے پوائنٹس کسٹمر کو واپس مل جاتے ہیں۔</li>
            </ol>
        </section>

        <section id="lucky-draw" class="bg-white rounded-xl shadow-card p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3"><i class="fas fa-dice text-green-500 ml-2"></i>لکی ڈرا</h2>
            <p class="text-gray-700 leading-8 mb-3">
                جب ایڈمن کوئی لکی ڈرا مہم (Campaign) چلا رہا ہو، تو اُس دوران ہونے والی ہر ادا شدہ (Paid) سیل پر کسٹمر کو
                خودکار انٹریاں ملتی ہیں - مثال کے طور پر اگر مہم "روپے 20,000 = 1 انٹری" پر ترتیب دی گئی ہو تو روپے 45,000
                کی سیل پر 2 انٹریاں ملیں گی۔
            </p>
            <p class="text-gray-600 text-sm leading-7">
                اگر مہم میں کم از کم خریداری (Minimum Purchase) کی شرط بھی رکھی گئی ہو تو اس شرط سے کم رقم کی سیل پر کوئی
                انٹری نہیں ملے گی۔ ایڈمن ونر کا انتخاب Admin → Golden Club → Lucky Draw سے کرتا ہے۔
            </p>
        </section>

        <section id="referral" class="bg-white rounded-xl shadow-card p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3"><i class="fas fa-user-friends text-teal-500 ml-2"></i>ریفرل بونس</h2>
            <p class="text-gray-700 leading-8">
                اگر کوئی موجودہ کسٹمر کسی نئے کسٹمر کو ہمارے پاس لے کر آئے (ریفر کرے) اور وہ نیا کسٹمر اپنی پہلی سیل مکمل
                ادا کر دے، تو ریفر کرنے والے کسٹمر کو بونس پوائنٹس (ڈیفالٹ: 50 پوائنٹس، ایڈمن Settings سے تبدیل کر سکتا
                ہے) خودکار مل جاتے ہیں۔
            </p>
        </section>

        <section id="notes" class="bg-white rounded-xl shadow-card p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-3"><i class="fas fa-info-circle text-gray-500 ml-2"></i>ضروری نوٹس</h2>
            <ul class="list-disc pr-6 space-y-2 text-gray-700 leading-7">
                <li>ویریفیکیشن کے بغیر کسی کسٹمر کو کوئی فائدہ نہیں ملتا - نہ پوائنٹس، نہ لکی ڈرا، نہ ریفرل بونس۔</li>
                <li>پوائنٹس اور لیول صرف اُس وقت اپڈیٹ ہوتے ہیں جب سیل واقعی "Paid" ہو (اوپر "اہم اصول" ملاحظہ کریں)۔</li>
                <li>سیل ریٹرن ہونے پر پہلے دیے گئے پوائنٹس یا لیول واپس نہیں لیے جاتے۔</li>
                <li>ممبرشپ لیول کا قیمتوں پر کوئی خودکار اثر نہیں - یہ صرف پہچان (بیج) کے لیے ہے۔</li>
                <li>موبائل ایپ میں "Customer Rank" کا خانہ فی الحال فعال نہیں ہے - یہ آنے والے اپڈیٹ کے لیے محفوظ رکھا
                    گیا ہے۔</li>
            </ul>
        </section>

    </div>
</div>
@endsection
