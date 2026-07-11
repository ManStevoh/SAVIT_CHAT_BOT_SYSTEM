<?php

namespace App\Console\Commands;

use App\Services\Agent\AgentToolRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifyEnterprisePlatformCommand extends Command
{
    protected $signature = 'platform:verify';

