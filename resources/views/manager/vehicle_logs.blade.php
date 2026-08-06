@extends('layouts.app')

@section('title', 'Lịch sử ra vào phương tiện')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold text-primary"><i class="material-icons-outlined align-middle me-2">history</i> Lịch sử ra vào của phương tiện</h4>
        {{-- <button type="button" class="btn btn-outline-primary fw-bold d-inline-flex align-items-center shadow-sm" onclick="refreshVehicleLogsWithFetch(true)">
            <i class="material-icons-outlined align-middle me-1">sync</i> Làm mới (Fetch)
        </button> --}}
    </div>
    <div class="card-body">
        <!-- Nav tabs -->
        <ul class="nav nav-pills mb-4 gap-2" id="logsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold px-4 py-2" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                    <i class="material-icons-outlined align-middle me-1">login</i> Xe vào chưa ra
                    <span class="badge bg-danger ms-1">{{ $pendingLogs->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold px-4 py-2" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">
                    <i class="material-icons-outlined align-middle me-1">check_circle</i> Xe vào đã ra
                    <span class="badge bg-success ms-1">{{ $completedLogs->count() }}</span>
                </button>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content">
            <!-- Mục Xe vào chưa ra -->
            <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                <div class="table-responsive">
                    <table id="pendingTable" class="table table-bordered table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 30px;">STT</th>
                                <th>Mã Code</th>
                                <th>BSX</th>
                                <th>Thời gian vào</th>
                                <th class="text-center" style="width: 100px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingLogs as $index => $log)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td><span class="badge bg-primary fs-6">{{ $log->code }}</span></td>
                                <td><strong class="text-danger fs-6">{{ $log->plate_number }}</strong></td>
                                <td>{{ \Carbon\Carbon::parse($log->entry_time)->format('d/m/Y H:i:s') }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-info text-white d-inline-flex align-items-center"
                                        data-bs-toggle="modal" data-bs-target="#viewPendingModal{{ $log->id }}" title="Xem chi tiết">
                                        <i class="material-icons-outlined">visibility</i>
                                    </button>

                                    <!-- Modal Chi tiết Xe vào chưa ra -->
                                    <div class="modal fade" id="viewPendingModal{{ $log->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content text-start">
                                                <div class="modal-header bg-info text-white">
                                                    <h5 class="modal-title text-white">Chi tiết phương tiện vào (Mã code: {{ $log->code }})</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-12 col-md-6 text-center">
                                                            <h6 class="fw-bold mb-2">Ảnh Xe Vào</h6>
                                                            <div class="border rounded p-2 bg-light d-flex align-items-center justify-content-center" style="height: 270px;">
                                                                @if($log->entry_image)
                                                                    <img src="{{ asset($log->entry_image) }}" class="img-fluid rounded" style="max-height: 100%;">
                                                                @else
                                                                    <span class="text-muted">Không có ảnh</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 d-flex flex-column justify-content-center">
                                                            <ul class="list-group list-group-flush fs-6">
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Mã code:</span> <strong class="text-primary">{{ $log->code }}</strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>BSX vào:</span> <strong class="text-danger fs-5">{{ $log->plate_number }}</strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Thời gian vào:</span> <span>{{ \Carbon\Carbon::parse($log->entry_time)->format('d/m/Y H:i:s') }}</span>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Bảo vệ check-in:</span> <strong class="text-dark">{{ $log->guardIn->fullname ?? 'N/A' }}</strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Trạng thái:</span>
                                                                    @if($log->is_valid === 0 || $log->is_valid === false)
                                                                        <span class="badge bg-danger">Không hợp lệ</span>
                                                                    @else
                                                                        <span class="badge bg-warning text-dark">Chưa ra</span>
                                                                    @endif
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mục Xe vào đã ra -->
            <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                <div class="table-responsive">
                    <table id="completedTable" class="table table-bordered table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 30px;">STT</th>
                                <th>Mã Code</th>
                                <th>BSX vào</th>
                                <th>Thời gian vào</th>
                                <th>BSX ra</th>
                                <th>Thời gian ra</th>
                                <th class="text-center" style="width: 100px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($completedLogs as $index => $log)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td><span class="badge bg-primary fs-6">{{ $log->code }}</span></td>
                                <td><strong class="text-danger">{{ $log->plate_number }}</strong></td>
                                <td>{{ \Carbon\Carbon::parse($log->entry_time)->format('d/m/Y H:i:s') }}</td>
                                <td><strong class="text-success">{{ $log->exit_plate_number ?? $log->plate_number }}</strong></td>
                                <td>{{ $log->exit_time ? \Carbon\Carbon::parse($log->exit_time)->format('d/m/Y H:i:s') : '-' }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-info text-white d-inline-flex align-items-center"
                                        data-bs-toggle="modal" data-bs-target="#viewCompletedModal{{ $log->id }}" title="Xem chi tiết">
                                        <i class="material-icons-outlined">visibility</i>
                                    </button>

                                    <!-- Modal Chi tiết Xe vào đã ra -->
                                    <div class="modal fade" id="viewCompletedModal{{ $log->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-xl">
                                            <div class="modal-content text-start">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title text-white">Chi tiết phương tiện đã ra (Mã code: {{ $log->code }})</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row g-4">
                                                        <!-- Bên Xe Vào -->
                                                        <div class="col-12 col-md-6 border-end">
                                                            <h5 class="text-primary fw-bold mb-3"><i class="material-icons-outlined align-middle">login</i> Thông Tin Check-In (Vào)</h5>
                                                            <div class="border rounded p-2 bg-light text-center mb-3 d-flex align-items-center justify-content-center" style="height: 270px;">
                                                                @if($log->entry_image)
                                                                    <img src="{{ asset($log->entry_image) }}" class="img-fluid rounded" style="max-height: 100%;">
                                                                @else
                                                                    <span class="text-muted">Không có ảnh vào</span>
                                                                @endif
                                                            </div>
                                                            <ul class="list-group list-group-flush fs-6">
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Mã code:</span> <strong class="text-primary">{{ $log->code }}</strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>BSX vào:</span> <strong class="text-danger fs-5">{{ $log->plate_number }}</strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Thời gian vào:</span> <span>{{ \Carbon\Carbon::parse($log->entry_time)->format('d/m/Y H:i:s') }}</span>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Bảo vệ check-in:</span> <strong class="text-dark">{{ $log->guardIn->fullname ?? 'N/A' }}</strong>
                                                                </li>
                                                            </ul>
                                                        </div>

                                                        <!-- Bên Xe Ra -->
                                                        <div class="col-12 col-md-6">
                                                            <h5 class="text-success fw-bold mb-3"><i class="material-icons-outlined align-middle">logout</i> Thông Tin Check-Out (Ra)</h5>
                                                            <div class="border rounded p-2 bg-light text-center mb-3 d-flex align-items-center justify-content-center" style="height: 270px;">
                                                                @if($log->exit_image)
                                                                    <img src="{{ asset($log->exit_image) }}" class="img-fluid rounded" style="max-height: 100%;">
                                                                @else
                                                                    <span class="text-muted">Không có ảnh ra</span>
                                                                @endif
                                                            </div>
                                                            <ul class="list-group list-group-flush fs-6">
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Xác nhận:</span> <span class="badge bg-success">Hợp lệ</span>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>BSX ra:</span> <strong class="text-success fs-5">{{ $log->exit_plate_number ?? $log->plate_number }}</strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Thời gian ra:</span> <span>{{ $log->exit_time ? \Carbon\Carbon::parse($log->exit_time)->format('d/m/Y H:i:s') : '-' }}</span>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between">
                                                                    <span>Bảo vệ check-out:</span> <strong class="text-dark">{{ $log->guardOut->fullname ?? 'N/A' }}</strong>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="dynamic-modals-container"></div>
@endsection

@push('css')
    <link href="{{ asset('public/admin/assets/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
@endpush

@push('js')
    <script src="{{ asset('public/admin/assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('public/admin/assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    <script>
        const dtLanguageVi = {
            "sProcessing":   "Đang xử lý...",
            "sLengthMenu":   "Xem _MENU_ mục",
            "sZeroRecords":  "Không tìm thấy dòng nào phù hợp",
            "sInfo":         "Đang xem _START_ đến _END_ trong tổng số _TOTAL_ mục",
            "sInfoEmpty":    "Đang xem 0 đến 0 trong tổng số 0 mục",
            "sInfoFiltered": "(được lọc từ _MAX_ mục)",
            "sSearch":       "Tìm kiếm:",
            "oPaginate": {
                "sFirst":    "Đầu",
                "sPrevious": "Trước",
                "sNext":     "Tiếp",
                "sLast":     "Cuối"
            }
        };

        function initDataTables() {
            if ($.fn.DataTable.isDataTable('#pendingTable')) $('#pendingTable').DataTable().destroy();
            $('#pendingTable').DataTable({ "language": dtLanguageVi, "autoWidth": false });

            if ($.fn.DataTable.isDataTable('#completedTable')) $('#completedTable').DataTable().destroy();
            $('#completedTable').DataTable({ "language": dtLanguageVi, "autoWidth": false });
        }

        function refreshVehicleLogsWithFetch(force = false) {
            // Nếu đang mở bất kỳ Modal nào trên màn hình thì tạm dừng fetch làm mới DOM để không bị mất Modal
            if ($('.modal.show').length > 0) return;

            // Nếu người dùng đang đặt con trỏ chuột trong ô tìm kiếm (đang gõ dở) thì tạm dừng refresh ngầm
            if (!force && $('input[type="search"]').is(':focus')) return;

            fetch('{{ route("api.recent_logs") }}')
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        let pendingSearch = $.fn.DataTable.isDataTable('#pendingTable') ? $('#pendingTable').DataTable().search() : '';
                        let completedSearch = $.fn.DataTable.isDataTable('#completedTable') ? $('#completedTable').DataTable().search() : '';

                        if ($.fn.DataTable.isDataTable('#pendingTable')) $('#pendingTable').DataTable().destroy();
                        if ($.fn.DataTable.isDataTable('#completedTable')) $('#completedTable').DataTable().destroy();

                        let pendingHtml = '';
                        let modalsHtml = '';
                        res.pending.forEach((log, idx) => {
                            let imgTag = log.entry_image ? `<img src="${log.entry_image}" class="img-fluid rounded" style="max-height: 100%;">` : `<span class="text-muted">Không có ảnh</span>`;
                            let badgeStatus = (log.is_valid === 0 || log.is_valid === false) ? `<span class="badge bg-danger">Không hợp lệ</span>` : `<span class="badge bg-warning text-dark">Chưa ra</span>`;
                            pendingHtml += `<tr>
                                <td class="text-center">${idx + 1}</td>
                                <td><span class="badge bg-primary fs-6">${log.code}</span></td>
                                <td><strong class="text-danger fs-6">${log.plate_number}</strong></td>
                                <td>${log.entry_time}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-info text-white d-inline-flex align-items-center"
                                        data-bs-toggle="modal" data-bs-target="#viewPendingModal${log.id}" title="Xem chi tiết">
                                        <i class="material-icons-outlined">visibility</i>
                                    </button>
                                </td>
                            </tr>`;

                            modalsHtml += `<div class="modal fade" id="viewPendingModal${log.id}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content text-start">
                                        <div class="modal-header bg-info text-white">
                                            <h5 class="modal-title text-white">Chi tiết phương tiện vào (Mã code: ${log.code})</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-12 col-md-6 text-center">
                                                    <h6 class="fw-bold mb-2">Ảnh Xe Vào</h6>
                                                    <div class="border rounded p-2 bg-light d-flex align-items-center justify-content-center" style="height: 270px;">
                                                        ${imgTag}
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6 d-flex flex-column justify-content-center">
                                                    <ul class="list-group list-group-flush fs-6">
                                                        <li class="list-group-item d-flex justify-content-between"><span>Mã code:</span> <strong class="text-primary">${log.code}</strong></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>BSX vào:</span> <strong class="text-danger fs-5">${log.plate_number}</strong></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>Thời gian vào:</span> <span>${log.entry_time}</span></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>Bảo vệ check-in:</span> <strong class="text-dark">${log.guard_in}</strong></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>Trạng thái:</span> ${badgeStatus}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
                                    </div>
                                </div>
                            </div>`;
                        });
                        $('#pendingTable tbody').html(pendingHtml);
                        $('#dynamic-modals-container').html(modalsHtml);

                        let completedHtml = '';
                        res.completed.forEach((log, idx) => {
                            let entryImgTag = log.entry_image ? `<img src="${log.entry_image}" class="img-fluid rounded" style="max-height: 100%;">` : `<span class="text-muted">Không có ảnh vào</span>`;
                            let exitImgTag = log.exit_image ? `<img src="${log.exit_image}" class="img-fluid rounded" style="max-height: 100%;">` : `<span class="text-muted">Không có ảnh ra</span>`;

                            completedHtml += `<tr>
                                <td class="text-center">${idx + 1}</td>
                                <td><span class="badge bg-primary fs-6">${log.code}</span></td>
                                <td><strong class="text-danger">${log.plate_number}</strong></td>
                                <td>${log.entry_time}</td>
                                <td><strong class="text-success">${log.exit_plate_number || log.plate_number}</strong></td>
                                <td>${log.exit_time || '-'}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-info text-white d-inline-flex align-items-center"
                                        data-bs-toggle="modal" data-bs-target="#viewCompletedModal${log.id}" title="Xem chi tiết">
                                        <i class="material-icons-outlined">visibility</i>
                                    </button>
                                </td>
                            </tr>`;

                            modalsHtml += `<div class="modal fade" id="viewCompletedModal${log.id}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content text-start">
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title text-white">Chi tiết phương tiện đã ra (Mã code: ${log.code})</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-4">
                                                <div class="col-12 col-md-6 border-end">
                                                    <h5 class="text-primary fw-bold mb-3"><i class="material-icons-outlined align-middle">login</i> Thông Tin Check-In (Vào)</h5>
                                                    <div class="border rounded p-2 bg-light text-center mb-3 d-flex align-items-center justify-content-center" style="height: 270px;">
                                                        ${entryImgTag}
                                                    </div>
                                                    <ul class="list-group list-group-flush fs-6">
                                                        <li class="list-group-item d-flex justify-content-between"><span>Mã code:</span> <strong class="text-primary">${log.code}</strong></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>BSX vào:</span> <strong class="text-danger fs-5">${log.plate_number}</strong></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>Thời gian vào:</span> <span>${log.entry_time}</span></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>Bảo vệ check-in:</span> <strong class="text-dark">${log.guard_in}</strong></li>
                                                    </ul>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <h5 class="text-success fw-bold mb-3"><i class="material-icons-outlined align-middle">logout</i> Thông Tin Check-Out (Ra)</h5>
                                                    <div class="border rounded p-2 bg-light text-center mb-3 d-flex align-items-center justify-content-center" style="height: 270px;">
                                                        ${exitImgTag}
                                                    </div>
                                                    <ul class="list-group list-group-flush fs-6">
                                                        <li class="list-group-item d-flex justify-content-between"><span>Xác nhận:</span> <span class="badge bg-success">Hợp lệ</span></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>BSX ra:</span> <strong class="text-success fs-5">${log.exit_plate_number || log.plate_number}</strong></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>Thời gian ra:</span> <span>${log.exit_time || '-'}</span></li>
                                                        <li class="list-group-item d-flex justify-content-between"><span>Bảo vệ check-out:</span> <strong class="text-dark">${log.guard_out}</strong></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
                                    </div>
                                </div>
                            </div>`;
                        });
                        $('#completedTable tbody').html(completedHtml);
                        $('#dynamic-modals-container').html(modalsHtml);

                        initDataTables();

                        // Khôi phục lại từ khóa tìm kiếm nếu có trước khi refresh
                        if (pendingSearch) $('#pendingTable').DataTable().search(pendingSearch).draw(false);
                        if (completedSearch) $('#completedTable').DataTable().search(completedSearch).draw(false);
                    }
                })
                .catch(err => console.error('Lỗi khi fetch dữ liệu lịch sử xe:', err));
        }

        $(document).ready(function() {
            initDataTables();
            // Tự động fetch làm mới sau mỗi 5 giây
            setInterval(refreshVehicleLogsWithFetch, 5000);

            // Khi chuyển tab sang bảng xe đã ra, căn chỉnh lại độ rộng cột DataTables
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });
        });
    </script>
@endpush
