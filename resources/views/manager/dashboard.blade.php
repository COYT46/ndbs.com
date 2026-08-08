@extends('layouts.app')

@section('title', 'Quản lý Tài khoản')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0 fw-bold"><i class="material-icons-outlined align-middle me-1">people</i>
                            Danh sách Bảo vệ</h5>
                    </div>
                    <hr>
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show auto-dismiss-alert" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    @if ($errors->any() && !old('_edit_guard_id'))
                        <div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert" role="alert">
                            @foreach ($errors->all() as $error)
                                @if ($error !== '')
                                    <div>{{ $error }}</div>
                                @endif
                            @endforeach
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>STT</th>
                                    <th>Họ và tên</th>
                                    <th>Email</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($guards as $k => $guard)
                                    <tr>
                                        <td>{{ $k + 1 }}</td>
                                        <td class="fw-semibold">{{ $guard->fullname }}</td>
                                        <td>{{ $guard->email }}</td>
                                        <td>
                                            @if (isset($guard->is_active) && !$guard->is_active)
                                                <span class="badge bg-danger">Đã vô hiệu hóa</span>
                                            @else
                                                <span class="badge bg-success">Hoạt động</span>
                                            @endif
                                        </td>
                                        <td>{{ $guard->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <!-- Nút Sửa -->
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary d-flex align-items-center"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editGuardModal{{ $guard->id }}">
                                                    <i class="material-icons-outlined" style="font-size: 20px;">edit</i>
                                                </button>

                                                <!-- Nút Vô hiệu hóa / Kích hoạt -->
                                                <form action="{{ route('manager.guard.toggle_status', $guard->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    @if (isset($guard->is_active) && !$guard->is_active)
                                                        <button type="submit"
                                                            class="btn btn-sm btn-outline-success d-flex align-items-center"
                                                            title="Mở khóa tài khoản">
                                                            <i class="material-icons-outlined"
                                                                style="font-size: 20px;">lock_open</i>
                                                        </button>
                                                    @else
                                                        <button type="submit"
                                                            class="btn btn-sm btn-outline-warning d-flex align-items-center"
                                                            title="Vô hiệu hóa tài khoản"
                                                            onsubmit="return confirm('Vô hiệu hóa tài khoản này?');">
                                                            <i class="material-icons-outlined"
                                                                style="font-size: 20px;">block</i>
                                                        </button>
                                                    @endif
                                                </form>

                                                <!-- Nút Xóa -->
                                                <form action="{{ route('manager.guard.delete', $guard->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-danger d-flex align-items-center"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteGuardModal{{ $guard->id }}">
                                                        <i class="material-icons-outlined"
                                                            style="font-size: 20px;">delete</i>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Modal Sửa tài khoản bảo vệ -->
                                            <div class="modal fade" id="editGuardModal{{ $guard->id }}" tabindex="-1"
                                                aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="{{ route('manager.guard.update', $guard->id) }}"
                                                            method="POST">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="_edit_guard_id" value="{{ $guard->id }}">
                                                            <div class="modal-header bg-primary text-white">
                                                                <h5 class="modal-title text-white">Sửa tài khoản bảo vệ
                                                                    #{{ $guard->id }}</h5>
                                                                <button type="button" class="btn-close btn-close-white"
                                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body text-start">
                                                                @if ($errors->any() && (string) old('_edit_guard_id') === (string) $guard->id)
                                                                    <div class="alert alert-danger py-2 edit-guard-error" role="alert">
                                                                        @foreach ($errors->all() as $error)
                                                                            @if ($error !== '')
                                                                                <div>{{ $error }}</div>
                                                                            @endif
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                                @php
                                                                    $isThisEdit = (string) old('_edit_guard_id') === (string) $guard->id;
                                                                @endphp
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Họ và tên</label>
                                                                    <input type="text" class="form-control"
                                                                        name="fullname"
                                                                        value="{{ $isThisEdit && !$errors->has('fullname') ? old('fullname', $guard->fullname) : $guard->fullname }}"
                                                                        data-original="{{ $guard->fullname }}"
                                                                        required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Email</label>
                                                                    <input type="email" class="form-control"
                                                                        name="email"
                                                                        value="{{ $isThisEdit && !$errors->has('email') ? old('email', $guard->email) : $guard->email }}"
                                                                        data-original="{{ $guard->email }}"
                                                                        required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Mật khẩu mới <small
                                                                            class="text-muted">(Bỏ trống nếu không
                                                                            đổi)</small></label>
                                                                    <input type="password" class="form-control"
                                                                        name="password"
                                                                        placeholder="Nhập mật khẩu mới nếu muốn thay đổi">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Hủy</button>
                                                                <button type="submit" class="btn btn-primary">Lưu thay
                                                                    đổi</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Modal Xác nhận xóa tài khoản bảo vệ -->
                                            <div class="modal fade" id="deleteGuardModal{{ $guard->id }}" tabindex="-1"
                                                aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg">
                                                        <form action="{{ route('manager.guard.delete', $guard->id) }}"
                                                            method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title text-white fw-bold">
                                                                    <i class="material-icons-outlined align-middle me-1">help_outline</i>
                                                                    Xác nhận xóa tài khoản
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white"
                                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body text-center p-4">
                                                                <div class="mb-3">
                                                                    <i class="material-icons-outlined text-danger" style="font-size: 56px;">delete_forever</i>
                                                                </div>
                                                                <h5 class="fw-bold mb-2">Xóa tài khoản bảo vệ {{ $guard->fullname }}?</h5>
                                                                <p class="text-muted mb-0 fs-6">
                                                                    Hành động này sẽ xóa vĩnh viễn tài khoản bảo vệ khỏi hệ thống.
                                                                    Bạn có chắc chắn muốn tiếp tục?
                                                                </p>
                                                            </div>
                                                            <div class="modal-footer justify-content-center bg-light">
                                                                <button type="button" class="btn btn-secondary px-4 fw-bold"
                                                                    data-bs-dismiss="modal">Hủy</button>
                                                                <button type="submit" class="btn btn-danger px-4 fw-bold shadow">
                                                                    Xác nhận xóa
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($guards->isEmpty())
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Chưa có tài khoản bảo vệ nào.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold"><i class="material-icons-outlined align-middle me-1">person_add</i> Thêm
                        Bảo vệ</h5>
                    <hr>
                    <form action="{{ route('manager.guard.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Họ và tên</label>
                            <input type="text" class="form-control" name="fullname"
                                value="{{ old('_edit_guard_id') ? '' : old('fullname') }}"
                                placeholder="Nhập họ tên bảo vệ" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" name="email"
                                value="{{ old('_edit_guard_id') ? '' : old('email') }}"
                                placeholder="email@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mật khẩu</label>
                            <input type="password" class="form-control" name="password" placeholder="Tối thiểu 6 ký tự"
                                required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i
                                class="material-icons-outlined align-middle me-1">add_circle</i> Tạo tài khoản</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    $(document).ready(function() {
        // Tự ẩn thông báo sau 3.5 giây
        setTimeout(function() {
            $('.auto-dismiss-alert').each(function() {
                const el = this;
                if (window.bootstrap && bootstrap.Alert) {
                    bootstrap.Alert.getOrCreateInstance(el).close();
                } else {
                    $(el).fadeOut(400, function() { $(this).remove(); });
                }
            });
        }, 3500);

        // Đóng modal sửa → trả form về giá trị đã lưu trong DB
        document.querySelectorAll('[id^="editGuardModal"]').forEach(function(modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function() {
                const form = modalEl.querySelector('form');
                if (!form) return;
                const fullname = form.querySelector('[name="fullname"]');
                const email = form.querySelector('[name="email"]');
                const password = form.querySelector('[name="password"]');
                if (fullname) fullname.value = fullname.getAttribute('data-original') || '';
                if (email) email.value = email.getAttribute('data-original') || '';
                if (password) password.value = '';
                const err = form.querySelector('.edit-guard-error');
                if (err) err.remove();
            });
        });

        // Lỗi khi sửa → mở lại modal tương ứng
        @if ($errors->any() && old('_edit_guard_id'))
            const editModal = document.getElementById('editGuardModal{{ old('_edit_guard_id') }}');
            if (editModal && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(editModal).show();
            }
        @endif
    });
</script>
@endpush
