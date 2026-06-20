<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('wallet:health-check')->dailyAt('02:00');
