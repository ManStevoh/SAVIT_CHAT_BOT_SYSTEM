<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'plan',
        'status',
        'growth_pilot_at',
        'first_attributed_sale_at',
        'growth_demo_mode',
        'industry',
        'attribution_retention_days',
        'stripe_customer_id',
    ];

    protected $casts = [
        'growth_pilot_at' => 'datetime',
        'first_attributed_sale_at' => 'datetime',
        'growth_demo_mode' => 'boolean',
        'attribution_retention_days' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function whatsappAccount(): HasOne
    {
        return $this->hasOne(WhatsAppAccount::class);
    }

    public function dashboardNotifications(): HasMany
    {
        return $this->hasMany(CompanyNotification::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function growthInsights(): HasMany
    {
        return $this->hasMany(GrowthInsight::class);
    }

    public function competitorProfiles(): HasMany
    {
        return $this->hasMany(CompetitorProfile::class);
    }

    public function growthAdSpendEntries(): HasMany
    {
        return $this->hasMany(GrowthAdSpendEntry::class);
    }

    public function portfolioRecommendations(): HasMany
    {
        return $this->hasMany(PortfolioRecommendation::class);
    }
}
