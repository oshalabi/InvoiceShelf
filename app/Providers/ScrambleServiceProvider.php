<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\ServiceProvider;

/**
 * Configures Scramble's OpenAPI generation for the InvoiceShelf API.
 *
 * Scramble is a dev-only dependency used to generate the spec in CI/local (see
 * `php artisan scramble:export`); it is absent in production (composer install
 * --no-dev). Every reference to it is therefore guarded by class_exists().
 */
class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::configure()
            ->withDocumentTransformers($this->documentTransformer(...))
            ->withOperationTransformers($this->operationTransformer(...));
    }

    /**
     * Advertise Bearer (Sanctum personal access token) auth as the global
     * security scheme. The customer-portal guard (auth:customer) is session-based
     * and not part of the token API surface, so it is intentionally not exposed.
     */
    protected function documentTransformer(OpenApi $openApi): void
    {
        $openApi->secure(SecurityScheme::http('bearer'));
    }

    /**
     * Add the multi-tenancy `company` header to every company-scoped operation.
     * CompanyMiddleware (alias `company`) resolves the active tenant from this
     * header, so only routes that actually run it get the parameter — auth, ping,
     * and installation endpoints stay clean.
     */
    protected function operationTransformer(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! in_array('company', $routeInfo->route->gatherMiddleware(), true)) {
            return;
        }

        $operation->addParameters([
            Parameter::make('company', 'header')
                ->description('ID of the company the request operates on (multi-tenancy).')
                ->setSchema(Schema::fromType(new StringType))
                ->required(true),
        ]);
    }
}
