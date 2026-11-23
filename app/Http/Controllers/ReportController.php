<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\BusinessReportService;
use App\Support\ReportsCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly BusinessReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $categories = ReportsCatalog::forUser($user);

        $availableCount = 0;
        $plannedCount = 0;

        foreach ($categories as $category) {
            foreach ($category['reports'] as $report) {
                if ($report['status'] === 'available' && $report['accessible']) {
                    $availableCount++;
                } elseif ($report['status'] === 'not_implemented') {
                    $plannedCount++;
                }
            }
        }

        return Inertia::render('reports/index', [
            'categories' => $categories,
            'stats' => [
                'available' => $availableCount,
                'planned' => $plannedCount,
                'categories' => count($categories),
            ],
        ]);
    }

    /**
     * Period report builder — select month/year/custom range and export PDF.
     */
    public function export(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('reports/export', [
            'reportOptions' => $this->reports->reportOptions(),
            'branches' => Branch::notTrashed()
                ->visibleTo($user)
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get(),
            'defaults' => [
                'quick' => 'this_month',
                'month' => (int) now()->month,
                'year' => (int) now()->year,
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
                'report' => 'full',
                'branch_id' => $user?->isBranchScoped() ? $user->branch_id : null,
                'include_all_products' => false,
                'include_all_parties' => false,
            ],
            'branchScoped' => (bool) $user?->isBranchScoped(),
            'years' => range((int) now()->year, (int) now()->year - 10),
        ]);
    }

    /**
     * JSON preview of report totals (no PDF).
     */
    public function preview(Request $request): \Illuminate\Http\JsonResponse
    {
        $input = $this->validatedFilters($request);
        $data = $this->reports->build($input, $request->user());

        return response()->json([
            'ok' => true,
            'meta' => $data['meta'],
            'summary' => $data['summary'] ?? null,
            'sections' => $data['sections'],
            'data' => $data,
        ]);
    }

    /**
     * Stream A4 PDF for the selected period/report.
     */
    public function pdf(Request $request): HttpResponse|StreamedResponse
    {
        $input = $this->validatedFilters($request);
        $data = $this->reports->build($input, $request->user());

        $filename = sprintf(
            'ZUHA-%s-%s-to-%s.pdf',
            $data['meta']['report'],
            $data['meta']['period_from'],
            $data['meta']['period_to'],
        );

        $pdf = Pdf::loadView('reports.business.period-pdf', [
            'report' => $data,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'quick' => ['required', Rule::in(['this_month', 'last_month', 'this_year', 'month', 'custom'])],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'report' => ['required', Rule::in(array_keys(BusinessReportService::REPORT_PRESETS))],
            'include_all_products' => ['sometimes', 'boolean'],
            'include_all_parties' => ['sometimes', 'boolean'],
        ]);

        $validated['include_all_products'] = $request->boolean('include_all_products');
        $validated['include_all_parties'] = $request->boolean('include_all_parties');

        return $validated;
    }
}
