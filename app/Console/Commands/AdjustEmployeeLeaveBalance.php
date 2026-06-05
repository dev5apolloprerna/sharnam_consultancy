<?php

namespace App\Console\Commands;

use App\Services\EmployeeLeaveLedgerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AdjustEmployeeLeaveBalance extends Command
{
    protected $signature = 'employee-leaves:adjust
        {employee_id : Employee ID to adjust}
        {adjustment_type : credit or debit}
        {leave_units : Leave units to add or subtract}
        {--from= : From date (Y-m-d). Defaults to today.}
        {--to= : To date (Y-m-d). Defaults to from date.}
        {--description= : Manual adjustment note}';

    protected $description = 'Manually credit or debit leave balance for a particular employee.';

    public function handle(EmployeeLeaveLedgerService $ledgerService): int
    {
        $adjustmentType = strtolower((string) $this->argument('adjustment_type'));

        if (!in_array($adjustmentType, ['credit', 'debit'], true)) {
            $this->error('adjustment_type must be credit or debit.');
            return Command::FAILURE;
        }

        $leaveUnits = (float) $this->argument('leave_units');

        if ($leaveUnits <= 0) {
            $this->error('leave_units must be greater than 0.');
            return Command::FAILURE;
        }

        $fromDate = $this->option('from') ? Carbon::parse((string) $this->option('from')) : null;
        $toDate = $this->option('to') ? Carbon::parse((string) $this->option('to')) : null;

        if ($fromDate && $toDate && $toDate->lt($fromDate)) {
            $this->error('to date must be greater than or equal to from date.');
            return Command::FAILURE;
        }



        $ledger = $ledgerService->manualAdjustment(
            (int) $this->argument('employee_id'),
            $adjustmentType,
            $leaveUnits,
            $this->option('description') ?: null,
            null,
            $fromDate,
            $toDate
        );

        $this->info('Manual leave ' . $adjustmentType . ' saved successfully.');
        $this->line('Ledger ID: ' . $ledger->leave_ledger_id);
        $this->line('Current Balance: ' . number_format((float) $ledger->closing_balance, 2));

        return Command::SUCCESS;
    }
}
