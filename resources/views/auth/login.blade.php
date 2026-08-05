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

        $(document).ready(function() {
            var submitting = false;
            $('#login-form').on('submit', function() {
                if (submitting) {
                    return false;
                }
                submitting = true;
                var $btn = $('#login-submit');
                $btn.prop('disabled', true).text('Đang đăng nhập...');
                // Nếu mạng chậm, vẫn chỉ gửi 1 lần
                setTimeout(function() {
                    submitting = false;
                    $btn.prop('disabled', false).text('Đăng nhập');
                }, 8000);
                return true;
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
