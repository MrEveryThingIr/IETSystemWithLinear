<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دفتر پرونده‌ها — {{ $portal->title }}</title>
    @vite('resources/css/app.css')
    <style>
        body{background:#f1f5f9;color:#0f172a}
        .wrap{max-width:1450px;margin:auto;padding:28px 16px}
        .top{display:flex;justify-content:space-between;gap:15px;align-items:end;flex-wrap:wrap}
        .cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:22px 0}
        .card,.filters,.tablebox{background:#fff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 5px 20px rgba(15,23,42,.04)}
        .card{padding:18px}.card b{font-size:30px;display:block;margin-top:7px}
        .filters{padding:18px;margin-bottom:18px}
        .fgrid{display:grid;grid-template-columns:2fr repeat(3,1fr) auto;gap:10px;align-items:end}
        input,select{width:100%;box-sizing:border-box;border:1.5px solid #94a3b8!important;background:white!important;border-radius:12px;padding:11px 12px}
        label span{display:block;font-size:13px;font-weight:800;margin-bottom:6px;color:#475569}
        button,.btn{border:0;border-radius:12px;padding:11px 16px;font-weight:900;cursor:pointer;text-decoration:none;display:inline-block}
        .primary{background:#047857;color:#fff}.dark{background:#0f172a;color:#fff}.light{background:#fff;color:#334155;border:1px solid #cbd5e1}
        .tablebox{overflow:hidden}.scroll{overflow:auto}
        table{width:100%;border-collapse:collapse;min-width:1050px}
        th{background:#f8fafc;text-align:right;font-size:12px;color:#475569;padding:14px;border-bottom:1px solid #e2e8f0}
        td{padding:14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        tr:hover td{background:#f0fdf4}
        .badge{display:inline-block;border-radius:999px;padding:5px 10px;background:#f1f5f9;font-size:12px;font-weight:800}
        .pager{padding:16px}
        @media(max-width:900px){.cards{grid-template-columns:repeat(2,1fr)}.fgrid{grid-template-columns:1fr 1fr}}
        @media(max-width:600px){.cards,.fgrid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="wrap">
    <div class="top">
        <div>
            <div style="color:#047857;font-weight:900">دفتر املاک</div>
            <h1 style="font-size:32px;margin:5px 0">پرونده‌های {{ $portal->title }}</h1>
            <p style="color:#64748b;margin:0">جست‌وجو، فیلتر و پیگیری پرونده‌های ثبت‌شده</p>
        </div>
        <a class="btn light" target="_blank" href="{{ route('public.real-estate.show', $portal) }}">باز کردن فرم عمومی</a>
    </div>

    <section class="cards">
        <div class="card">همه پرونده‌ها <b>{{ number_format($stats['total']) }}</b></div>
        <div class="card">جدید <b>{{ number_format($stats['new']) }}</b></div>
        <div class="card">عرضه ملک <b>{{ number_format($stats['offers']) }}</b></div>
        <div class="card">متقاضی <b>{{ number_format($stats['needs']) }}</b></div>
    </section>

    <form method="GET" class="filters">
        <div class="fgrid">
            <label><span>جست‌وجو</span><input name="q" value="{{ request('q') }}" placeholder="نام، تلفن، کد، محله، آدرس..."></label>
            <label><span>نوع پرونده</span><select name="intent"><option value="">همه</option><option value="offer" @selected(request('intent')==='offer')>عرضه</option><option value="need" @selected(request('intent')==='need')>تقاضا</option></select></label>
            <label><span>معامله</span><select name="transaction_mode"><option value="">همه</option><option value="sale" @selected(request('transaction_mode')==='sale')>خرید/فروش</option><option value="rent" @selected(request('transaction_mode')==='rent')>رهن/اجاره</option></select></label>
            <label><span>وضعیت</span><select name="status">
                <option value="">همه</option>
                @foreach(['new'=>'جدید','contacted'=>'تماس گرفته شد','qualified'=>'واجد شرایط','in_progress'=>'در حال پیگیری','closed'=>'بسته‌شده','rejected'=>'ردشده'] as $v=>$t)
                    <option value="{{ $v }}" @selected(request('status')===$v)>{{ $t }}</option>
                @endforeach
            </select></label>
            <div style="display:flex;gap:7px"><button class="primary">اعمال</button><a class="btn light" href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}">پاک</a></div>
        </div>
    </form>

    <section class="tablebox">
        <div class="scroll">
            <table>
                <thead><tr>
                    <th>پرونده</th><th>مراجعه‌کننده</th><th>نوع</th><th>ملک / محدوده</th><th>متراژ</th><th>قیمت</th><th>وضعیت</th><th></th>
                </tr></thead>
                <tbody>
                @forelse($cases as $case)
                    @php($statusText=['new'=>'جدید','contacted'=>'تماس گرفته شد','qualified'=>'واجد شرایط','in_progress'=>'در حال پیگیری','closed'=>'بسته‌شده','rejected'=>'ردشده'])
                    <tr>
                        <td><b style="font-family:monospace">{{ $case->reference_code }}</b><br><small style="color:#94a3b8">{{ $case->created_at?->format('Y-m-d H:i') }}</small></td>
                        <td><b>{{ $case->contact_name }}</b><br><span dir="ltr" style="color:#64748b">{{ $case->phone }}</span></td>
                        <td><span class="badge">{{ $case->intent==='offer'?'عرضه':'تقاضا' }}</span><br><small>{{ $case->transaction_mode==='sale'?'خرید/فروش':'رهن/اجاره' }}</small></td>
                        <td><b>{{ $case->property_subtype ?: $case->property_class }}</b><br><small style="color:#64748b">{{ $case->public_area ?: '—' }}</small></td>
                        <td>{{ $case->land_area ?: '—' }} m²</td>
                        <td>
                            @if($case->asking_price)
                                {{ number_format((float)$case->asking_price) }} تومان
                            @elseif($case->deposit_amount || $case->monthly_rent_amount)
                                رهن {{ number_format((float)($case->deposit_amount ?? 0)) }}<br>
                                <small>اجاره {{ number_format((float)($case->monthly_rent_amount ?? 0)) }}</small>
                            @else — @endif
                        </td>
                        <td><span class="badge">{{ $statusText[$case->status] ?? $case->status }}</span></td>
                        <td><a class="btn dark" href="{{ route('office.real-estate.show',['portal'=>$portal->uuid,'case'=>$case]) }}">مشاهده</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:50px"><b>پرونده‌ای پیدا نشد</b></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($cases->hasPages())<div class="pager">{{ $cases->links() }}</div>@endif
    </section>
</main>
</body>
</html>
