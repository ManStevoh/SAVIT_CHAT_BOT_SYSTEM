<?php

namespace App\Services\Agent\Channels;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

final class ChannelIngestAuthService
{
    public function companyFromRequest(Request $request, ?int $companyId = null): ?Company
    {
