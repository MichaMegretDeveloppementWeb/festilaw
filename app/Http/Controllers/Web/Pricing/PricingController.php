<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Pricing;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class PricingController extends Controller
{
    public function __invoke(): View
    {
        return view('web.pricing.index');
    }
}
