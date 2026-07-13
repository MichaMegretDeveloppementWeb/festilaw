<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Services;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class ServicesController extends Controller
{
    public function __invoke(): View
    {
        return view('web.services.index');
    }
}
