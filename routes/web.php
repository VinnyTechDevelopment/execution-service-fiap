<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['service' => 'execution-service', 'docs' => '/openapi.yaml']));
