<?php

namespace App\Providers;

use App\Models\Company;
use App\Observers\CompanyObserver;
use App\Services\Agent\AgentToolRegistry;
use App\Services\Agent\Tools\CheckCalendarAvailabilityTool;
use App\Services\Agent\Tools\CheckDeliveryStatusTool;
use App\Services\Agent\Tools\CheckMpesaPaymentTool;
use App\Services\Agent\Tools\GetBusinessInfoTool;
use App\Services\Agent\Tools\GetCatalogTool;
use App\Services\Agent\Tools\GetCustomerProfileTool;
use App\Services\Agent\Tools\GetMarketingPerformanceTool;
use App\Services\Agent\Tools\GetProductRelationshipsTool;
use App\Services\Agent\Tools\GetShippingQuoteTool;
use App\Services\Agent\Tools\GetWeatherTool;
use App\Services\Agent\Tools\IssueOrderRefundTool;
use App\Services\Agent\Tools\ProcessOrderMessageTool;
use App\Services\Agent\Tools\RememberCustomerTool;
use App\Services\Agent\Tools\SearchFaqTool;
use App\Services\Agent\Tools\SearchKnowledgeTool;
use App\Services\Agent\Tools\SearchOrdersTool;
use App\Services\Agent\Tools\SearchProductsTool;
use App\Services\Agent\Tools\SendWhatsAppCampaignTool;
use App\Services\Agent\Tools\TraceCustomerGraphTool;
use App\Services\Agent\Tools\TransferToHumanTool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AgentToolRegistry::class, function ($app) {
            $registry = new AgentToolRegistry();
            foreach ([
                SearchProductsTool::class,
                SearchFaqTool::class,
                SearchKnowledgeTool::class,
                GetCustomerProfileTool::class,
                SearchOrdersTool::class,
                GetCatalogTool::class,
                ProcessOrderMessageTool::class,
                TransferToHumanTool::class,
                RememberCustomerTool::class,
                GetBusinessInfoTool::class,
                TraceCustomerGraphTool::class,
                GetProductRelationshipsTool::class,
                CheckDeliveryStatusTool::class,
                GetWeatherTool::class,
                CheckMpesaPaymentTool::class,
                GetShippingQuoteTool::class,
                CheckCalendarAvailabilityTool::class,
                GetMarketingPerformanceTool::class,
                SendWhatsAppCampaignTool::class,
                IssueOrderRefundTool::class,
            ] as $toolClass) {
                $registry->register($app->make($toolClass));
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Company::observe(CompanyObserver::class);

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('auth-password', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });
    }
}
