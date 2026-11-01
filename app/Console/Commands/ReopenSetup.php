<?php

namespace App\Console\Commands;

use App\Services\Settings\Settings;
use App\Services\Setup\SetupStatus;
use Illuminate\Console\Command;

/** Clear the persistent first-run completion marker after explicit confirmation. */
class ReopenSetup extends Command
{
    protected $signature = 'setup:reopen {--force : Confirm clearing the persistent setup completion marker}';

    protected $description = 'Clear the first-run setup completion marker';

    public function handle(Settings $settings, SetupStatus $setupStatus): int
    {
        $this->warn('This clears setup_completed_at. First-run setup can create a new administrator.');

        if (! $this->option('force')) {
            $this->error('Pass --force to clear the setup completion marker.');

            return self::FAILURE;
        }

        $settings->forget('setup_completed_at');
        $setupStatus->forget();
        $this->info('Setup completion marker cleared. Setup remains blocked while any user account exists.');

        return self::SUCCESS;
    }
}
