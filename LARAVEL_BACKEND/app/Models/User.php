<?php

namespace App\Models;

use App\Services\MailService;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'phone',
        'status',
        'avatar',
        'role',
        'terms_accepted_at',
        'marketing_consent',
        'marketing_consent_at',
        'selected_plan_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'marketing_consent' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Send the password reset notification. Uses MailService so platform SMTP (admin-configured) is used when set.
     */
    public function sendPasswordResetNotification($token): void
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
        $url = $frontendUrl . '/reset-password?token=' . $token;
        $appName = MailService::applicationName();
        $subject = '[' . $appName . '] Reset your password';
        $html = '<p>You are receiving this because we received a password reset request for your account.</p>';
        $html .= '<p><a href="' . e($url) . '">Reset password</a></p>';
        $html .= '<p>If you did not request this, you can ignore this email.</p>';
        $html .= '<p>This link will expire in 60 minutes.</p>';
        $html = MailService::wrapEmailBody($html);
        App::make(MailService::class)->send($this->email, $subject, $html, strip_tags($html));
    }

    /**
     * Send email verification using MailService and a signed API URL (so platform SMTP is used).
     */
    public function sendEmailVerificationNotification(): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $this->id, 'hash' => sha1($this->email)]
        );
        App::make(MailService::class)->sendWelcomeVerificationEmail($this, $verificationUrl);
    }
}
