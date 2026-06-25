<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\ConversationLearningExportService;

class CompanyObserver
{
    public function deleted(Company $company): void
    {
        app(ConversationLearningExportService::class)->purgeForCompany((int) $company->id);
    }
}
