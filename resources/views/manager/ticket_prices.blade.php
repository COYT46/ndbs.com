@extends('layouts.app')

@section('title', 'Cài đặt giá vé')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-7 col-xl-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-1">
                    <i class="material-icons-outlined align-middle me-1">payments</i>
                    Cài đặt giá vé
                </h5>
                <p class="text-muted mb-4">Giá vé ngày tính theo giờ (làm tròn lên). Giá vé tháng tính theo 30 ngày.</p>

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show auto-dismiss-alert" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form action="{{ route('manager.ticket_prices.save') }}" method="POST" id="ticket-price-form">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Giá vé ngày (VNĐ / 1 giờ)</label>
                        <input type="number" min="1" step="1" class="form-control form-control-lg"
                            name="daily_price_per_hour" id="daily_price_per_hour"
                            value="{{ old('daily_price_per_hour', $dailyPrice) }}" required>
                        <small class="text-muted">Mặc định 1.000 VNĐ / giờ. Không được để trống hoặc ≤ 0.</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Giá vé tháng (VNĐ / 1 tháng)</label>
                        <input type="number" min="1" step="1" class="form-control form-control-lg"
                            name="monthly_price_per_month" id="monthly_price_per_month"
                            value="{{ old('monthly_price_per_month', $monthlyPrice) }}" required>
                        <small class="text-muted">Mặc định 100.000 VNĐ / tháng (30 ngày). Không được để trống hoặc ≤ 0.</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                        <i class="material-icons-outlined align-middle me-1">save</i> Lưu
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
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

        $('#ticket-price-form').on('submit', function(e) {
            const daily = Number($('#daily_price_per_hour').val());
            const monthly = Number($('#monthly_price_per_month').val());
            if (!$('#daily_price_per_hour').val() || !$('#monthly_price_per_month').val() || daily <= 0 || monthly <= 0) {
                e.preventDefault();
                alert('Giá vé không được để trống và phải lớn hơn 0.');
            }
        });
    });
</script>
@endpush
