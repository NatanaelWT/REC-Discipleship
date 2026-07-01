<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsPasswordRequest;
use App\Services\Settings\SettingsPageData;
use App\Services\Settings\SettingsPasswordService;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request, SettingsPageData $pageData): View
    {
        return view('settings.index', $pageData->forRequest($request));
    }

    public function update(UpdateSettingsPasswordRequest $request, SettingsPasswordService $passwordService): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        if (function_exists('is_developer_access_mode') && is_developer_access_mode()) {
            return redirect()->route('settings', ['error' => 'developer_access_password_disabled']);
        }

        $error = $passwordService->updatePassword(
            current_username(),
            (string) $request->input('current_password', ''),
            (string) $request->input('new_password', ''),
        );

        if ($error !== null) {
            return redirect()->route('settings', ['error' => $error]);
        }

        return redirect()->route('settings', ['pw_changed' => 1]);
    }
}
