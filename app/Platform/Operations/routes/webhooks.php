<?php

use App\Platform\Operations\Http\Webhooks\CronJobController;
use Illuminate\Support\Facades\Route;

// Scheduler trigger for hosts without a real crontab. The middleware is the
// only gate here — it matches the shared token header before the run.
Route::middleware('cron-job')->get('cron', CronJobController::class);
