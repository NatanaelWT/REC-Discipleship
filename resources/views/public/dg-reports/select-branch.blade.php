@extends('layouts.rec_plain', [
    'title' => 'Pilih Cabang Jurnal Temu DG',
    'settings' => $settings,
    'bodyClass' => 'page-dg-public page-public-dg-branch',
])

@section('content')
    @if ($errorCode === 'invalid_branch')
        <div class="alert danger">Cabang yang dipilih tidak valid. Silakan pilih cabang terlebih dahulu.</div>
    @endif

    <section class="card public-branch-select-card">
      <div class="public-branch-head">
        <div class="card-row public-branch-title-row">
          <div class="public-branch-title-wrap">
            <h2>Pilih Cabang Jurnal Temu DG</h2>
            <p class="public-branch-subtitle">Pilih cabang terlebih dahulu untuk membuka Jurnal Temu DG.</p>
          </div>
          <span class="badge warning">Form Publik</span>
        </div>
        <div class="public-branch-meta" role="status" aria-live="polite">
          <span class="public-branch-count">{{ (string) $branchCount }} cabang aktif</span>
          <span class="public-branch-divider" aria-hidden="true"></span>
          <span class="public-branch-guide">Pilih cabang di bawah</span>
        </div>
      </div>
      <div class="public-branch-fieldset">
        <div class="public-branch-grid">
          @foreach ($branchOptions as $branchOption)
            @php
                $branchCode = normalize_public_branch_code((string) ($branchOption['code'] ?? ''));
                $branchLabel = trim((string) ($branchOption['label'] ?? strtoupper($branchCode)));
                if ($branchLabel === '') {
                    $branchLabel = strtoupper($branchCode);
                }
            @endphp
            @continue($branchCode === '')
            <a class="public-branch-link-card" href="{{ route('public.dg.report', ['branch' => $branchCode]) }}" aria-label="Isi laporan cabang {{ $branchLabel }}">
              <span class="public-branch-card-eyebrow">Cabang</span>
              <span class="public-branch-card-title">{{ $branchLabel }}</span>
              <span class="public-branch-card-cta">Isi Laporan <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg></span>
            </a>
          @endforeach
        </div>
      </div>
      <div class="public-branch-actions actions">
        <a class="btn ghost" href="{{ url('/') }}">Kembali</a>
      </div>
    </section>
@endsection
