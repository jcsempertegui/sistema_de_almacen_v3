<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel') · SGA CONFIPETROL</title>
    <link rel="stylesheet" href="{{ asset('css/sga.css') }}?v={{ filemtime(public_path('css/sga.css')) }}">
    <script src="{{ asset('js/sga-ui.js') }}?v={{ filemtime(public_path('js/sga-ui.js')) }}" defer></script>
</head>
<body class="@auth app-shell @else guest-shell @endauth @yield('body-class')">
<a class="skip-link" href="#main-content">Ir al contenido</a>
@auth
    <aside class="sidebar" id="main-navigation" aria-label="Menú del sistema">
        <div class="sidebar-brand">
            <img src="{{ asset('images/confipetrol.png') }}" width="42" height="42" alt="">
            <div><strong>CONFIPETROL</strong><span>Gestión de almacén</span></div>
            <button type="button" class="icon-button sidebar-close" data-nav-close aria-label="Cerrar menú"><x-icon name="close" /></button>
        </div>
        @include('layouts.navigation')
        <div class="sidebar-footer"><x-icon name="location" /><div><strong>Río Grande</strong><span>Planta Separadora de Líquidos</span></div></div>
    </aside>
    <button type="button" class="nav-backdrop" data-nav-close aria-label="Cerrar menú" tabindex="-1" hidden></button>
    <header class="topbar">
        <div class="topbar-context">
            <button type="button" id="nav-toggle" class="icon-button mobile-menu-toggle" aria-controls="main-navigation" aria-expanded="false" aria-label="Abrir menú" hidden><x-icon name="menu" /></button>
            <div><span class="topbar-eyebrow">SISTEMA DE GESTIÓN DE ALMACÉN</span><span class="topbar-title">@yield('title', 'Panel')</span></div>
        </div>
        <div class="topbar-account">
            <span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="user-copy"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->role->name }}</span></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit"><x-icon name="logout" /><span>Cerrar sesión</span></button></form>
        </div>
    </header>
@else
    <header class="guest-header"><img src="{{ asset('images/confipetrol.png') }}" alt="" width="35" height="35"><strong>SGA CONFIPETROL</strong></header>
@endauth
<main class="container" id="main-content" tabindex="-1">
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="notice error" role="alert"><strong>Revisa los siguientes datos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
@auth<footer class="app-footer"><span>SGA CONFIPETROL</span><span>Planta Separadora de Líquidos Río Grande</span></footer>@endauth
</body>
</html>
