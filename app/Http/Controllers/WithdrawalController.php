<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Withdrawal');
    }
}
