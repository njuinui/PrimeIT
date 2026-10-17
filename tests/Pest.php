<?php

use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->in('Feature');
    
require_once __DIR__.'/Support/helpers.php';