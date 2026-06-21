<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;

class GrowthPilotCompanyCommand extends Command
{
    protected $signature = 'growth:pilot-company {email : Company owner email}';

    protected $description = 'Mark a company as Growth Engine pilot and print OAuth setup steps';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || ! $user->company_id) {
            $this->error('User not found or has no company.');

            return self::FAILURE;
        }

        $company = Company::find($user->company_id);
        if (! $company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        $company->update(['growth_pilot_at' => now()]);

        $this->info("Pilot enabled: {$company->name} (ID {$company->id})");
        $this->line('');
        $this->line('Next steps for this client:');
        $this->line('1. Set GROWTH_META_APP_ID + GROWTH_META_APP_SECRET on server');
        $this->line('2. Meta App → Valid OAuth Redirect: '.rtrim(config('app.url'), '/').'/oauth/growth/callback');
        $this->line('3. Request permissions: pages_show_list, pages_read_engagement, pages_manage_posts, ads_read');
        $this->line('4. Client logs in → Dashboard → Growth → Platforms → Connect Facebook');
        $this->line('5. Select Page + Ad Account if prompted');
        $this->line('6. Run: php artisan growth:sync-meta --company='.$company->id);

        return self::SUCCESS;
    }
}
