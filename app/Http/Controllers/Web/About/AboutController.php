<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\About;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class AboutController extends Controller
{
    public function __invoke(): View
    {
        return view('web.about.index');
    }
}
