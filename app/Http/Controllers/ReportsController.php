<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function index(Request $request, MonthlyReport $report)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $date = MonthlyReport::monthFromQuery($request->query('month'));
        $data = $report->build($user, $date);

        return Inertia::render('Reports/Index', [
            'period' => [
                'month' => $data['month'],
                'label' => $data['label'],
                'prev' => $date->copy()->subMonth()->format('Y-m'),
                'next' => $date->copy()->addMonth()->format('Y-m'),
                'isCurrent' => $date->isSameMonth(now()),
            ],
            'totals' => [
                'income' => $data['totals']['income'],
                'expense' => $data['totals']['expense'],
                'balance' => $data['totals']['balance'],
                'prevIncome' => $data['totals']['prevIncome'],
                'prevExpense' => $data['totals']['prevExpense'],
            ],
            'expenseByCategory' => $data['expenseByCategory'],
            'incomeByCategory' => $data['incomeByCategory'],
            'baseSymbol' => $data['symbol'],
        ]);
    }
}
