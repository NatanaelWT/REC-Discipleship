<?php

namespace App\Http\Controllers\Legacy\Concerns;

use App\Services\Legacy\LegacyRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

trait RendersLegacyPages
{
    public function __construct(private readonly LegacyRenderer $renderer)
    {
    }

    protected function legacy(Request $request, string $page): Response
    {
        return $this->renderer->render($request, $page);
    }
}
