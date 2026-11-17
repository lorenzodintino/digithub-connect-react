<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\PlanCatalogue;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The showcase of the platform, with the plans on sale as the table has them.
     */
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'plans' => PlanCatalogue::onSale(),
            'featureLabels' => Plan::featureLabels(),
        ]);
    }
}
