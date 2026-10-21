<?php

namespace App\Support\Categories;

/**
 * The shared starting tree of categories: 16 broad areas of life, each with
 * common subcategories, in every supported language. People add their own
 * categories under these; suggestions become shared after review.
 *
 * Each entry: [slug, en, fa, ar, zh_CN, kinds?]. Kinds default to skill and
 * service for subcategories and to every kind for the 16 areas.
 */
final class LifeDomainCatalog
{
    public const VOCABULARY_SLUG = 'iet-categories';

    public const SCHEME_SLUG = 'life-domains';

    /** Old skill list codes → category slugs (the old records are kept and copied). */
    public const LEGACY_PROFESSIONS = [
        'construction' => 'housing.construction',
        'mason' => 'housing.masonry',
        'construction_worker' => 'housing.construction-labour',
        'electrician' => 'housing.electrical',
        'plumber' => 'housing.plumbing',
        'carpenter' => 'housing.carpentry',
        'painter' => 'housing.painting',
        'hvac_technician' => 'housing.hvac',
        'welder' => 'industry.welding',
        'tiler' => 'housing.tiling',
        'real_estate' => 'housing.real-estate',
        'real_estate_agent' => 'housing.real-estate-brokerage',
        'property_appraiser' => 'housing.property-appraisal',
        'retail' => 'business.retail-sales',
        'salesperson' => 'business.retail-sales',
        'store_manager' => 'business.store-management',
        'professional_services' => 'business',
        'accountant' => 'finance.accounting',
        'driver' => 'transport.driving',
        'cleaner' => 'housing.cleaning',
        'technician' => 'industry.maintenance-repair',
    ];

    /** @return list<array{slug: string, labels: array<string, string>, kinds: list<string>, children: list<array{slug: string, labels: array<string, string>, kinds: list<string>}>}> */
    public static function tree(): array
    {
        $tree = [];
        foreach (self::DOMAINS as $domain) {
            [$slug, $en, $fa, $ar, $zh] = $domain['area'];
            $tree[] = [
                'slug' => $slug,
                'labels' => ['en' => $en, 'fa' => $fa, 'ar' => $ar, 'zh_CN' => $zh],
                'kinds' => CategoryKind::values(),
                'children' => array_map(static fn (array $child): array => [
                    'slug' => $slug.'.'.$child[0],
                    'labels' => ['en' => $child[1], 'fa' => $child[2], 'ar' => $child[3], 'zh_CN' => $child[4]],
                    'kinds' => $child[5] ?? ['skill', 'service'],
                ], $domain['children']),
            ];
        }

        return $tree;
    }

    /** @return list<string> every slug in the tree */
    public static function slugs(): array
    {
        $slugs = [];
        foreach (self::tree() as $domain) {
            $slugs[] = $domain['slug'];
            foreach ($domain['children'] as $child) {
                $slugs[] = $child['slug'];
            }
        }

        return $slugs;
    }

