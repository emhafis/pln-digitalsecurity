<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\App\Concerns\BuildsAttendanceContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BuildsAttendanceContext;

    public function index(Request $request): Response
    {
        return Inertia::render('App/Home', $this->attendanceContext($request));
    }
}
