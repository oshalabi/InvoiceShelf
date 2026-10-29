<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Http\Requests\DeleteEstimatesRequest;
use App\Domains\Sales\Http\Requests\EstimatesRequest;
use App\Domains\Sales\Http\Requests\SendEstimatesRequest;
use App\Domains\Sales\Http\Resources\EstimateResource;
use App\Domains\Sales\Http\Resources\InvoiceResource;
use App\Domains\Sales\Jobs\GenerateEstimatePdfJob;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

/**
 * Company-scoped estimate endpoints: listing, the write surface, bulk removal,
 * mailing, and the two document conversions.
 */
class EstimatesController extends Controller
{
    public function __construct(private readonly EstimateService $estimateService) {}

    /**
     * Paginated estimates of the active company, joined to their customer so the
     * list can be filtered and sorted by customer name.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        $filters = $request->all();
        $perPage = $request->has('limit') ? $request->input('limit') : 10;

        $page = Estimate::query()
            ->whereCompany()
            ->join('customers', fn ($join) => $join->on('customers.id', '=', 'estimates.customer_id'))
            ->applyFilters($filters)
            ->select(['estimates.*', 'customers.name'])
            ->orderByDesc('created_at')
            ->paginateData($perPage);

        return EstimateResource::collection($page)->additional([
            'meta' => [
                'estimate_total_count' => Estimate::query()->whereCompany()->count(),
            ],
        ]);
    }

    /**
     * Persist a new estimate, optionally mailing it straight away, and queue the
     * PDF render.
     */
    public function store(EstimatesRequest $request)
    {
        $this->authorize('create', Estimate::class);

        $estimate = $this->estimateService->create(...$this->writeArguments($request));

        if ($request->has('estimateSend')) {
            $this->estimateService->send($estimate, $request->only(['title', 'body']));
        }

        GenerateEstimatePdfJob::dispatch($estimate);

        return EstimateResource::make($estimate);
    }

    public function show(Request $request, Estimate $estimate)
    {
        $this->authorize('view', $estimate);

        return EstimateResource::make($estimate);
    }

    /**
     * Overwrite an estimate — lines and taxes are replaced wholesale — and
     * re-render its PDF.
     */
    public function update(EstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('update', $estimate);

        $estimate = $this->estimateService->update($estimate, ...$this->writeArguments($request));

        GenerateEstimatePdfJob::dispatch($estimate, true);

        return EstimateResource::make($estimate);
    }

    /**
     * Bulk removal. Ids outside the active company are silently skipped.
     */
    public function delete(DeleteEstimatesRequest $request)
    {
        $this->authorize('delete multiple estimates');

        $ids = Estimate::query()
            ->whereCompany()
            ->whereIn('id', $request->input('ids'))
            ->pluck('id');

        Estimate::destroy($ids);

        return response()->json(['success' => true]);
    }

    public function send(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        return response()->json(
            $this->estimateService->send($estimate, $request->all())
        );
    }

    /**
     * Render the mail body the customer would receive, without sending it.
     */
    public function sendPreview(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $data = $this->estimateService->sendEstimateData($estimate, $request->all());
        $data['url'] = $estimate->estimatePdfUrl;

        $renderer = new Markdown(view(), config('mail.markdown'));

        return $renderer->render('emails.send.estimate', ['data' => $data]);
    }

    public function clone(Request $request, Estimate $estimate)
    {
        $this->authorize('view', $estimate);
        $this->authorize('create', Estimate::class);

        return EstimateResource::make($this->estimateService->clone($estimate));
    }

    /**
     * Reading the source estimate is checked on top of the invoice-create
     * ability so the conversion cannot reach across companies.
     */
    public function convertToInvoice(Request $request, Estimate $estimate)
    {
        $this->authorize('view', $estimate);
        $this->authorize('create', Invoice::class);

        return InvoiceResource::make($this->estimateService->convertToInvoice($estimate));
    }

    public function changeStatus(Request $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $this->estimateService->changeStatus($estimate, $request->input('status'));

        return response()->json(['success' => true]);
    }

    /**
     * The arguments create() and update() share, keyed by parameter name.
     *
     * @return array<string, mixed>
     */
    private function writeArguments(EstimatesRequest $request): array
    {
        $fields = $request->input('customFields');

        return [
            'attributes' => $request->getEstimatePayload(),
            'items' => $request->input('items'),
            'taxes' => $request->has('taxes') ? $request->input('taxes') : null,
            'customFields' => is_iterable($fields) ? $fields : null,
        ];
    }
}
