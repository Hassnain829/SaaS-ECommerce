<?php

namespace Database\Seeders;

use App\Services\ReturnService;
use Illuminate\Database\Seeder;

class ReturnReasonSeeder extends Seeder
{
    public function run(): void
    {
        app(ReturnService::class)->ensureDefaultReasons(null);
    }
}
