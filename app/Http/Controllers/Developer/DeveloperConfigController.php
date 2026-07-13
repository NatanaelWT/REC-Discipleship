<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\AppConfig\AppConfigService;
use App\Services\Developer\DeveloperDiagnosticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeveloperConfigController extends Controller
{
    public function index(
        Request $request,
        AppConfigService $config,
        DeveloperDiagnosticsService $diagnostics,
    ): RedirectResponse|View {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $developerDiagnostics = $diagnostics->summary();

        return view('developer.config', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_config',
            'configValues' => $config->values(),
            'runtime' => $developerDiagnostics['runtime'] ?? [],
            'timezoneOptions' => $config->timezoneOptions(),
            'statusCode' => trim((string) $request->query('status', '')),
            'errorCode' => trim((string) $request->query('error', '')),
            'errorMessages' => [
                'config_table_missing' => 'Tabel config belum tersedia. Jalankan migration.',
                'church_name_required' => 'Nama gereja wajib diisi.',
                'timezone_invalid' => 'Timezone tidak valid.',
            ],
        ]);
    }

    public function update(Request $request, AppConfigService $config): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $error = $config->update($request->only([
            'church_name',
            'app_timezone',
            'developer_debug_banner',
            'maintenance_mode',
        ]), current_username());

        if ($error !== null) {
            return redirect()->route('developer.config', ['error' => $error]);
        }

        return redirect()->route('developer.config', ['status' => 'saved']);
    }

    private function guard(Request $request): ?RedirectResponse
    {
        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }
        if (! can_manage_app_config()) {
            abort(403, 'Akses developer diperlukan.');
        }

        return null;
    }
}
