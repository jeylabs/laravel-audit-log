<?php

namespace Jeylabs\AuditLog;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Jeylabs\AuditLog\Contracts\AuditLogStore;

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

    public function handle(AuditLogStore $store)
    {
        $this->comment('Cleaning audit log...');

        $maxAgeInDays = config('laravel-audit-log.delete_records_older_than_days');

        $cutOffDate = Carbon::now()->subDays($maxAgeInDays);

        $amountDeleted = $store->deleteOlderThan($cutOffDate);

        $this->info("Deleted {$amountDeleted} record(s) from the audit log.");

        $this->comment('All done!');
    }
}
