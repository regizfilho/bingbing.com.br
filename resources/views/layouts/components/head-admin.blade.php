<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="vapid-public-key" content="{{ config('services.vapid.public_key') }}">

<meta name="author" content="BingBing" />
<meta name="google" content="notranslate" data-rh="true" />
<meta name="robots" content="noindex, nofollow" data-rh="true" />
<meta name="description" content="{{ config('app.name', 'BingBing') }}." data-rh="true" />
<meta name="applicable-device" content="pc, mobile" data-rh="true" />
<meta name="canonical" content="{{ url()->current() }}" data-rh="true" />
<meta name="keywords" content="Controle de Finanças" data-rh="true" />
<title>Painel Admin | {{ config('app.name', 'BingBing') }}</title>
<link rel="shortcut icon" type="image/png" href="favicon.png">
<meta name="theme-color" content="#008D52">
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'BingBing') }}" />
<meta name="apple-mobile-web-app-status-bar-style" content="black" />
<link rel="apple-touch-icon" href="/imgs/apple-touch-icon.png" />
<link rel="manifest" href="/manifest.json" />

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

<style>
    body {
        background-color: #05070a;
        font-family: 'Inter', sans-serif;
    }

    [x-cloak] {
        display: none !important;
    }

    /* Scrollbar Profissional */
    ::-webkit-scrollbar {
        width: 5px;
    }

    ::-webkit-scrollbar-track {
        background: #05070a;
    }

    ::-webkit-scrollbar-thumb {
        background: #1e293b;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #334155;
    }
</style>

@livewireStyles
@vite(['resources/css/app.css', 'resources/js/app.js'])

@yield('head')
