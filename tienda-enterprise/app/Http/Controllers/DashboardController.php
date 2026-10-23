<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class DashboardController extends Controller
{
    public function __invoke(ProductRepositoryInterface $products, OrderRepositoryInterface $orders): View
    {
        return view('dashboard', [
            'stats' => $products->catalogStats(),
            'orders' => $orders->latestWithSummary(10),
            'products' => $products->activeForSelect(),
            'users' => User::query()->select(['id', 'name'])->orderBy('name')->limit(20)->get(),
            'queue' => [
                'default' => Queue::size('default'),
                'low' => Queue::size('low'),
                'failed' => DB::table('failed_jobs')->count(),
            ],
        ]);
    }
}
