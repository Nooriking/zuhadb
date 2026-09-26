<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SplashController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Splash', [
            'app' => [
                'name'        => config('app.name', 'Perfume Shop'),
                'tagline'     => 'Luxury Perfume Management System',
                'description' => 'A complete solution for managing your perfume business — inventory, sales, multi-branch, investor tracking, and POS with barcode scanning.',
                'version'     => '1.0.0',
            ],
        ]);
    }
}