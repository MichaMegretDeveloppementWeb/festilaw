<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Home;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('web.home.index');
    }
}
