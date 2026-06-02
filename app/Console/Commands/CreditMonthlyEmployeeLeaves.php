<?php

namespace App\Console\Commands;

use App\Models\EmployeeLeaveLedger;
use App\Services\EmployeeLeaveLedgerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreditMonthlyEmployeeLeaves extends Command
{
    protected $signature = 'employee-leaves:credit-monthly {--date= : Any date in the month to credit (Y-m-d). Defaults to today.}';

    protected $description = 'Credit default monthly leave balance to every active employee.';

    public function handle(EmployeeLeaveLedgerService $ledgerService): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : now();

        $created = $ledgerService->creditMonthlyLeaves($date, EmployeeLeaveLedger::DEFAULT_MONTHLY_CREDIT);

        $this->info('Monthly leave credit completed. Entries created: ' . $created);

        return Command::SUCCESS;
    }
}