    private const DOMAINS = [
        ['area' => ['housing', 'Housing & living', 'مسکن و زندگی', 'السكن والمعيشة', '住房与生活'], 'children' => [
            ['construction', 'Construction & renovation', 'ساخت و بازسازی', 'البناء والترميم', '建筑与装修'],
            ['construction-labour', 'Construction labour', 'کارگری ساختمانی', 'عمالة البناء', '建筑劳务'],
            ['masonry', 'Masonry', 'بنایی', 'أعمال البناء بالحجر والطوب', '砌筑'],
            ['electrical', 'Electrical installation & repair', 'برق‌کاری ساختمان', 'التمديدات والإصلاحات الكهربائية', '电气安装与维修'],
            ['plumbing', 'Plumbing', 'لوله‌کشی', 'السباكة', '水管安装与维修'],
            ['carpentry', 'Carpentry & woodwork', 'نجاری', 'النجارة', '木工'],
            ['painting', 'Building painting', 'نقاشی ساختمان', 'دهان المباني', '房屋油漆'],
            ['tiling', 'Tiling & flooring', 'کاشی‌کاری و کف‌پوش', 'التبليط والأرضيات', '瓷砖与地面铺设'],
            ['hvac', 'Heating & cooling', 'تأسیسات گرمایش و سرمایش', 'التدفئة والتبريد', '暖通空调'],
            ['appliance-repair', 'Home appliance repair', 'تعمیر لوازم خانگی', 'إصلاح الأجهزة المنزلية', '家电维修'],
            ['cleaning', 'Cleaning', 'نظافت', 'التنظيف', '清洁服务'],
            ['interior-design', 'Interior design', 'طراحی داخلی', 'التصميم الداخلي', '室内设计'],
            ['real-estate', 'Real estate', 'املاک', 'العقارات', '房地产', ['property', 'service']],
            ['real-estate-brokerage', 'Property brokerage', 'مشاوره املاک', 'الوساطة العقارية', '房产中介'],
            ['property-appraisal', 'Property appraisal', 'کارشناسی ارزش ملک', 'تقييم العقارات', '房产评估'],
            ['rental', 'Renting & letting', 'اجاره ملک', 'الإيجار', '房屋租赁', ['property', 'service']],
            ['furniture', 'Furniture & home goods', 'مبلمان و لوازم خانه', 'الأثاث والمستلزمات المنزلية', '家具与家居用品', ['product', 'service']],
        ]],
        ['area' => ['food', 'Food & agriculture', 'خوراک و کشاورزی', 'الغذاء والزراعة', '食品与农业'], 'children' => [
            ['farming', 'Farming & crops', 'کشاورزی و زراعت', 'الزراعة والمحاصيل', '种植业'],
            ['livestock', 'Livestock & poultry', 'دامداری و طیور', 'الثروة الحيوانية والدواجن', '畜牧与家禽', ['skill', 'service', 'product']],
            ['gardening', 'Gardening & landscaping', 'باغبانی و فضای سبز', 'البستنة وتنسيق الحدائق', '园艺与景观'],
            ['cooking', 'Cooking & catering', 'آشپزی و پذیرایی', 'الطبخ وتقديم الطعام', '烹饪与餐饮服务'],
            ['baking', 'Baking & pastry', 'نانوایی و شیرینی‌پزی', 'المخبوزات والحلويات', '烘焙与糕点', ['skill', 'service', 'product']],
            ['groceries', 'Groceries', 'خواربار', 'البقالة', '食品杂货', ['product']],
            ['fresh-produce', 'Fruit & vegetables', 'میوه و سبزی', 'الفواكه والخضروات', '果蔬', ['product']],
            ['dairy', 'Dairy products', 'لبنیات', 'منتجات الألبان', '乳制品', ['product']],
            ['meat', 'Meat & protein', 'گوشت و پروتئین', 'اللحوم والبروتين', '肉类与蛋白质', ['product']],
            ['beverages', 'Beverages', 'نوشیدنی‌ها', 'المشروبات', '饮料', ['product']],
            ['food-processing', 'Food processing', 'فرآوری مواد غذایی', 'تصنيع الأغذية', '食品加工'],
            ['restaurants', 'Restaurants & cafés', 'رستوران و کافه', 'المطاعم والمقاهي', '餐厅与咖啡馆', ['service']],
            ['nutrition', 'Nutrition advice', 'مشاوره تغذیه', 'الاستشارات الغذائية', '营养咨询'],
        ]],
        ['area' => ['clothing', 'Clothing & personal goods', 'پوشاک و کالای شخصی', 'الملابس والمستلزمات الشخصية', '服装与个人用品'], 'children' => [
            ['tailoring', 'Tailoring & sewing', 'خیاطی', 'الخياطة', '裁缝'],
            ['clothes', 'Clothes', 'لباس', 'الملابس', '服装', ['product']],
            ['shoes-bags', 'Shoes & bags', 'کفش و کیف', 'الأحذية والحقائب', '鞋包', ['product']],
            ['textiles', 'Textiles & fabric', 'پارچه و منسوجات', 'الأقمشة والمنسوجات', '纺织面料', ['product']],
            ['knitting', 'Knitting & needlework', 'بافتنی و دوخت دستی', 'الحياكة والتطريز', '编织与刺绣', ['skill', 'service', 'product']],
            ['jewellery', 'Jewellery & accessories', 'زیورآلات و اکسسوری', 'المجوهرات والإكسسوارات', '珠宝配饰', ['product', 'skill', 'service']],
            ['cosmetics', 'Cosmetics & personal care', 'آرایشی و بهداشتی', 'مستحضرات التجميل والعناية الشخصية', '化妆品与个人护理', ['product']],
            ['hairdressing', 'Hairdressing & barbering', 'آرایشگری', 'تصفيف الشعر والحلاقة', '美发理发'],
            ['beauty-care', 'Beauty care', 'خدمات زیبایی', 'خدمات التجميل', '美容护理'],
            ['laundry', 'Laundry & dry cleaning', 'خشکشویی', 'المغاسل والتنظيف الجاف', '洗衣干洗', ['service']],
            ['clothing-repair', 'Clothing & shoe repair', 'تعمیر لباس و کفش', 'إصلاح الملابس والأحذية', '衣鞋修补'],
            ['fashion-design', 'Fashion design', 'طراحی مد', 'تصميم الأزياء', '时装设计'],
        ]],
        ['area' => ['health', 'Health & care', 'سلامت و مراقبت', 'الصحة والرعاية', '健康与护理'], 'children' => [
            ['medical-care', 'Medical care', 'خدمات پزشکی', 'الرعاية الطبية', '医疗服务'],
            ['nursing', 'Nursing', 'پرستاری', 'التمريض', '护理'],
            ['elderly-care', 'Elderly care', 'مراقبت از سالمند', 'رعاية المسنين', '老人照护'],
            ['childcare', 'Childcare', 'مراقبت از کودک', 'رعاية الأطفال', '儿童看护'],
            ['physiotherapy', 'Physiotherapy', 'فیزیوتراپی', 'العلاج الطبيعي', '物理治疗'],
            ['counselling', 'Counselling & mental health', 'مشاوره و سلامت روان', 'الإرشاد والصحة النفسية', '心理咨询与心理健康'],
            ['dentistry', 'Dental care', 'دندان‌پزشکی', 'طب الأسنان', '牙科'],
            ['pharmacy', 'Pharmacy & medicines', 'داروخانه و دارو', 'الصيدلية والأدوية', '药房与药品', ['product', 'service']],
            ['medical-equipment', 'Medical equipment', 'تجهیزات پزشکی', 'المعدات الطبية', '医疗设备', ['product', 'property']],
            ['disability-support', 'Disability support', 'حمایت از افراد دارای معلولیت', 'دعم ذوي الإعاقة', '残障支持'],
            ['veterinary', 'Veterinary & pet care', 'دامپزشکی و مراقبت از حیوانات', 'الطب البيطري ورعاية الحيوانات', '兽医与宠物护理'],
            ['first-aid', 'First aid', 'کمک‌های اولیه', 'الإسعافات الأولية', '急救'],
        ]],
        ['area' => ['education', 'Education & knowledge', 'آموزش و دانش', 'التعليم والمعرفة', '教育与知识'], 'children' => [
            ['school-tutoring', 'School tutoring', 'تدریس دروس مدرسه', 'الدروس الخصوصية المدرسية', '中小学辅导'],
            ['mathematics', 'Mathematics', 'ریاضی', 'الرياضيات', '数学'],
            ['sciences', 'Sciences', 'علوم', 'العلوم', '自然科学'],
            ['languages', 'Language teaching', 'آموزش زبان', 'تعليم اللغات', '语言教学'],
            ['university', 'University courses', 'دروس دانشگاهی', 'المقررات الجامعية', '大学课程'],
            ['exam-prep', 'Exam preparation', 'آمادگی آزمون و کنکور', 'التحضير للامتحانات', '考试准备'],
            ['vocational', 'Vocational training', 'آموزش فنی و حرفه‌ای', 'التدريب المهني', '职业培训'],
            ['arts-lessons', 'Art & music lessons', 'آموزش هنر و موسیقی', 'دروس الفنون والموسيقى', '艺术与音乐课程'],
            ['programming-lessons', 'Programming lessons', 'آموزش برنامه‌نویسی', 'دروس البرمجة', '编程课程'],
            ['research-writing', 'Research & academic writing', 'پژوهش و نگارش علمی', 'البحث والكتابة الأكاديمية', '研究与学术写作'],
            ['translation', 'Translation', 'ترجمه', 'الترجمة', '翻译'],
            ['books', 'Books & learning materials', 'کتاب و منابع آموزشی', 'الكتب والمواد التعليمية', '书籍与学习资料', ['product']],
            ['early-education', 'Early childhood education', 'آموزش کودکان خردسال', 'تعليم الطفولة المبكرة', '幼儿教育'],
        ]],
        ['area' => ['transport', 'Transportation & logistics', 'حمل‌ونقل و لجستیک', 'النقل والخدمات اللوجستية', '交通与物流'], 'children' => [
            ['driving', 'Driving', 'رانندگی', 'القيادة', '驾驶'],
            ['passenger', 'Passenger transport', 'جابه‌جایی مسافر', 'نقل الركاب', '客运', ['service']],
            ['freight', 'Freight & trucking', 'باربری و حمل بار', 'الشحن والنقل بالشاحنات', '货运', ['service']],
            ['moving', 'Moving house', 'اسباب‌کشی', 'نقل الأثاث', '搬家', ['service']],
            ['delivery', 'Courier & delivery', 'پیک و ارسال', 'التوصيل والبريد السريع', '快递配送', ['service']],
            ['vehicle-repair', 'Vehicle repair', 'تعمیر خودرو', 'إصلاح المركبات', '汽车维修'],
            ['vehicles', 'Vehicles & parts', 'خودرو و قطعات', 'المركبات وقطع الغيار', '车辆与配件', ['product', 'property']],
            ['vehicle-rental', 'Vehicle rental', 'اجاره خودرو', 'تأجير المركبات', '汽车租赁', ['property', 'service']],
            ['warehousing', 'Warehousing & storage', 'انبارداری', 'التخزين والمستودعات', '仓储', ['service', 'property']],
            ['logistics', 'Logistics planning', 'برنامه‌ریزی لجستیک', 'تخطيط الخدمات اللوجستية', '物流规划'],
            ['bicycles', 'Bicycles & scooters', 'دوچرخه و اسکوتر', 'الدراجات والسكوترات', '自行车与滑板车', ['product', 'service']],
        ]],
        ['area' => ['industry', 'Industry & manufacturing', 'صنعت و تولید', 'الصناعة والتصنيع', '工业与制造'], 'children' => [
            ['welding', 'Welding', 'جوشکاری', 'اللحام', '焊接'],
            ['machining', 'Machining & CNC', 'ماشین‌کاری و CNC', 'التشغيل الآلي و CNC', '机械加工与数控'],
            ['metalwork', 'Metalwork', 'فلزکاری', 'تشكيل المعادن', '金属加工'],
            ['quality-inspection', 'Quality control & inspection', 'کنترل کیفیت و بازرسی', 'مراقبة الجودة والتفتيش', '质量控制与检验'],
            ['maintenance-repair', 'Technical maintenance & repair', 'تعمیر و نگهداری فنی', 'الصيانة والإصلاح الفني', '技术维修保养'],
            ['industrial-electrical', 'Industrial electrical work', 'برق صنعتی', 'الكهرباء الصناعية', '工业电气'],
            ['mechanical-engineering', 'Mechanical engineering', 'مهندسی مکانیک', 'الهندسة الميكانيكية', '机械工程'],
            ['production-work', 'Production line work', 'کارگری خط تولید', 'العمل في خطوط الإنتاج', '生产线工作'],
            ['raw-materials', 'Raw materials', 'مواد اولیه', 'المواد الخام', '原材料', ['product']],
            ['tools-machinery', 'Tools & machinery', 'ابزار و ماشین‌آلات', 'الأدوات والآلات', '工具与机械', ['product', 'property']],
            ['printing-packaging', 'Printing & packaging', 'چاپ و بسته‌بندی', 'الطباعة والتغليف', '印刷与包装'],
            ['plastics-chemicals', 'Plastics & chemicals', 'پلاستیک و مواد شیمیایی', 'البلاستيك والكيماويات', '塑料与化工', ['product', 'service']],
        ]],
        ['area' => ['technology', 'Technology & digital services', 'فناوری و خدمات دیجیتال', 'التكنولوجيا والخدمات الرقمية', '科技与数字服务'], 'children' => [
            ['web-development', 'Web development', 'توسعه وب', 'تطوير المواقع', '网站开发'],
            ['mobile-apps', 'Mobile apps', 'اپلیکیشن موبایل', 'تطبيقات الجوال', '移动应用'],
            ['software', 'Software development', 'توسعه نرم‌افزار', 'تطوير البرمجيات', '软件开发'],
            ['graphic-design', 'Graphic & UI design', 'طراحی گرافیک و رابط کاربری', 'التصميم الجرافيكي وواجهات المستخدم', '平面与界面设计'],
            ['data-ai', 'Data & AI', 'داده و هوش مصنوعی', 'البيانات والذكاء الاصطناعي', '数据与人工智能'],
            ['networks', 'Networks & servers', 'شبکه و سرور', 'الشبكات والخوادم', '网络与服务器'],
            ['cybersecurity', 'Cybersecurity', 'امنیت سایبری', 'الأمن السيبراني', '网络安全'],
            ['device-repair', 'Computer & phone repair', 'تعمیر رایانه و موبایل', 'إصلاح الحواسيب والهواتف', '电脑手机维修'],
            ['electronics', 'Electronics & devices', 'الکترونیک و دستگاه‌ها', 'الإلكترونيات والأجهزة', '电子产品与设备', ['product']],
            ['digital-marketing', 'Digital marketing', 'بازاریابی دیجیتال', 'التسويق الرقمي', '数字营销'],
            ['content-creation', 'Content creation & video', 'تولید محتوا و ویدئو', 'صناعة المحتوى والفيديو', '内容创作与视频'],
            ['it-support', 'IT support', 'پشتیبانی فناوری اطلاعات', 'الدعم الفني لتقنية المعلومات', 'IT 支持'],
        ]],
        ['area' => ['business', 'Business & professional services', 'کسب‌وکار و خدمات حرفه‌ای', 'الأعمال والخدمات المهنية', '商业与专业服务'], 'children' => [
            ['consulting', 'Business consulting', 'مشاوره کسب‌وکار', 'الاستشارات الإدارية', '商业咨询'],
            ['legal', 'Legal services', 'خدمات حقوقی', 'الخدمات القانونية', '法律服务'],
            ['marketing-sales', 'Marketing & sales', 'بازاریابی و فروش', 'التسويق والمبيعات', '市场营销与销售'],
            ['retail-sales', 'Retail & shop sales', 'فروشندگی و خرده‌فروشی', 'البيع بالتجزئة', '零售销售'],
            ['store-management', 'Store management', 'مدیریت فروشگاه', 'إدارة المتاجر', '门店管理'],
            ['administration', 'Office administration', 'امور اداری', 'الأعمال الإدارية المكتبية', '行政办公'],
            ['human-resources', 'Human resources', 'منابع انسانی', 'الموارد البشرية', '人力资源'],
            ['project-management', 'Project management', 'مدیریت پروژه', 'إدارة المشاريع', '项目管理'],
            ['customer-service', 'Customer service', 'خدمات مشتریان', 'خدمة العملاء', '客户服务'],
            ['trade', 'Trade, import & export', 'بازرگانی، واردات و صادرات', 'التجارة والاستيراد والتصدير', '贸易与进出口'],
            ['procurement', 'Purchasing & supply', 'خرید و تأمین', 'المشتريات والتوريد', '采购与供应'],
            ['security-services', 'Security & guarding', 'نگهبانی و حراست', 'الحراسة والأمن', '安保服务'],
        ]],
        ['area' => ['finance', 'Finance & resources', 'مالی و منابع', 'المال والموارد', '金融与资源'], 'children' => [
            ['accounting', 'Accounting & bookkeeping', 'حسابداری', 'المحاسبة ومسك الدفاتر', '会计记账'],
            ['tax', 'Tax advice', 'مشاوره مالیاتی', 'الاستشارات الضريبية', '税务咨询'],
            ['financial-planning', 'Financial planning', 'برنامه‌ریزی مالی', 'التخطيط المالي', '理财规划'],
            ['insurance', 'Insurance', 'بیمه', 'التأمين', '保险', ['service']],
            ['loans-credit', 'Loans & credit', 'وام و اعتبار', 'القروض والائتمان', '贷款与信贷', ['service']],
            ['investment', 'Investment', 'سرمایه‌گذاری', 'الاستثمار', '投资', ['service', 'property']],
            ['auditing', 'Auditing', 'حسابرسی', 'التدقيق', '审计'],
            ['payroll', 'Payroll', 'حقوق و دستمزد', 'الرواتب', '薪资管理'],
            ['funding', 'Funding & partnership', 'تأمین سرمایه و مشارکت', 'التمويل والشراكة', '融资与合伙', ['service', 'activity']],
            ['equipment-sharing', 'Sharing tools & equipment', 'اشتراک ابزار و تجهیزات', 'مشاركة الأدوات والمعدات', '工具设备共享', ['property', 'service']],
        ]],
        ['area' => ['arts', 'Arts & entertainment', 'هنر و سرگرمی', 'الفنون والترفيه', '艺术与娱乐'], 'children' => [
            ['music', 'Music', 'موسیقی', 'الموسيقى', '音乐'],
            ['painting-drawing', 'Painting & drawing', 'نقاشی و طراحی', 'الرسم والتلوين', '绘画'],
            ['photography', 'Photography', 'عکاسی', 'التصوير الفوتوغرافي', '摄影'],
            ['film-video', 'Film & video', 'فیلم و ویدئو', 'الأفلام والفيديو', '影视制作'],
            ['theatre', 'Theatre & performance', 'تئاتر و نمایش', 'المسرح والأداء', '戏剧表演'],
            ['writing', 'Writing & literature', 'نویسندگی و ادبیات', 'الكتابة والأدب', '写作与文学'],
            ['calligraphy', 'Calligraphy', 'خوشنویسی', 'الخط', '书法'],
            ['handicrafts', 'Handicrafts', 'صنایع‌دستی', 'الحرف اليدوية', '手工艺品', ['skill', 'service', 'product']],
            ['events', 'Events & celebrations', 'مراسم و جشن‌ها', 'المناسبات والاحتفالات', '活动与庆典', ['service', 'activity']],
            ['games', 'Games & recreation', 'بازی و تفریح', 'الألعاب والترفيه', '游戏与娱乐', ['activity', 'product']],
            ['instruments', 'Musical instruments', 'سازهای موسیقی', 'الآلات الموسيقية', '乐器', ['product']],
        ]],
        ['area' => ['environment', 'Environment & energy', 'محیط زیست و انرژی', 'البيئة والطاقة', '环境与能源'], 'children' => [
            ['solar', 'Solar energy', 'انرژی خورشیدی', 'الطاقة الشمسية', '太阳能'],
            ['power-supply', 'Power supply', 'تأمین برق', 'إمدادات الكهرباء', '供电'],
            ['water', 'Water & irrigation', 'آب و آبیاری', 'المياه والري', '水与灌溉'],
            ['recycling', 'Recycling & waste', 'بازیافت و پسماند', 'إعادة التدوير والنفايات', '回收与废物处理'],
            ['energy-saving', 'Energy saving', 'بهینه‌سازی مصرف انرژی', 'ترشيد استهلاك الطاقة', '节能'],
            ['nature', 'Tree planting & nature care', 'درختکاری و حفظ طبیعت', 'زراعة الأشجار وحماية الطبيعة', '植树与自然保护', ['activity', 'service']],
            ['pest-control', 'Pest control', 'سم‌پاشی و دفع آفات', 'مكافحة الآفات', '虫害防治'],
            ['environmental-consulting', 'Environmental consulting', 'مشاوره محیط زیست', 'الاستشارات البيئية', '环境咨询'],
            ['fuel-heating', 'Fuel & heating supply', 'سوخت و گرمایش', 'الوقود والتدفئة', '燃料与供暖', ['product', 'service']],
        ]],
        ['area' => ['community', 'Community & social services', 'اجتماع و خدمات اجتماعی', 'المجتمع والخدمات الاجتماعية', '社区与社会服务'], 'children' => [
            ['volunteering', 'Volunteering', 'کار داوطلبانه', 'العمل التطوعي', '志愿服务', ['activity', 'skill', 'service']],
            ['charity', 'Charity & donations', 'خیریه و کمک‌رسانی', 'الأعمال الخيرية والتبرعات', '慈善与捐赠', ['activity', 'service']],
            ['neighbourhood', 'Neighbourhood help', 'کمک محلی و همسایگی', 'المساعدة في الحي', '邻里互助', ['activity', 'service']],
            ['religious', 'Religious & spiritual', 'مذهبی و معنوی', 'الشؤون الدينية والروحية', '宗教与精神', ['activity', 'service']],
            ['social-work', 'Social work', 'مددکاری اجتماعی', 'العمل الاجتماعي', '社会工作'],
            ['community-events', 'Community events', 'رویدادهای محلی', 'الفعاليات المجتمعية', '社区活动', ['activity']],
            ['mentoring', 'Mentoring', 'راهنمایی و منتورینگ', 'الإرشاد والتوجيه', '导师指导'],
            ['clubs', 'Clubs & associations', 'انجمن‌ها و باشگاه‌ها', 'الأندية والجمعيات', '俱乐部与协会', ['activity']],
            ['family-support', 'Family support', 'حمایت از خانواده', 'دعم الأسرة', '家庭支持'],
        ]],
        ['area' => ['civic', 'Government & civic affairs', 'امور دولتی و مدنی', 'الشؤون الحكومية والمدنية', '政府与公民事务'], 'children' => [
            ['documents', 'Official documents & paperwork', 'امور اسناد و مدارک', 'الوثائق والمعاملات الرسمية', '证件与文书办理'],
            ['permits', 'Permits & licences', 'مجوزها و پروانه‌ها', 'التصاريح والتراخيص', '许可与执照'],
            ['notary', 'Notary & registration', 'دفترخانه و ثبت', 'التوثيق والتسجيل', '公证与登记', ['service']],
            ['legal-aid', 'Legal aid', 'کمک حقوقی', 'المساعدة القانونية', '法律援助'],
            ['public-services', 'Public services', 'خدمات عمومی', 'الخدمات العامة', '公共服务', ['service']],
            ['civic-participation', 'Civic participation', 'مشارکت مدنی', 'المشاركة المدنية', '公民参与', ['activity']],
            ['taxes-fees', 'Taxes & public fees', 'مالیات و عوارض', 'الضرائب والرسوم العامة', '税费缴纳', ['service']],
            ['utilities', 'Utilities & bills', 'قبوض و خدمات شهری', 'المرافق والفواتير', '水电煤与账单', ['service']],
            ['urban-planning', 'Urban planning', 'شهرسازی', 'التخطيط العمراني', '城市规划'],
        ]],
        ['area' => ['travel', 'Travel & hospitality', 'سفر و گردشگری', 'السفر والضيافة', '旅行与酒店'], 'children' => [
            ['accommodation', 'Accommodation', 'اقامتگاه', 'أماكن الإقامة', '住宿', ['property', 'service']],
            ['tour-guiding', 'Tour guiding', 'راهنمای تور', 'الإرشاد السياحي', '导游'],
            ['travel-agency', 'Tickets & travel agency', 'بلیت و آژانس مسافرتی', 'التذاكر ووكالات السفر', '票务与旅行社', ['service']],
            ['hotels', 'Hotels & guesthouses', 'هتل و مهمان‌پذیر', 'الفنادق ودور الضيافة', '酒店与民宿', ['property', 'service']],
            ['ecotourism', 'Nature & ecotourism', 'طبیعت‌گردی', 'السياحة البيئية', '生态旅游', ['activity', 'service']],
            ['pilgrimage', 'Pilgrimage trips', 'سفرهای زیارتی', 'رحلات الزيارة', '朝圣旅行', ['activity', 'service']],
            ['travel-gear', 'Travel & camping gear', 'لوازم سفر و کمپینگ', 'معدات السفر والتخييم', '旅行露营装备', ['product', 'property']],
            ['hospitality-work', 'Hospitality work', 'خدمات پذیرایی و هتلداری', 'العمل في الضيافة', '酒店服务工作'],
            ['venues', 'Venues & halls', 'سالن و محل برگزاری', 'القاعات وأماكن الفعاليات', '场地与会场', ['property', 'service']],
        ]],
        ['area' => ['sports', 'Sports & fitness', 'ورزش و تندرستی', 'الرياضة واللياقة', '体育与健身'], 'children' => [
            ['coaching', 'Sports coaching', 'مربیگری ورزشی', 'التدريب الرياضي', '体育教练'],
            ['fitness', 'Fitness & gym', 'بدنسازی و تناسب اندام', 'اللياقة البدنية', '健身'],
            ['team-sports', 'Team sports', 'ورزش‌های گروهی', 'الرياضات الجماعية', '团体运动', ['activity', 'skill']],
            ['martial-arts', 'Martial arts', 'ورزش‌های رزمی', 'الفنون القتالية', '武术', ['activity', 'skill', 'service']],
            ['swimming', 'Swimming', 'شنا', 'السباحة', '游泳', ['activity', 'skill', 'service']],
            ['yoga', 'Yoga & mindfulness', 'یوگا و مدیتیشن', 'اليوغا والتأمل', '瑜伽与冥想', ['activity', 'skill', 'service']],
            ['mountaineering', 'Hiking & mountaineering', 'کوهنوردی', 'المشي وتسلق الجبال', '徒步与登山', ['activity', 'skill']],
            ['cycling-running', 'Cycling & running', 'دوچرخه‌سواری و دویدن', 'ركوب الدراجات والجري', '骑行与跑步', ['activity']],
            ['sports-equipment', 'Sports equipment', 'لوازم ورزشی', 'المعدات الرياضية', '体育器材', ['product']],
            ['sports-facilities', 'Sports facilities', 'اماکن ورزشی', 'المنشآت الرياضية', '体育场馆', ['property', 'service']],
            ['sports-events', 'Sports events', 'رویدادهای ورزشی', 'الفعاليات الرياضية', '体育赛事', ['activity']],
        ]],
    ];
}
