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
                            onclick="event.preventDefault(); window.ndbsLogout && window.ndbsLogout();">
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
                        <li class="{{ request()->is('guard/*') ? 'mm-active' : '' }}">
                            <a href="javascript:;" class="has-arrow">
                                <div class="parent-icon"><i class="material-icons-outlined">camera_alt</i></div>
                                <div class="menu-title">Giám sát Xe ra vào</div>
                            </a>
                            <ul>
                                <li class="{{ request()->routeIs('guard.recognize') ? 'mm-active' : '' }}">
                                    <a href="{{ route('guard.recognize') }}">
                                        <div class="menu-title">Nhận diện bằng ảnh</div>
                                    </a>
                                </li>
                                <li class="{{ request()->routeIs('guard.dashboard') ? 'mm-active' : '' }}">
                                    <a href="{{ route('guard.dashboard') }}">
                                        <div class="menu-title">Màn hình giám sát</div>
                                    </a>
                                </li>
                                <li class="{{ request()->is('guard/scan/entry') ? 'mm-active' : '' }}">
                                    <a href="{{ route('guard.scan', ['side' => 'entry']) }}">
                                        <div class="menu-title">Camera ĐT — xe vào</div>
                                    </a>
                                </li>
                                <li class="{{ request()->is('guard/scan/exit') ? 'mm-active' : '' }}">
                                    <a href="{{ route('guard.scan', ['side' => 'exit']) }}">
                                        <div class="menu-title">Camera ĐT — xe ra</div>
                                    </a>
                                </li>
                            </ul>
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

    <script>
        (function() {
            var kicking = false;
            var refreshingCsrf = false;
            var authFailStreak = 0;
            var loginUrl = @json(route('login'));
            var csrfUrl = @json(route('csrf.token'));
            var statusUrl = @json(route('account.status'));
            var defaultLogoutMsg = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
            var originalFetch = window.fetch;

            function applyCsrfToken(token) {
                if (!token) return;
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', token);
                var input = document.querySelector('#logout-form input[name="_token"]');
                if (input) input.value = token;
                if (window.jQuery) {
                    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token } });
                }
            }

            function refreshCsrfToken() {
                var fetchFn = typeof originalFetch === 'function' ? originalFetch : window.fetch;
                return fetchFn.call(window, csrfUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-store'
                }).then(function(res) {
                    return res.json();
                }).then(function(data) {
                    applyCsrfToken(data && data.token);
                    return data && data.token;
                });
            }

            window.ndbsLogout = function() {
                var form = document.getElementById('logout-form');
                if (!form) {
                    window.location.href = loginUrl;
                    return;
                }
                refreshCsrfToken()
                    .catch(function() { return null; })
                    .then(function() {
                        form.submit();
                    });
            };

            function showForceLogoutOverlay(message) {
                if (kicking) return;
                kicking = true;

                var msg = message || defaultLogoutMsg;

                var existing = document.getElementById('force-logout-overlay');
                if (existing) existing.remove();

                var overlay = document.createElement('div');
                overlay.id = 'force-logout-overlay';
                overlay.setAttribute('role', 'alert');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.55);padding:16px;';
                overlay.innerHTML =
                    '<div style="max-width:420px;width:100%;background:#fff;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.25);padding:24px 20px;text-align:center;">' +
                        '<div class="text-danger mb-2"><i class="material-icons-outlined" style="font-size:48px;">gpp_bad</i></div>' +
                        '<div style="font-weight:700;font-size:1.05rem;margin-bottom:8px;">Thông báo</div>' +
                        '<div style="color:#475569;margin-bottom:12px;">' + msg + '</div>' +
                        '<div style="color:#94a3b8;font-size:.9rem;">Đang chuyển về trang đăng nhập...</div>' +
                    '</div>';
                document.body.appendChild(overlay);

                // Đánh dấu đã hiện overlay → trang login không hiện lại cùng nội dung
                try {
                    sessionStorage.setItem('force_logout_shown', '1');
                    sessionStorage.removeItem('force_logout_message');
                } catch (e) {}

                setTimeout(function() {
                    window.location.href = loginUrl;
                }, 1600);
            }

            function handleForceLogoutPayload(data) {
                if (data && data.force_logout) {
                    showForceLogoutOverlay(data.message || defaultLogoutMsg);
                    return true;
                }
                return false;
            }

            // 419 = CSRF cũ: chỉ làm mới token, không đá phiên (tránh spam overlay)
            function handleCsrfMismatch(rawBody) {
                if (kicking) return;
                var data = null;
                if (rawBody && typeof rawBody === 'object') {
                    data = rawBody;
                } else if (typeof rawBody === 'string' && rawBody) {
                    try { data = JSON.parse(rawBody); } catch (e) {}
                }
                if (handleForceLogoutPayload(data)) return;
                if (refreshingCsrf) return;
                refreshingCsrf = true;
                refreshCsrfToken()
                    .catch(function() {})
                    .finally(function() { refreshingCsrf = false; });
            }

            if (window.jQuery) {
                $(document).ajaxError(function(event, jqxhr) {
                    if (!jqxhr || kicking) return;
                    if (jqxhr.status === 419) {
                        handleCsrfMismatch(jqxhr.responseJSON || jqxhr.responseText);
                        return;
                    }
                    if (jqxhr.status !== 403) return;
                    try {
                        handleForceLogoutPayload(jqxhr.responseJSON || JSON.parse(jqxhr.responseText || '{}'));
                    } catch (e) {}
                });
            }

            if (typeof originalFetch === 'function') {
                window.fetch = function() {
                    return originalFetch.apply(this, arguments).then(function(response) {
                        if (!response || kicking) return response;
                        if (response.status === 419) {
                            response.clone().json().then(function(data) {
                                handleCsrfMismatch(data);
                            }).catch(function() {
                                handleCsrfMismatch(null);
                            });
                        } else if (response.status === 403) {
                            response.clone().json().then(function(data) {
                                handleForceLogoutPayload(data);
                            }).catch(function() {});
                        }
                        return response;
                    });
                };
            }

            setInterval(function() {
                if (kicking || typeof originalFetch !== 'function') return;
                originalFetch.call(window, statusUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store'
                }).then(function(response) {
                    if (!response || kicking) return;
                    if (response.ok) {
                        authFailStreak = 0;
                        return;
                    }
                    if (response.status === 401) {
                        authFailStreak += 1;
                        // 2 lần liên tiếp (~10s) mới báo — tránh nháy mạng
                        if (authFailStreak >= 2) {
                            showForceLogoutOverlay(defaultLogoutMsg);
                        }
                        return;
                    }
                    authFailStreak = 0;
                    if (response.status === 403) {
                        return response.json().then(function(data) {
                            handleForceLogoutPayload(data);
                        });
                    }
                    if (response.status === 419) {
                        handleCsrfMismatch(null);
                    }
                }).catch(function() {});
            }, 5000);
        })();
    </script>

</body>

</html>
