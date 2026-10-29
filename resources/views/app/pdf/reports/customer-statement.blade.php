<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>{{ __('Customer Statement') }}</title>
    @include('app.pdf.partials.fonts')
    @include('app.pdf.reports.partials.styles')
</head>
<body>
    <table class="report-header">
        <tr>
            <td>
                @if (! empty($logo))
                    <img class="report-logo" style="height:40px" src="{{ \App\Platform\Pdf\Rendering\ImageUtils::toBase64Src($logo) }}" alt="{{ $company->name }}">
                @else
                    <p class="report-company-name">{{ $company->name }}</p>
                @endif
            </td>
            <td>
                <p class="report-date-range">
                    @if ($statement['type'] === 'activity')
                        {{ $statement['from_date'] }} - {{ $statement['to_date'] }}
                    @else
                        {{ __('As of') }} {{ $statement['as_of'] }}
                    @endif
                </p>
            </td>
        </tr>
    </table>

    <p class="report-title">{{ __('Customer Statement') }} — {{ $customer->name }}</p>

    @if ($statement['type'] === 'activity')
        <div class="report-section">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="report-amount">{{ __('Debit') }}</th>
                        <th class="report-amount">{{ __('Credit') }}</th>
                        <th class="report-amount">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="report-total">{{ __('Opening balance') }}</td>
                        <td class="report-amount report-total">{!! format_money_pdf($statement['opening_balance'], $currency) !!}</td>
                    </tr>
                    @forelse ($statement['entries']->items() as $entry)
                        <tr>
                            <td>{{ $entry['date'] }}</td>
                            <td>{{ $entry['description'] }}</td>
                            <td>{{ $entry['reference'] }}</td>
                            <td class="report-amount">{!! $entry['debit_amount'] ? format_money_pdf($entry['debit_amount'], $currency) : '' !!}</td>
                            <td class="report-amount">{!! $entry['credit_amount'] ? format_money_pdf($entry['credit_amount'], $currency) : '' !!}</td>
                            <td class="report-amount">{!! format_money_pdf($entry['balance'], $currency) !!}</td>
                        </tr>
                    @empty
                        <tr><td class="report-muted" colspan="6">@lang('pdf_report_no_records')</td></tr>
                    @endforelse
                    <tr class="report-total-row">
                        <td colspan="5" class="report-total">{{ __('Closing balance') }}</td>
                        <td class="report-amount report-total">{!! format_money_pdf($statement['closing_balance'], $currency) !!}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        <div class="report-section">
            <p class="report-section-heading">{{ __('Outstanding invoices') }}</p>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Due date') }}</th>
                        <th class="report-amount">{{ __('Original') }}</th>
                        <th class="report-amount">{{ __('Applied') }}</th>
                        <th class="report-amount">{{ __('Remaining') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statement['invoices'] as $invoice)
                        <tr>
                            <td>{{ $invoice['invoice_number'] }}</td>
                            <td>{{ $invoice['due_date'] }}</td>
                            <td class="report-amount">{!! format_money_pdf($invoice['original_amount'], $currency) !!}</td>
                            <td class="report-amount">{!! format_money_pdf($invoice['applied_amount'], $currency) !!}</td>
                            <td class="report-amount">{!! format_money_pdf($invoice['remaining_amount'], $currency) !!}</td>
                        </tr>
                    @empty
                        <tr><td class="report-muted" colspan="5">@lang('pdf_report_no_records')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="report-section">
            <p class="report-section-heading">{{ __('Available credit') }}</p>
            <table class="report-table">
                <thead><tr><th>{{ __('Payment') }}</th><th>{{ __('Date') }}</th><th class="report-amount">{{ __('Available') }}</th></tr></thead>
                <tbody>
                    @forelse ($statement['credits'] as $credit)
                        <tr>
                            <td>{{ $credit['payment_number'] }}</td>
                            <td>{{ $credit['payment_date'] }}</td>
                            <td class="report-amount">{!! format_money_pdf($credit['available_amount'], $currency) !!}</td>
                        </tr>
                    @empty
                        <tr><td class="report-muted" colspan="3">@lang('pdf_report_no_records')</td></tr>
                    @endforelse
                    <tr>
                        <td colspan="2" class="report-total">{{ __('Gross invoice due') }}</td>
                        <td class="report-amount report-total">{!! format_money_pdf($statement['invoice_due_amount'], $currency) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="report-total">{{ __('Available credit') }}</td>
                        <td class="report-amount report-total">{!! format_money_pdf($statement['available_credit'], $currency) !!}</td>
                    </tr>
                    <tr class="report-total-row">
                        <td colspan="2" class="report-total">{{ __('Net account balance') }}</td>
                        <td class="report-amount report-total">{!! format_money_pdf($statement['account_balance'], $currency) !!}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
