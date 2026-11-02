<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Sistem CRM</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="crm-internal">
<a class="skip-link" href="#main-content">Lewati navigasi</a>
@php
    $user = auth()->user();
    $role = $user->role;

    $access = app(\App\Services\CrmAccess::class);
    $inquiries = $access->scope(\App\Models\Inquiry::query(), $user, 'inquiries');
    $workflow = app(\App\Services\HandlingWorkflow::class);
    $inboxCount = (clone $inquiries)->whereIn('status', ['baru', 'ditugaskan'])
        ->get()->filter(fn ($inquiry) => in_array($workflow->state($inquiry), ['active', 'waiting'], true))->count();
    $inboxCount += \App\Models\WhatsappBotConversation::query()
        ->where('handed_off', true)
        ->whereNull('inquiry_id')
        ->whereIn('handling_status', ['active', 'waiting'])
        ->when($role === 'cs', fn ($query) => $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to')))
        ->count();
    $orderCount = $access->scope(\App\Models\Order::query(), $user, 'orders')
        ->whereIn('status', ['baru', 'diproses'])->count();
    $ticketCount = $access->scope(\App\Models\Ticket::query(), $user, 'tickets')
        ->whereNotIn('status', ['selesai', 'ditutup'])->count();
    $badges = ['inbox' => $inboxCount, 'orders.index' => $orderCount, 'tickets.index' => $ticketCount];
    $workspace = [['dashboard','Dashboard','M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z'], ['inbox','Inbox WhatsApp','M3 5h18v14H3z M3 6l9 7 9-7']];
    if ($role !== 'admin') $workspace = array_merge($workspace, [['contacts.index','Pelanggan','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 21v-2a4 4 0 0 0-3-4'], ['orders.index','Pesanan','M4 4h16v17H4z M8 2v4 M16 2v4 M8 11h8 M8 15h5'], ['tickets.index','Keluhan','M21 11a9 9 0 0 1-9 9H3l2-5a9 9 0 1 1 16-4 M12 7v5 M12 15h.01']]);
    $management = $role === 'owner' ? [['reports.index','Laporan','M4 20V10 M10 20V4 M16 20v-8 M22 20H2']] : ($role === 'admin' ? [['admin.users.index','Pengguna','M4 21v-3a5 5 0 0 1 10 0v3 M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M18 8v8 M14 12h8'], ['admin.services.index','Layanan','M3 7h18v14H3z M8 7V3h8v4 M3 12h18'], ['admin.faqs','FAQ & informasi bot','M4 3h16v18H4z M9 8a3 3 0 1 1 4 3l-1 2 M12 16h.01']] : []);
@endphp
<div class="app">
    <div class="overlay" data-menu-close></div>
    <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
        <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark" aria-hidden="true">S</span><span>Sistem CRM<small>LAYANAN PELANGGAN</small></span></a>
        <button class="btn sidebar-close" type="button" data-menu-dismiss aria-label="Tutup menu">Tutup menu ×</button>
        @foreach(['Ruang kerja' => $workspace, 'Analisis & pengaturan' => $management] as $section => $links)
            @if(count($links))<nav aria-label="{{ $section }}"><div class="nav-caption">{{ $section }}</div>
            @foreach($links as [$link, $label, $path])
                @php $active = request()->routeIs(str_replace('.index', '.*', $link)) || ($link === 'inbox' && request()->routeIs('inquiries.*', 'whatsapp.handoffs*')); @endphp
                <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route($link) }}" @if($active) aria-current="page" @endif><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg><span class="nav-label">{{ $label }}</span>@if(($badges[$link] ?? 0) > 0)<span class="nav-badge" aria-label="{{ $badges[$link] }} item belum selesai">{{ $badges[$link] > 99 ? '99+' : $badges[$link] }}</span>@endif</a>
            @endforeach</nav>@endif
        @endforeach
        <div class="sidebar-bottom">
            <div class="user-chip">
                <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
                <div class="user-details">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span class="user-role"><span class="user-status-dot" aria-hidden="true"></span>{{ ['cs'=>'Customer Service','owner'=>'Pemilik','admin'=>'Administrator'][$role] }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout" type="submit"><span class="logout-icon" aria-hidden="true">↪</span><span>Keluar dari akun</span><span class="logout-arrow" aria-hidden="true">→</span></button>
            </form>
        </div>
    </aside>
    <div class="main">
        <header class="topbar"><div class="top-actions"><button class="mobile-toggle" type="button" data-menu-open aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">☰</button><span class="crumb">Ruang kerja / <strong>@yield('title','Dashboard')</strong></span></div><span class="date">{{ now()->locale('id')->translatedFormat('d F Y') }}</span></header>
        <main class="content" id="main-content" tabindex="-1">
            @if(session('success'))<div class="alert" role="status">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-error" role="alert">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="alert alert-error" role="alert"><strong>Mohon periksa kembali data Anda.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
