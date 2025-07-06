<?php

namespace Blueprint\Controllers;

use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
    }
} 