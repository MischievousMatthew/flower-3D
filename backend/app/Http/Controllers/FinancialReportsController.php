<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FinancialReportsService;
use App\Services\VendorBooksService;
use App\Traits\ResolvesOwner;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finance → Reports: sales, expenses, profit and loss, payments and refunds,
 * and tax summaries, shown on the page and downloaded as CSV or PDF.
 *
 * Served at /vendor/books/financial-reports/{type}* for the shop owner and
 * /finance/reports/{type}* for ERP Finance staff (financial_reports module:
 * view to read, export to download).
 */
class FinancialReportsController extends Controller
{
    use ResolvesOwner;

    public function __construct(private FinancialReportsService $reports) {}

    /** GET …/{type} */
    public function show(Request $request, string $type): JsonResponse
    {
        [$from, $to, $options, $error] = $this->inputs($request);
        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        return response()->json(['success' => true, 'data' => $this->report($request, $type, $from, $to, $options)]);
    }

    /** GET …/{type}/export?format=csv|pdf */
    public function export(Request $request, string $type): Response
    {
        $format = $request->validate(['format' => 'required|in:csv,pdf'])['format'];
        [$from, $to, $options, $error] = $this->inputs($request);
        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }
        $report = $this->report($request, $type, $from, $to, $options);
        $name = 'bloomcraft-' . str_replace('_', '-', $type) . '-' . $from->format('Ymd') . '-' . $to->format('Ymd');

        if ($format === 'pdf') {
            return Pdf::loadView('reports.financial', ['r' => $report, 'generated' => now()])
                ->setPaper('a4', count($report['tables'][0]['columns'] ?? []) > 5 ? 'landscape' : 'portrait')
                ->download("{$name}.pdf");
        }

        return response($this->csv($report), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}.csv\"",
        ]);
    }

    private function inputs(Request $request): array
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'unit' => ['nullable', Rule::in(FinancialReportsService::UNITS)],
            'basis' => ['nullable', Rule::in(VendorBooksService::COST_BASES)],
            'tax_mode' => ['nullable', Rule::in(FinancialReportsService::TAX_MODES)],
        ]);
        $from = $request->filled('from') ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $options = ['unit' => $data['unit'] ?? 'day', 'basis' => $data['basis'] ?? 'sold', 'tax_mode' => $data['tax_mode'] ?? 'percentage'];

        return [$from, $to, $options, $this->reports->tooLong($from, $to, $options['unit'])];
    }

    private function report(Request $request, string $type, Carbon $from, Carbon $to, array $options): array
    {
        $vendorId = $this->resolveOwnerId($request);
        $shop = User::find($vendorId)?->vendor_data['store_name'] ?? 'Your shop';

        return [
            'type' => $type,
            'shop' => $shop,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $from->format('M j, Y') . ' – ' . $to->format('M j, Y')],
            'options' => $options,
        ] + $this->reports->build($type, $vendorId, $from, $to, $options);
    }

    /** One CSV with the summary first, then each table; numbers stay plain so spreadsheets can add them up */
    private function csv(array $r): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ["{$r['shop']} — {$r['title']}", $r['range']['label']]);
        if ($r['subtitle']) {
            fputcsv($out, [$r['subtitle']]);
        }
        foreach ($r['notes'] as $note) {
            fputcsv($out, ["Note: {$note}"]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Summary']);
        foreach ($r['summary'] as [$label, $value]) {
            fputcsv($out, [$label, $value]);
        }
        foreach ($r['tables'] as $table) {
            fputcsv($out, []);
            fputcsv($out, [$table['title']]);
            fputcsv($out, array_column($table['columns'], 1));
            foreach ($table['rows'] as $row) {
                fputcsv($out, array_map(fn ($c) => $row[$c[0]] ?? '', $table['columns']));
            }
            if ($table['totals']) {
                fputcsv($out, array_map(fn ($c) => $table['totals'][$c[0]] ?? '', $table['columns']));
            }
        }
        rewind($out);
        $body = "\xEF\xBB\xBF" . stream_get_contents($out);
        fclose($out);

        return $body;
    }
}
