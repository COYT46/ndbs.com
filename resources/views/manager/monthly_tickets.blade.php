@extends('layouts.app')

@section('title', 'Quản lý vé tháng')

@section('content')
@php
    $monthlyPrice = (int) $monthlyPrice;
@endphp
<div class="row">
    <div class="col-12 col-lg-8">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show auto-dismiss-alert" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if ($errors->any() && !old('_edit_ticket_id') && !old('_renew_ticket_id'))
            <div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert" role="alert">
                @foreach ($errors->all() as $error)
                    @if ($error !== '')
                        <div>{{ $error }}</div>
                    @endif
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3 fw-bold">
                    <i class="material-icons-outlined align-middle me-1">verified</i> Vé tháng còn hạn
                </h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>STT</th>
                                <th>Mã code</th>
                                <th>BSX</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activeTickets as $k => $ticket)
                                @php $allowed = $ticket->allowedDurations(); @endphp
                                <tr>
                                    <td>{{ $k + 1 }}</td>
                                    <td><span class="badge bg-primary fs-6">{{ $ticket->code }}</span></td>
                                    <td class="fw-semibold">{{ $ticket->plate_number }}</td>
                                    <td>{{ $ticket->created_at->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-info d-flex align-items-center"
                                                data-bs-toggle="modal" data-bs-target="#viewTicketModal{{ $ticket->id }}" title="Xem">
                                                <i class="material-icons-outlined" style="font-size: 20px;">visibility</i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center"
                                                data-bs-toggle="modal" data-bs-target="#editTicketModal{{ $ticket->id }}" title="Sửa">
                                                <i class="material-icons-outlined" style="font-size: 20px;">edit</i>
                                            </button>
                                            <form action="{{ route('manager.monthly_tickets.toggle_status', $ticket->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                @if (!$ticket->is_active)
                                                    <button type="submit" class="btn btn-sm btn-outline-success d-flex align-items-center" title="Kích hoạt">
                                                        <i class="material-icons-outlined" style="font-size: 20px;">lock_open</i>
                                                    </button>
                                                @else
                                                    <button type="submit" class="btn btn-sm btn-outline-warning d-flex align-items-center" title="Vô hiệu hóa">
                                                        <i class="material-icons-outlined" style="font-size: 20px;">block</i>
                                                    </button>
                                                @endif
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center"
                                                data-bs-toggle="modal" data-bs-target="#deleteTicketModal{{ $ticket->id }}" title="Xóa">
                                                <i class="material-icons-outlined" style="font-size: 20px;">delete</i>
                                            </button>
                                        </div>

                                        <div class="modal fade" id="viewTicketModal{{ $ticket->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-info text-white">
                                                        <h5 class="modal-title text-white">Chi tiết vé tháng {{ $ticket->code }}</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <ul class="list-group list-group-flush">
                                                            <li class="list-group-item d-flex justify-content-between"><span>Mã code</span><strong>{{ $ticket->code }}</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>BSX</span><strong class="text-danger">{{ $ticket->plate_number }}</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Thời hạn</span><strong>{{ $ticket->duration_months }} tháng</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Giá tiền</span><strong>{{ format_vnd($ticket->price) }}</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Ngày tạo</span><span>{{ $ticket->created_at->format('d/m/Y') }}</span></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Ngày hết hạn</span><strong>{{ $ticket->expires_on->format('d/m/Y') }}</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between">
                                                                <span>Trạng thái</span>
                                                                @if (!$ticket->is_active)
                                                                    <span class="badge bg-danger">Đã vô hiệu hóa</span>
                                                                @else
                                                                    <span class="badge bg-success">Còn hạn</span>
                                                                @endif
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal fade edit-ticket-modal" id="editTicketModal{{ $ticket->id }}" tabindex="-1" aria-hidden="true"
                                            data-start="{{ $ticket->starts_on->format('Y-m-d') }}">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('manager.monthly_tickets.update', $ticket->id) }}" method="POST">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="_edit_ticket_id" value="{{ $ticket->id }}">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title text-white">Sửa vé tháng {{ $ticket->code }}</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            @if ($errors->any() && (string) old('_edit_ticket_id') === (string) $ticket->id)
                                                                <div class="alert alert-danger py-2 edit-ticket-error">
                                                                    @foreach ($errors->all() as $error)
                                                                        @if ($error !== '') <div>{{ $error }}</div> @endif
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Mã vé tháng</label>
                                                                <input type="text" class="form-control" value="{{ $ticket->code }}" disabled>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">BSX</label>
                                                                <input type="text" class="form-control edit-plate" name="plate_number"
                                                                    value="{{ $ticket->plate_number }}" data-original="{{ $ticket->plate_number }}" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Thời hạn</label>
                                                                <select class="form-select edit-duration" name="duration_months" data-original="{{ $ticket->duration_months }}">
                                                                    @foreach ([1,2,3,4] as $m)
                                                                        <option value="{{ $m }}" {{ (int) $ticket->duration_months === $m ? 'selected' : '' }}
                                                                            {{ !in_array($m, $allowed, true) ? 'disabled' : '' }}>
                                                                            {{ $m }} tháng
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="mb-2 fw-semibold">Giá tiền: <span class="edit-price text-primary">{{ format_vnd($ticket->price) }}</span></div>
                                                            <div class="text-muted">Ngày hết hạn: <span class="edit-expiry">{{ $ticket->expires_on->format('d/m/Y') }}</span></div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal fade" id="deleteTicketModal{{ $ticket->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg">
                                                    <form action="{{ route('manager.monthly_tickets.delete', $ticket->id) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title text-white fw-bold">Xác nhận xóa vé tháng</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-center p-4">
                                                            <i class="material-icons-outlined text-danger" style="font-size: 56px;">delete_forever</i>
                                                            <h5 class="fw-bold mt-2">Xóa vé tháng {{ $ticket->code }}?</h5>
                                                            <p class="text-muted mb-0">Vé sẽ bị xóa mềm khỏi danh sách.</p>
                                                        </div>
                                                        <div class="modal-footer justify-content-center bg-light">
                                                            <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" class="btn btn-danger px-4 fw-bold">Xác nhận xóa</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @if ($activeTickets->isEmpty())
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Chưa có vé tháng còn hạn.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3 fw-bold">
                    <i class="material-icons-outlined align-middle me-1">event_busy</i> Vé tháng hết hạn
                </h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>STT</th>
                                <th>Mã code</th>
                                <th>BSX</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($expiredTickets as $k => $ticket)
                                <tr>
                                    <td>{{ $k + 1 }}</td>
                                    <td><span class="badge bg-secondary fs-6">{{ $ticket->code }}</span></td>
                                    <td class="fw-semibold">{{ $ticket->plate_number }}</td>
                                    <td>{{ $ticket->created_at->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-info d-flex align-items-center"
                                                data-bs-toggle="modal" data-bs-target="#viewExpiredModal{{ $ticket->id }}" title="Xem">
                                                <i class="material-icons-outlined" style="font-size: 20px;">visibility</i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success d-flex align-items-center"
                                                data-bs-toggle="modal" data-bs-target="#renewTicketModal{{ $ticket->id }}" title="Gia hạn">
                                                <i class="material-icons-outlined" style="font-size: 20px;">autorenew</i>
                                            </button>
                                        </div>

                                        <div class="modal fade" id="viewExpiredModal{{ $ticket->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-secondary text-white">
                                                        <h5 class="modal-title text-white">Chi tiết vé tháng {{ $ticket->code }}</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <ul class="list-group list-group-flush">
                                                            <li class="list-group-item d-flex justify-content-between"><span>Mã code</span><strong>{{ $ticket->code }}</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>BSX</span><strong class="text-danger">{{ $ticket->plate_number }}</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Thời hạn</span><strong>{{ $ticket->duration_months }} tháng</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Giá tiền</span><strong>{{ format_vnd($ticket->price) }}</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Ngày tạo</span><span>{{ $ticket->created_at->format('d/m/Y') }}</span></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Ngày hết hạn</span><strong>{{ $ticket->expires_on->format('d/m/Y') }}</strong></li>
                                                            <li class="list-group-item d-flex justify-content-between"><span>Trạng thái</span><span class="badge bg-danger">Hết hạn</span></li>
                                                        </ul>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal fade renew-ticket-modal" id="renewTicketModal{{ $ticket->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('manager.monthly_tickets.renew', $ticket->id) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="_renew_ticket_id" value="{{ $ticket->id }}">
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title text-white">Gia hạn vé tháng {{ $ticket->code }}</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            @if ($errors->any() && (string) old('_renew_ticket_id') === (string) $ticket->id)
                                                                <div class="alert alert-danger py-2">
                                                                    @foreach ($errors->all() as $error)
                                                                        @if ($error !== '') <div>{{ $error }}</div> @endif
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Mã vé tháng</label>
                                                                <input type="text" class="form-control" value="{{ $ticket->code }}" disabled>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">BSX</label>
                                                                <input type="text" class="form-control" name="plate_number" value="{{ old('_renew_ticket_id') == $ticket->id ? old('plate_number', $ticket->plate_number) : $ticket->plate_number }}" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Thời hạn</label>
                                                                <select class="form-select renew-duration" name="duration_months">
                                                                    @foreach ([1,2,3,4] as $m)
                                                                        <option value="{{ $m }}" {{ $m === 1 ? 'selected' : '' }}>{{ $m }} tháng</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="mb-2 fw-semibold">Giá tiền: <span class="renew-price text-primary">{{ format_vnd($monthlyPrice) }}</span></div>
                                                            <div class="text-muted">Ngày hết hạn: <span class="renew-expiry"></span></div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" class="btn btn-success">Gia hạn</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @if ($expiredTickets->isEmpty())
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Không có vé tháng hết hạn.</td>
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
                <h5 class="card-title fw-bold">
                    <i class="material-icons-outlined align-middle me-1">add_card</i> Thêm vé tháng
                </h5>
                <hr>
                <form action="{{ route('manager.monthly_tickets.store') }}" method="POST" id="add-monthly-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mã vé tháng</label>
                        <input type="text" class="form-control" value="{{ $nextCode }}" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">BSX</label>
                        <input type="text" class="form-control text-uppercase" name="plate_number"
                            value="{{ old('_edit_ticket_id') || old('_renew_ticket_id') ? '' : old('plate_number') }}"
                            placeholder="12A-34567" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Thời hạn</label>
                        <select class="form-select" name="duration_months" id="add-duration">
                            @foreach ([1,2,3,4] as $m)
                                <option value="{{ $m }}" {{ (int) old('duration_months', 1) === $m ? 'selected' : '' }}>{{ $m }} tháng</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2 fw-semibold">Giá tiền: <span id="add-price" class="text-primary">{{ format_vnd($monthlyPrice) }}</span></div>
                    <div class="text-muted mb-3">Ngày hết hạn: <span id="add-expiry"></span></div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                        <i class="material-icons-outlined align-middle me-1">add_circle</i> Thêm vé tháng
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    const MONTHLY_PRICE = {{ (int) $monthlyPrice }};

    function addDaysYmd(ymd, days) {
        const p = (ymd || '').split('-');
        const d = p.length === 3 ? new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2])) : new Date();
        d.setHours(0, 0, 0, 0);
        d.setDate(d.getDate() + Number(days));
        return d;
    }
    function formatVi(d) {
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        return dd + '/' + mm + '/' + d.getFullYear();
    }
    function formatVnd(n) {
        return Number(n).toLocaleString('vi-VN') + ' VNĐ';
    }
    function todayYmd() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function updatePriceExpiry(monthsEl, priceEl, expiryEl, startYmd) {
        const months = Number($(monthsEl).val() || 1);
        $(priceEl).text(formatVnd(MONTHLY_PRICE * months));
        $(expiryEl).text(formatVi(addDaysYmd(startYmd || todayYmd(), months * 30)));
    }

    $(document).ready(function() {
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

        updatePriceExpiry('#add-duration', '#add-price', '#add-expiry', todayYmd());
        $('#add-duration').on('change', function() {
            updatePriceExpiry('#add-duration', '#add-price', '#add-expiry', todayYmd());
        });

        $('.edit-ticket-modal').each(function() {
            const modal = this;
            const start = modal.getAttribute('data-start');
            const dur = $(modal).find('.edit-duration');
            const update = function() {
                updatePriceExpiry(dur, $(modal).find('.edit-price'), $(modal).find('.edit-expiry'), start);
            };
            dur.on('change', update);
            update();
        });

        $('.renew-ticket-modal').each(function() {
            const modal = this;
            const dur = $(modal).find('.renew-duration');
            const update = function() {
                updatePriceExpiry(dur, $(modal).find('.renew-price'), $(modal).find('.renew-expiry'), todayYmd());
            };
            dur.on('change', update);
            $(modal).on('shown.bs.modal', update);
            update();
        });

        @if ($errors->any() && old('_edit_ticket_id'))
            const editModal = document.getElementById('editTicketModal{{ old('_edit_ticket_id') }}');
            if (editModal && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(editModal).show();
            }
        @endif
        @if ($errors->any() && old('_renew_ticket_id'))
            const renewModal = document.getElementById('renewTicketModal{{ old('_renew_ticket_id') }}');
            if (renewModal && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(renewModal).show();
            }
        @endif
    });
</script>
@endpush
