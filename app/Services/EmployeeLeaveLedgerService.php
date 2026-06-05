<?php

namespace App\Services;

use App\Models\EmployeeLeaveLedger;
use App\Models\EmployeeLeaveMaster;
use App\Models\EmployeeMaster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeLeaveLedgerService
{
    public function creditMonthlyLeaves(?Carbon $monthDate = null, float $creditUnits = EmployeeLeaveLedger::DEFAULT_MONTHLY_CREDIT): int
    {
        $monthDate = ($monthDate ?: now())->copy()->startOfMonth();
        $employeeIds = EmployeeMaster::where('iStatus', 1)
            ->where('isDelete', 0)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $created = 0;

        foreach ($employeeIds as $employeeId) {
            $ledger = $this->creditMonthlyLeave($employeeId, $monthDate, $creditUnits);
            if ($ledger->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public function creditMonthlyLeave(int $employeeId, Carbon $monthDate, float $creditUnits = EmployeeLeaveLedger::DEFAULT_MONTHLY_CREDIT): EmployeeLeaveLedger
    {
        $monthDate = $monthDate->copy()->startOfMonth();
        $reference = $this->monthlyCreditReference((int) $monthDate->year, (int) $monthDate->month);

        return DB::transaction(function () use ($employeeId, $monthDate, $creditUnits, $reference) {
            $existing = EmployeeLeaveLedger::where('employee_id', $employeeId)
                ->where('reference', $reference)
                ->first();

            if ($existing) {
                return $existing;
            }

            $openingBalance = $this->currentBalance($employeeId);
            $closingBalance = $openingBalance + $creditUnits;

            return EmployeeLeaveLedger::create([
                'employee_id' => $employeeId,
                'entry_type' => EmployeeLeaveLedger::TYPE_MONTHLY_CREDIT,
                'leave_month' => (int) $monthDate->month,
                'leave_year' => (int) $monthDate->year,
                'from_date' => $monthDate->toDateString(),
                'to_date' => $monthDate->toDateString(),
                'opening_balance' => $openingBalance,
                'credit_units' => $creditUnits,
                'debit_units' => 0,
                'closing_balance' => $closingBalance,
                'reference' => $reference,
                'description' => 'Monthly default leave credit of ' . number_format($creditUnits, 2, '.', '') . ' units.',
            ]);
        });
    }

    public function debitApprovedLeave(EmployeeLeaveMaster $leave, ?int $approvedBy = null): ?EmployeeLeaveLedger
    {
        if ($leave->status !== 'accepted') {
            return null;
        }

        $leaveDate = Carbon::parse($leave->leave_date)->startOfDay();
        $debitUnits = $this->leaveUnits((string) $leave->leave_type);
        $reference = $this->leaveDebitReference((int) $leave->emp_leave_id);

        return DB::transaction(function () use ($leave, $leaveDate, $debitUnits, $reference, $approvedBy) {
            $existing = EmployeeLeaveLedger::where('employee_id', (int) $leave->employee_id)
                ->where('reference', $reference)
                ->first();

            if ($existing) {
                return $existing;
            }

            $openingBalance = $this->currentBalance((int) $leave->employee_id);
            $closingBalance = $openingBalance - $debitUnits;

            return EmployeeLeaveLedger::create([
                'employee_id' => (int) $leave->employee_id,
                'emp_leave_id' => (int) $leave->emp_leave_id,
                'entry_type' => EmployeeLeaveLedger::TYPE_LEAVE_DEBIT,
                'leave_month' => (int) $leaveDate->month,
                'leave_year' => (int) $leaveDate->year,
                'from_date' => $leaveDate->toDateString(),
                'to_date' => $leaveDate->toDateString(),
                'opening_balance' => $openingBalance,
                'credit_units' => 0,
                'debit_units' => $debitUnits,
                'closing_balance' => $closingBalance,
                'reference' => $reference,
                'description' => 'Leave debit for approved ' . ((string) $leave->leave_type === 'H' ? 'half-day' : 'full-day') . ' leave.',
                'created_by' => $approvedBy,
            ]);
        });
    }


    public function syncApprovedLeaveDebitsForPeriod(array $employeeIds, Carbon $startDate, Carbon $endDate): int
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if (empty($employeeIds)) {
            return 0;
        }

        $created = 0;
        $leaves = EmployeeLeaveMaster::whereIn('employee_id', $employeeIds)
            ->where('status', 'accepted')
            ->where('iStatus', 1)
            ->where('isDelete', 0)
            ->whereDate('leave_date', '>=', $startDate->toDateString())
            ->whereDate('leave_date', '<=', $endDate->toDateString())
            ->orderBy('leave_date')
            ->orderBy('emp_leave_id')
            ->get();

        foreach ($leaves as $leave) {
            $ledger = $this->debitApprovedLeave($leave, (int) ($leave->approved_by ?? 0) ?: null);
            if ($ledger && $ledger->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }


   public function manualAdjustment(
    int $employeeId,
    string $adjustmentType,
    float $leaveUnits,
    $description = null,
    $createdBy = null,
    $fromDate = null,
    $toDate = null,
    $extraDates = null
    ): EmployeeLeaveLedger {

        if ($description instanceof Carbon) {
            $legacyTransactionDate = $description;
            $legacyDescription = is_string($createdBy) ? $createdBy : null;
            $legacyCreatedBy = is_numeric($fromDate) ? (int) $fromDate : null;

            $legacyFromDate = $toDate instanceof Carbon
                ? $toDate
                : $legacyTransactionDate;

            $legacyToDate = $legacyFromDate;

            if (is_array($extraDates) && isset($extraDates[0]) && $extraDates[0] instanceof Carbon) {
                $legacyToDate = $extraDates[0];
            } elseif ($extraDates instanceof Carbon) {
                $legacyToDate = $extraDates;
            }

            $description = $legacyDescription;
            $createdBy = $legacyCreatedBy;
            $fromDate = $legacyFromDate;
            $toDate = $legacyToDate;
        } else {
            $description = is_string($description) ? $description : null;
            $createdBy = is_numeric($createdBy) ? (int) $createdBy : null;
            $fromDate = $fromDate instanceof Carbon ? $fromDate : null;
            $toDate = $toDate instanceof Carbon ? $toDate : null;
        }

        $fromDate = ($fromDate ?: now())->copy()->startOfDay();
        $toDate = ($toDate ?: $fromDate)->copy()->startOfDay();

        
        $adjustmentType = strtolower($adjustmentType);
        $isCredit = $adjustmentType === 'credit';
        $entryType = $isCredit ? EmployeeLeaveLedger::TYPE_MANUAL_CREDIT : EmployeeLeaveLedger::TYPE_MANUAL_DEBIT;
        $leaveUnits = round($leaveUnits, 2);
        $reference = $this->manualAdjustmentReference($entryType);

        $description = $this->manualAdjustmentDescription($description, $isCredit, $fromDate, $toDate);

        $description = $this->manualAdjustmentDescription($description, $isCredit, $fromDate, $toDate);

        return DB::transaction(function () use ($employeeId, $fromDate, $toDate, $entryType, $isCredit, $leaveUnits, $reference, $description, $createdBy) {
            $openingBalance = $this->currentBalance($employeeId);
            $creditUnits = $isCredit ? $leaveUnits : 0;
            $debitUnits = $isCredit ? 0 : $leaveUnits;
            $closingBalance = $openingBalance + $creditUnits - $debitUnits;

            return EmployeeLeaveLedger::create([
                'employee_id' => $employeeId,
                'entry_type' => $entryType,
                'leave_month' => (int) $fromDate->month,
                'leave_year' => (int) $fromDate->year,
                'transaction_date' => $fromDate->toDateString(),
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'opening_balance' => $openingBalance,
                'credit_units' => $creditUnits,
                'debit_units' => $debitUnits,
                'closing_balance' => $closingBalance,
                'reference' => $reference,
                'description' => $description,
                'created_by' => $createdBy,
            ]);
        });
    }

 private function normalizeManualAdjustmentArguments($description, $createdBy, $fromDate, $toDate, array $extraDates): array
    {
        if ($description instanceof Carbon) {
            $legacyTransactionDate = $description;
            $legacyDescription = is_string($createdBy) ? $createdBy : null;
            $legacyCreatedBy = is_numeric($fromDate) ? (int) $fromDate : null;
            $legacyFromDate = $toDate instanceof Carbon ? $toDate : $legacyTransactionDate;
            $legacyToDate = isset($extraDates[0]) && $extraDates[0] instanceof Carbon
                ? $extraDates[0]
                : $legacyFromDate;

            return [$legacyDescription, $legacyCreatedBy, $legacyFromDate, $legacyToDate];
        }

        return [
            is_string($description) ? $description : null,
            is_numeric($createdBy) ? (int) $createdBy : null,
            $fromDate instanceof Carbon ? $fromDate : null,
            $toDate instanceof Carbon ? $toDate : null,
        ];
    }

    public function currentBalance(int $employeeId): float
    {
        $lastLedger = EmployeeLeaveLedger::where('employee_id', $employeeId)
            ->orderByDesc('leave_ledger_id')
            ->first();

        return (float) ($lastLedger->closing_balance ?? 0);
    }

    public function availableUnitsForPeriod(int $employeeId, Carbon $startDate, Carbon $endDate): float
    {
        $openingLedger = EmployeeLeaveLedger::where('employee_id', $employeeId)
            ->whereDate('transaction_date', '<', $startDate->toDateString())
            ->orderByDesc('transaction_date')
            ->orderByDesc('leave_ledger_id')
            ->first();

        $openingBalance = (float) ($openingLedger->closing_balance ?? 0);
        $periodCredits = (float) EmployeeLeaveLedger::where('employee_id', $employeeId)
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->whereDate('transaction_date', '<=', $endDate->toDateString())
            ->sum('credit_units');

         return $openingBalance + $periodCredits;
    }

    public function manualDebitUnitsForPeriod(int $employeeId, Carbon $startDate, Carbon $endDate): float
    {

        $manualDebits = EmployeeLeaveLedger::where('employee_id', $employeeId)
            ->whereDate(DB::raw('COALESCE(from_date, transaction_date)'), '<=', $endDate->toDateString())
            ->whereDate(DB::raw('COALESCE(to_date, from_date, transaction_date)'), '>=', $startDate->toDateString())
            ->get();

        $units = 0.0;

        foreach ($manualDebits as $manualDebit) {
            $debitFrom = Carbon::parse($manualDebit->from_date ?: $manualDebit->transaction_date)->startOfDay();
            $debitTo = Carbon::parse($manualDebit->to_date ?: $manualDebit->from_date ?: $manualDebit->transaction_date)->startOfDay();

            $overlapStart = $debitFrom->greaterThan($startDate) ? $debitFrom : $startDate->copy()->startOfDay();
            $overlapEnd = $debitTo->lessThan($endDate) ? $debitTo : $endDate->copy()->startOfDay();

            if ($overlapEnd->lt($overlapStart)) {
                continue;
            }

            $rangeDays = max(1, $debitFrom->diffInDays($debitTo) + 1);
            $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
            $units += ((float) $manualDebit->debit_units / $rangeDays) * $overlapDays;
        }

        return round($units, 2);

    }

    public function leaveUnits(string $leaveType): float
    {
        return strtoupper($leaveType) === 'H' ? 0.5 : 1.0;
    }


    private function manualAdjustmentDescription(?string $description, bool $isCredit, ?Carbon $fromDate, ?Carbon $toDate): string
    {
        $description = trim((string) $description);
        $description = $description !== ''
            ? $description
            : 'Manual leave ' . ($isCredit ? 'credit' : 'debit') . ' adjustment.';

        if (! $fromDate) {
            return $description;
        }

        $toDate = $toDate ?: $fromDate->copy();
        $periodText = $fromDate->isSameDay($toDate)
            ? 'Date: ' . $fromDate->format('d-m-Y')
            : 'Period: ' . $fromDate->format('d-m-Y') . ' to ' . $toDate->format('d-m-Y');

        return $description . ' (' . $periodText . ')';
    }




    private function monthlyCreditReference(int $year, int $month): string
    {
        return 'monthly_credit_' . $year . '_' . str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    }

    private function leaveDebitReference(int $leaveId): string
    {
        return 'leave_debit_' . $leaveId;
    }

    private function manualAdjustmentReference(string $entryType): string
    {
        return $entryType . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4));
    }
}
