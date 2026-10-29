<?php

namespace App\Platform\Pdf\Application;

use App\Platform\Operations\Models\Setting;
use App\Platform\Pdf\Contracts\PdfConfigurator;
use Illuminate\Support\Facades\Config;

class PdfConfigurationService implements PdfConfigurator
{
    /** @var list<string> */
    private const PAGE_SETTINGS = [
        'pdf_paper_width',
        'pdf_paper_height',
        'pdf_orientation',
        'pdf_margin_top',
        'pdf_margin_right',
        'pdf_margin_bottom',
        'pdf_margin_left',
    ];

    /** @var list<string> */
    private const PAGE_BOOLEANS = [
        'pdf_page_numbers',
    ];

    /**
     * Apply the persisted instance-wide PDF settings to Laravel's runtime config.
     */
    public function applyGlobalConfig(): void
    {
        $pageSettings = collect(self::PAGE_SETTINGS)
            ->mapWithKeys(fn (string $setting): array => [$setting => self::configKeyFor($setting)])
            ->all();

        $pdfSettings = Setting::getSettings(array_merge(
            ['pdf_driver', 'gotenberg_host', 'gotenberg_pdfa'],
            self::PAGE_BOOLEANS,
            array_keys($pageSettings),
        ));

        if (! empty($pdfSettings['pdf_driver'])) {
            Config::set('pdf.driver', $pdfSettings['pdf_driver']);

            if ($pdfSettings['pdf_driver'] === 'gotenberg') {
                if (! empty($pdfSettings['gotenberg_host'])) {
                    Config::set('pdf.connections.gotenberg.host', $pdfSettings['gotenberg_host']);
                }

                // An explicitly stored blank means an ordinary PDF and must
                // override an environment-level PDF/A default.
                if (isset($pdfSettings['gotenberg_pdfa'])) {
                    Config::set('pdf.connections.gotenberg.pdfa', $pdfSettings['gotenberg_pdfa'] ?: null);
                }
            }
        }

        foreach ($pageSettings as $setting => $configKey) {
            if (isset($pdfSettings[$setting]) && trim((string) $pdfSettings[$setting]) !== '') {
                Config::set($configKey, $pdfSettings[$setting]);
            }
        }

        if (isset($pdfSettings['pdf_page_numbers'])) {
            Config::set(
                'pdf.page.page_numbers',
                filter_var($pdfSettings['pdf_page_numbers'], FILTER_VALIDATE_BOOLEAN)
            );
        }
    }

    /**
     * @return array<string, bool|string|null>
     */
    public function environment(): array
    {
        $pdfSettings = Setting::getSettings(array_merge(
            ['pdf_driver', 'gotenberg_host', 'gotenberg_pdfa'],
            self::PAGE_SETTINGS,
            self::PAGE_BOOLEANS,
        ));

        $environment = [
            'pdf_driver' => $pdfSettings['pdf_driver'] ?? config('pdf.driver'),
            'gotenberg_host' => $pdfSettings['gotenberg_host'] ?? config('pdf.connections.gotenberg.host'),
            'gotenberg_pdfa' => $pdfSettings['gotenberg_pdfa'] ?? config('pdf.connections.gotenberg.pdfa') ?? '',
        ];

        foreach (self::PAGE_SETTINGS as $setting) {
            $environment[$setting] = $pdfSettings[$setting] ?? config(self::configKeyFor($setting));
        }

        foreach (self::PAGE_BOOLEANS as $setting) {
            $environment[$setting] = filter_var(
                $pdfSettings[$setting] ?? config(self::configKeyFor($setting)),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        return $environment;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): void
    {
        $driver = $input['pdf_driver'];
        $settings = ['pdf_driver' => $driver];

        foreach (self::PAGE_SETTINGS as $setting) {
            $settings[$setting] = $input[$setting] ?? null;
        }

        // Page numbers are a Gotenberg-only control. When the dompdf form omits
        // it, retain the operator's existing choice.
        foreach (self::PAGE_BOOLEANS as $setting) {
            if (array_key_exists($setting, $input)) {
                $settings[$setting] = filter_var($input[$setting], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }
        }

        if ($driver === 'gotenberg') {
            $settings['gotenberg_host'] = $input['gotenberg_host'];
            $settings['gotenberg_pdfa'] = $input['gotenberg_pdfa'] ?? '';
        }

        Setting::setSettings($settings);
    }

    private static function configKeyFor(string $setting): string
    {
        return 'pdf.page.'.substr($setting, strlen('pdf_'));
    }
}
