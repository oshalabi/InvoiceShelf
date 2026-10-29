{{--
    The chrome shared by every report PDF: page skeleton, fonts, stylesheet,
    branded header with the date range, and the grand-total band at the foot.

    A report extends this and fills four sections. Three of them are single
    values and read best in the inline form:

        @extends('app.pdf.reports.partials.layout')
        @section('report-title', __('pdf_profit_loss_label'))
        @section('footer-label', __('pdf_net_profit_label'))
        @section('footer-value', format_money_pdf($net, $currency))
        @section('report-body') ... @endsection

    Everything the header needs (company, from_date, to_date) is shared by the
    report controller, so no report passes it down by hand.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <title>@yield('report-title')</title>
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
                <p class="report-date-range">{{ $from_date }} - {{ $to_date }}</p>
            </td>
        </tr>
    </table>

    <p class="report-title">@yield('report-title')</p>

    @yield('report-body')

    <div class="report-section">
        <div class="report-footer">
            <table>
                <tr>
                    <td>
                        <p class="report-footer-label">@yield('footer-label')</p>
                    </td>
                    <td>
                        <p class="report-footer-value">@yield('footer-value')</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>
