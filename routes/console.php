<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('poe2:prices')->hourly();
