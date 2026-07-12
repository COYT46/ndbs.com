<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LPR Dashboard')</title>

    <!--plugins-->
    <link href="{{ asset('public/admin/assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('public/admin/assets/plugins/metismenu/metisMenu.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('public/admin/assets/plugins/metismenu/mm-vertical.css') }}">
    <link rel="stylesheet" type="text/css"
        href="{{ asset('public/admin/assets/plugins/simplebar/css/simplebar.css') }}">
    <!--bootstrap css-->
    <link href="{{ asset('public/admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=groups_3" />
    <!--main css-->
    <link href="{{ asset('public/admin/assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ asset('public/admin/sass/main.css') }}" rel="stylesheet">
    <link href="{{ asset('public/admin/sass/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('public/admin/sass/semi-dark.css') }}" rel="stylesheet">
    <link href="{{ asset('public/admin/sass/bordered-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('public/admin/sass/responsive.css') }}" rel="stylesheet">
    @stack('css')
</head>

<body>

    <!--start header-->
    <header class="top-header">
        <nav class="navbar navbar-expand align-items-center gap-4">
            <div class="btn-toggle">
                <a href="javascript:;"><i class="material-icons-outlined">menu</i></a>
            </div>
            <div class="search-bar flex-grow-1">
                <h5 class="mb-0">Hệ thống Nhận diện Biển số xe</h5>
            </div>
            <ul class="navbar-nav gap-1 nav-right-links align-items-center">
                <li class="nav-item dropdown">
                    <a href="javascript:;" class="dropdown-toggle dropdown-toggle-nocaret" data-bs-toggle="dropdown">
                        <img src="{{ asset('public/admin/assets/images/avatars/01.png') }}"
                            class="rounded-circle p-1 border" width="45" height="45">
                    </a>
                    <div class="dropdown-menu dropdown-user dropdown-menu-end shadow">
                        <a class="dropdown-item  gap-2 py-2" href="javascript:;">
                            <div class="text-center">
                                <h5 class="user-name mb-0 fw-bold">{{ auth()->user()->fullname ?? 'User' }}</h5>
                                <p class="mb-0">{{ auth()->user()->role === 'manager' ? 'Quản lý' : 'Bảo vệ' }}</p>
                            </div>
                        </a>
                        <hr class="dropdown-divider">
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="material-icons-outlined">power_settings_new</i> Đăng xuất
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </li>
            </ul>
        </nav>
    </header>
    <!--end top header-->

    <!--start sidebar-->
    <aside class="sidebar-wrapper">
        <div class="sidebar-header">
            <div class="logo-name flex-grow-1">
                <h5 class="mb-0 text-primary fw-bold">LPR System</h5>
            </div>
            <div class="sidebar-close">
                <span class="material-icons-outlined">close</span>
            </div>
        </div>
        <div class="sidebar-nav" data-simplebar="true">
            <ul class="metismenu" id="sidenav">
                @if (auth()->check())
                    @if (auth()->user()->role === 'manager')
                        <li>
                            <a href="{{ route('manager.dashboard') }}">
                                <div class="parent-icon"><span class="material-symbols-outlined">groups_3</span></div>
                                <div class="menu-title">Quản lý tài khoản</div>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('manager.vehicle_logs') }}">
                                <div class="parent-icon"><i class="material-icons-outlined">history</i></div>
                                <div class="menu-title">Lịch sử ra vào</div>
                            </a>
                        </li>
                    @elseif(auth()->user()->role === 'guard')
                        <li>
                            <a href="{{ route('guard.dashboard') }}">
                                <div class="parent-icon"><i class="material-icons-outlined">camera_alt</i></div>
                                <div class="menu-title">Giám sát Xe ra vào</div>
                            </a>
                        </li>
                    @endif
                @endif
            </ul>
        </div>
    </aside>
    <!--end sidebar-->

    <!--start main wrapper-->
    <main class="main-wrapper">
        <div class="main-content">
            @yield('content')
        </div>
    </main>
    <!--end main wrapper-->

    <!--bootstrap js-->
    <script src="{{ asset('public/admin/assets/js/bootstrap.bundle.min.js') }}"></script>

    <!--plugins-->
    <script src="{{ asset('public/admin/assets/js/jquery.min.js') }}"></script>
    <!--plugins-->
    <script src="{{ asset('public/admin/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('public/admin/assets/plugins/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ asset('public/admin/assets/plugins/simplebar/js/simplebar.min.js') }}"></script>
    <script src="{{ asset('public/admin/assets/js/main.js') }}"></script>

    @stack('js')

</body>

</html>
