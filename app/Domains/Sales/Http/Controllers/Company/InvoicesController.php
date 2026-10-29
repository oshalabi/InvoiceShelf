<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Application\CreditNoteService;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Http\Requests\ChangeInvoiceStatusRequest;
use App\Domains\Sales\Http\Requests\CreateCreditNoteRequest;
use App\Domains\Sales\Http\Requests\DeleteInvoiceRequest;
use App\Domains\Sales\Http\Requests\InvoicesRequest;
use App\Domains\Sales\Http\Requests\SendInvoiceRequest;
use App\Domains\Sales\Http\Resources\CreditNoteResource;
use App\Domains\Sales\Http\Resources\EstimateResource;
use App\Domains\Sales\Http\Resources\InvoiceResource;
use App\Domains\Sales\Jobs\GenerateInvoicePdfJob;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Validation\ValidationException;

/**
 * Company-scoped invoice endpoints: listing, the write surface, bulk removal,
 * mailing, cloning, conversion to an estimate, credit notes, and the status
 * transitions.
 */
class InvoicesController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly CreditNoteService $creditNoteService,
    ) {}

    /**
     * Paginated invoices of the active company, newest first.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $limit = $request->input('limit', 10);
        $filters = $request->all();

        // creditNotes drives the "cancelled" badge on every row, so it is
        // eager-loaded (two columns) rather than probed per row.
        $invoices = Invoice::query()
            ->whereCompany()
            ->applyFilters($filters)
            ->with(['customer', 'creditNotes:id,related_invoice_id,invoice_number,total'])
            ->latest()
            ->paginateData($limit);

        return InvoiceResource::collection($invoices)
            ->additional([
                'meta' => [
                    'invoice_total_count' => Invoice::query()->whereCompany()->count(),
                ],
            ]);
    }

    /**
     * Persist a new invoice, optionally mail it straight away, and queue its
     * PDF render.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(InvoicesRequest $request)
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create(
            attributes: $request->getInvoicePayload(),
            items: $request->input('items'),
            taxes: $request->has('taxes') ? $request->input('taxes') : null,
            customFields: $this->customFields($request),
        );

        if ($request->exists('invoiceSend')) {
            $this->invoiceService->send($invoice, $request->only(['subject', 'body']));
        }

        dispatch(new GenerateInvoicePdfJob($invoice));

        return InvoiceResource::make($invoice);
    }

    /**
     * One invoice, loaded with what its detail page reads.
     *
     * @return JsonResponse
     */
    public function show(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        if ($invoice->isCreditNote()) {
            return new CreditNoteResource($invoice->load('relatedInvoice'));
        }

        // Feeds the credit-note banner on the detail page: how much of the
        // invoice has been credited, and how much of each line, so the partial
        // credit form can offer the remaining quantities.
        return new InvoiceResource($invoice->load([
            'creditNotes:id,related_invoice_id,invoice_number,total',
            'creditNotes.items:id,invoice_id,source_invoice_item_id,quantity',
            'allocations.payment',
        ]));
    }

    /**
     * Overwrite an invoice, lines and taxes included, and re-render its PDF.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function update(InvoicesRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice = $this->invoiceService->update(
            invoice: $invoice,
            attributes: $request->getInvoicePayload(),
            items: $request->input('items'),
            taxes: $request->has('taxes') ? $request->input('taxes') : null,
            customFields: $this->customFields($request),
        );

        dispatch(new GenerateInvoicePdfJob($invoice, true));

        return InvoiceResource::make($invoice);
    }

    /**
     * Bulk removal. Ids outside the active company are silently skipped.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteInvoiceRequest $request)
    {
        $this->authorize('delete multiple invoices');

        $ids = Invoice::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        $this->invoiceService->delete($ids);

        return response()->json(['success' => true]);
    }

    public function send(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $this->invoiceService->send($invoice, $request->all());

        return response()->json(['success' => true]);
    }

    public function sendPreview(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $markdown = new Markdown(app('view'), config('mail.markdown'));

        $data = $this->invoiceService->sendInvoiceData($invoice, $request->all());
        $data['url'] = $invoice->invoice_pdf_url;

        // Preview the template that will actually be sent: a credit note goes
        // out through SendCreditNoteMail, so it must preview as one.
        $view = $invoice->isCreditNote() ? 'emails.send.credit-note' : 'emails.send.invoice';

        return $markdown->render($view, ['data' => $data]);
    }

    public function clone(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $this->authorize('create', Invoice::class);

        // Cloning a credit note would mint a positive invoice out of a reversal
        // document. Domain rule violation (422), not an authorization failure.
        if ($invoice->isCreditNote()) {
            throw ValidationException::withMessages([
                'invoice' => ['a_credit_note_cannot_be_cloned'],
            ]);
        }

        $newInvoice = $this->invoiceService->clone($invoice);

        return new InvoiceResource($newInvoice);
    }

    public function convertToEstimate(Request $request, Invoice $invoice)
    {
        // Authorize access to the source invoice (tenant isolation) in addition
        // to the ability to create an estimate.
        $this->authorize('view', $invoice);
        $this->authorize('create', Estimate::class);

        // Same reason as clone(): the conversion copies the amounts unnegated,
        // so a credit note would become a positive estimate.
        if ($invoice->isCreditNote()) {
            throw ValidationException::withMessages([
                'invoice' => ['a_credit_note_cannot_be_converted_to_an_estimate'],
            ]);
        }

        $estimate = $this->invoiceService->convertToEstimate($invoice);

        return new EstimateResource($estimate);
    }

    public function createCreditNote(CreateCreditNoteRequest $request, Invoice $invoice)
    {
        $this->authorize('create credit note', $invoice);

        // A credit note can only reverse a real invoice, never another credit
        // note. This is a domain rule (422), not an authorization failure (403).
        if ($invoice->isCreditNote()) {
            throw ValidationException::withMessages([
                'invoice' => ['a_credit_note_cannot_be_created_from_a_credit_note'],
            ]);
        }

        // A draft was never issued, so there is nothing to reverse: edit or
        // delete it instead.
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'invoice' => ['a_draft_invoice_cannot_be_credited'],
            ]);
        }

        // How much of the invoice is still creditable, and whether the credit
        // fits inside its unpaid balance, is decided by the service under a row
        // lock. Guarding it here would race.
        $creditNote = $this->creditNoteService->create(
            $invoice,
            $request->input('items', []),
            $request->input('reason')
        );

        GenerateInvoicePdfJob::dispatch($creditNote);

        // The original's own PDF changed too: its balance moved and it now
        // carries the cancellation banner, so the stored file is replaced.
        GenerateInvoicePdfJob::dispatch($invoice->fresh(), true);

        return (new CreditNoteResource($creditNote))
            ->response()
            ->setStatusCode(201);
    }

    public function changeStatus(ChangeInvoiceStatusRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $this->invoiceService->changeStatus($invoice, $request->status);

        return response()->json(['success' => true]);
    }

    private function customFields(InvoicesRequest $request): ?iterable
    {
        $customFields = $request->input('customFields');

        return is_iterable($customFields) ? $customFields : null;
    }
}
