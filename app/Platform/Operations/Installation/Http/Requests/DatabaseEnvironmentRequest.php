<?php

namespace App\Platform\Operations\Installation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the wizard's database step. The shape of the form depends on the
 * chosen driver: a file-backed SQLite database needs nothing but a path,
 * whereas a server-backed one needs somewhere to connect to.
 *
 * The password is intentionally absent from both rule sets — server setups
 * with a passwordless local account must remain installable.
 */
class DatabaseEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->get('database_connection') == 'sqlite') {
            return $this->fileDatabaseRules();
        }

        return $this->serverDatabaseRules();
    }

    /**
     * SQLite: database_name carries the path to the database file.
     */
    private function fileDatabaseRules(): array
    {
        return [
            'app_url' => [
                'required',
                'url',
            ],
            'database_connection' => [
                'required',
                'string',
            ],
            'database_name' => [
                'required',
                'string',
            ],
            'database_overwrite' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * MySQL, MariaDB, PostgreSQL and anything else driver-shaped.
     */
    private function serverDatabaseRules(): array
    {
        return [
            'app_url' => [
                'required',
                'url',
            ],
            'database_connection' => [
                'required',
                'string',
            ],
            'database_hostname' => [
                'required',
                'string',
            ],
            'database_port' => [
                'required',
                'numeric',
            ],
            'database_name' => [
                'required',
                'string',
            ],
            'database_username' => [
                'required',
                'string',
            ],
            'database_overwrite' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
