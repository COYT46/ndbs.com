<?php

namespace App\Http\Controllers;

use App\Models\MonthlyTicket;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyTicketController extends Controller
{
    private function ensureManager()
    {
        if (auth()->user()->role !== 'manager') {
            return redirect('/');
        }
        return null;
    }

    private function normalizePlate(?string $plate): string
    {
        return strtoupper(trim((string) $plate));
    }

    private function plateTaken(?string $plate, ?int $ignoreId = null): bool
    {
        $clean = preg_replace('/[^A-Z0-9]/i', '', $this->normalizePlate($plate));
        if ($clean === '') {
            return false;
        }

        $q = MonthlyTicket::notDeleted()
            ->where('is_active', true)
            ->whereDate('expires_on', '>=', now()->toDateString());
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->get()->contains(function ($ticket) use ($clean) {
            $other = preg_replace('/[^A-Z0-9]/i', '', (string) $ticket->plate_number);
            return strcasecmp($other, $clean) === 0;
        });
    }

    public function index()
    {
        if ($redirect = $this->ensureManager()) return $redirect;

        $all = MonthlyTicket::notDeleted()->orderByDesc('id')->get();
        $activeTickets = $all->filter(fn ($t) => !$t->isExpired())->values();
        $expiredTickets = $all->filter(fn ($t) => $t->isExpired())->values();
        $nextCode = MonthlyTicket::nextCode();
        $monthlyPrice = Setting::monthlyPricePerMonth();

        return view('manager.monthly_tickets', compact(
            'activeTickets',
            'expiredTickets',
            'nextCode',
            'monthlyPrice'
        ));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->ensureManager()) return $redirect;

        $request->validate([
            'plate_number' => 'required|string|max:20',
            'duration_months' => 'required|integer|in:1,2,3,4',
        ], [
            'plate_number.required' => 'Vui lòng nhập biển số xe.',
            'duration_months.required' => 'Vui lòng chọn thời hạn.',
            'duration_months.in' => 'Thời hạn không hợp lệ.',
        ]);

        $plate = $this->normalizePlate($request->plate_number);
        if ($this->plateTaken($plate)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['plate_number' => 'Biển số này đã có vé tháng còn hạn.']);
        }

        $months = (int) $request->duration_months;
        $startsOn = now()->toDateString();
        $expiresOn = MonthlyTicket::expiryDate($startsOn, $months)->toDateString();
        $price = Setting::monthlyPricePerMonth() * $months;

        MonthlyTicket::create([
            'code' => MonthlyTicket::nextCode(),
            'plate_number' => $plate,
            'duration_months' => $months,
            'price' => $price,
            'starts_on' => $startsOn,
            'expires_on' => $expiresOn,
            'is_active' => true,
            'deleted' => false,
        ]);

        return redirect()->back()->with('success', 'Thêm vé tháng thành công.');
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->ensureManager()) return $redirect;

        $ticket = MonthlyTicket::notDeleted()->findOrFail($id);
        if ($ticket->isExpired()) {
            return redirect()->back()->withErrors(['error' => 'Vé tháng đã hết hạn, hãy dùng Gia hạn.']);
        }

        $allowed = $ticket->allowedDurations();
        $request->validate([
            'plate_number' => 'required|string|max:20',
            'duration_months' => ['required', 'integer', Rule::in($allowed)],
        ], [
            'plate_number.required' => 'Vui lòng nhập biển số xe.',
            'duration_months.required' => 'Vui lòng chọn thời hạn.',
            'duration_months.in' => 'Không thể chọn thời hạn này vì đã dùng quá số ngày tương ứng.',
        ]);

        $plate = $this->normalizePlate($request->plate_number);
        if ($this->plateTaken($plate, $ticket->id)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['plate_number' => 'Biển số này đã có vé tháng còn hạn.'])
                ->with('_edit_ticket_id', $ticket->id);
        }

        $months = (int) $request->duration_months;
        $ticket->plate_number = $plate;
        $ticket->duration_months = $months;
        $ticket->price = Setting::monthlyPricePerMonth() * $months;
        $ticket->expires_on = MonthlyTicket::expiryDate($ticket->starts_on, $months)->toDateString();
        $ticket->save();

        return redirect()->back()->with('success', 'Cập nhật vé tháng thành công.');
    }

    public function toggleStatus($id)
    {
        if ($redirect = $this->ensureManager()) return $redirect;

        $ticket = MonthlyTicket::notDeleted()->findOrFail($id);
        if ($ticket->isExpired()) {
            return redirect()->back()->withErrors(['error' => 'Không thể đổi trạng thái vé đã hết hạn.']);
        }
        $ticket->is_active = !$ticket->is_active;
        $ticket->save();
        $msg = $ticket->is_active ? 'Đã kích hoạt vé tháng.' : 'Đã vô hiệu hóa vé tháng.';
        return redirect()->back()->with('success', $msg);
    }

    public function destroy($id)
    {
        if ($redirect = $this->ensureManager()) return $redirect;

        $ticket = MonthlyTicket::notDeleted()->findOrFail($id);
        $ticket->deleted = 1;
        $ticket->save();
        return redirect()->back()->with('success', 'Đã xóa vé tháng.');
    }

    public function renew(Request $request, $id)
    {
        if ($redirect = $this->ensureManager()) return $redirect;

        $ticket = MonthlyTicket::notDeleted()->findOrFail($id);
        if (!$ticket->isExpired()) {
            return redirect()->back()->withErrors(['error' => 'Vé tháng vẫn còn hạn, không cần gia hạn.']);
        }

        $request->validate([
            'plate_number' => 'required|string|max:20',
            'duration_months' => 'required|integer|in:1,2,3,4',
        ], [
            'plate_number.required' => 'Vui lòng nhập biển số xe.',
            'duration_months.required' => 'Vui lòng chọn thời hạn.',
            'duration_months.in' => 'Thời hạn không hợp lệ.',
        ]);

        $plate = $this->normalizePlate($request->plate_number);
        if ($this->plateTaken($plate, $ticket->id)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['plate_number' => 'Biển số này đã có vé tháng còn hạn.'])
                ->with('_renew_ticket_id', $ticket->id);
        }

        $months = (int) $request->duration_months;
        $startsOn = now()->toDateString();
        $ticket->plate_number = $plate;
        $ticket->duration_months = $months;
        $ticket->price = Setting::monthlyPricePerMonth() * $months;
        $ticket->starts_on = $startsOn;
        $ticket->expires_on = MonthlyTicket::expiryDate($startsOn, $months)->toDateString();
        $ticket->is_active = true;
        $ticket->save();

        return redirect()->back()->with('success', 'Gia hạn vé tháng thành công.');
    }
}
