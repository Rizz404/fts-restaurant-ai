<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Schedule (PLAN_08)
|--------------------------------------------------------------------------
|
| Run `php artisan schedule:run` every minute from cron (see
| docs/OPERATIONS.md). Retention runs at 02:00 restaurant time.
|
*/

Schedule::command('restaurant:recover-turns')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('restaurant:purge-data')
    ->dailyAt('02:00')
    ->timezone(config('restaurant.timezone'))
    ->withoutOverlapping(120)
    ->onOneServer();
