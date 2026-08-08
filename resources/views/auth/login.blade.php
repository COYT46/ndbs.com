<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title>Đăng nhập - LPR System</title>
    <link href="{{ asset('public/admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('public/admin/sass/main.css') }}" rel="stylesheet">
</head>

<body>

    <div class="section-authentication-cover">
        <div class="row g-0">
            <div
                class="col-12 col-xl-7 col-xxl-8 auth-cover-left align-items-center justify-content-center d-none d-xl-flex border-end bg-transparent">
                <div class="card rounded-0 mb-0 border-0 bg-transparent">
                    <div class="card-body">
                        <img src="{{ asset('public/admin/assets/images/auth/login1.png') }}"
                            class="img-fluid auth-img-cover-login" width="650" alt="">
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5 col-xxl-4 auth-cover-right align-items-center justify-content-center">
                <div class="card rounded-0 m-3 mb-0 border-0 shadow-none">
                    <div class="card-body p-sm-5">
                        <img src="{{ asset('public/admin/assets/images/logo-icon.png') }}" class="mb-4" width="45"
                            alt="">
                        <h4 class="fw-bold">Hệ thống Nhận diện LPR</h4>
                        <p class="mb-0">Vui lòng đăng nhập bằng tài khoản của bạn</p>

                        <div class="form-body mt-4">
                            @if (session('force_logout_message'))
                                <div class="alert alert-warning alert-dismissible fade show auto-dismiss-logout-alert" role="alert">
                                    {{ session('force_logout_message') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            <div id="force-logout-client-alert" class="alert alert-warning alert-dismissible fade show d-none" role="alert">
                                <span class="force-logout-text"></span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    @foreach ($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <form id="login-form" class="row g-3" method="POST" action="{{ url('/login') }}">
                                @csrf
                                <div class="col-12">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="{{ old('email') }}" required autofocus autocomplete="username">
                                </div>
                                <div class="col-12">
                                    <label for="password" class="form-label">Mật khẩu</label>
                                    <div class="input-group" id="show_hide_password">
                                        <input type="password" class="form-control border-end-0" id="password"
                                            name="password" required autocomplete="current-password">
                                        <a href="javascript:;" class="input-group-text bg-transparent"><i
                                                class="bi bi-eye-slash-fill"></i></a>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-grid">
                                        <button type="submit" id="login-submit" class="btn btn-primary">Đăng nhập</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('public/admin/assets/js/jquery.min.js') }}"></script>
    <script>
        // Trang lấy từ bfcache (nút Back) → CSRF cũ → 419: buộc tải lại
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                window.location.reload();
            }
        });

        function applyCsrfToken(token) {
            if (!token) return;
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', token);
            var input = document.querySelector('#login-form input[name="_token"]');
            if (input) input.value = token;
        }

        function refreshCsrfToken() {
            return fetch(@json(route('csrf.token')), {
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

        $(document).ready(function() {
            // Làm mới CSRF ngay khi mở trang login (ĐT hay giữ tab cũ)
            refreshCsrfToken().catch(function() {});

            try {
                var alreadyShown = sessionStorage.getItem('force_logout_shown');
                var clientMsg = sessionStorage.getItem('force_logout_message');
                sessionStorage.removeItem('force_logout_shown');
                sessionStorage.removeItem('force_logout_message');

                // Overlay đã hiện rồi → không báo lại. Đã có flash server → cũng không chồng thêm.
                if (clientMsg && !alreadyShown && !$('.auto-dismiss-logout-alert').length) {
                    var $box = $('#force-logout-client-alert');
                    $box.find('.force-logout-text').text(clientMsg);
                    $box.removeClass('d-none');
                }
            } catch (e) {}

            setTimeout(function() {
                $('.auto-dismiss-logout-alert, #force-logout-client-alert:not(.d-none)').fadeOut(400, function() {
                    $(this).remove();
                });
            }, 4500);

            var submitting = false;
            $('#login-form').on('submit', function(e) {
                if (submitting) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                submitting = true;
                var form = this;
                var $btn = $('#login-submit');
                $btn.prop('disabled', true).text('Đang đăng nhập...');

                refreshCsrfToken()
                    .catch(function() { return null; })
                    .then(function() {
                        HTMLFormElement.prototype.submit.call(form);
                    });

                // Nếu mạng chậm, vẫn chỉ gửi 1 lần
                setTimeout(function() {
                    submitting = false;
                    $btn.prop('disabled', false).text('Đăng nhập');
                }, 8000);
                return false;
            });

            $("#show_hide_password a").on('click', function(event) {
                event.preventDefault();
                if ($('#show_hide_password input').attr("type") == "text") {
                    $('#show_hide_password input').attr('type', 'password');
                    $('#show_hide_password i').addClass("bi-eye-slash-fill");
                    $('#show_hide_password i').removeClass("bi-eye-fill");
                } else if ($('#show_hide_password input').attr("type") == "password") {
                    $('#show_hide_password input').attr('type', 'text');
                    $('#show_hide_password i').removeClass("bi-eye-slash-fill");
                    $('#show_hide_password i').addClass("bi-eye-fill");
                }
            });
        });
    </script>

</body>

</html>
