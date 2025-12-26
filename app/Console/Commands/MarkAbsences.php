<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Presence;
use Illuminate\Console\Command;

class MarkAbsences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:mark-absences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
{
    $today = now()->toDateString();
    $employees = Employee::all();

    foreach ($employees as $employee) {
        $exists = Presence::where('employee_id', $employee->id)
                          ->where('date', $today)
                          ->exists();

        if (!$exists) {
            Presence::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'status' => 'absent', // Assurez-vous d'avoir une colonne status
                'check_in' => null,
                'check_out' => null
            ]);
        }
    }
}
}
