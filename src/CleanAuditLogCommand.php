<?php

namespace Jeylabs\AuditLog;

use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanAuditLogCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'auditlog:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old records from the audit log.';

    public function handle()
    {
        $this->comment('Cleaning audit log...');

        $maxAgeInDays = config('laravel-audit-log.delete_records_older_than_days');

        $cutOffDate = Carbon::now()->subDays($maxAgeInDays)->format('Y-m-d H:i:s');

        $activity = AuditLogServiceProvider::getActivityModelInstance();

        $amountDeleted = $activity::where('created_at', '<', $cutOffDate)->delete();

        $this->info("Deleted {$amountDeleted} record(s) from the audit log.");

        $this->comment('All done!');
    }
}
