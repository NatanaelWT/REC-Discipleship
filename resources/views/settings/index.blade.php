@extends('layouts.rec_app', [
    'title' => 'Pengaturan',
    'settings' => $settings,
    'currentPage' => 'settings',
    'bodyClass' => 'page-settings',
])

@section('content')
    @include('settings.partials.alerts')

    @php
      $passwordDisabled = ($centralReadOnly ?? false) || ($developerAccessMode ?? false);
    @endphp

    <section class="card settings-account-card">
      <div class="settings-account-hero">
        <div class="settings-account-copy">
          <span class="settings-account-kicker">Akun</span>
          <h2>Kelola Password</h2>
          <p>Kunci akun kamu agar tetap aman. Gunakan kombinasi huruf, angka, dan simbol untuk password yang kuat.</p>
        </div>
        <div class="settings-account-meta">
          <span class="settings-account-badge">Username: {{ $currentUsername }}</span>
          @if ($developerAccessMode ?? false)
            <span class="settings-account-badge is-muted">Mode akses developer · password dikunci</span>
          @elseif ($centralReadOnly)
            <span class="settings-account-badge is-muted">Pusat Pemuridan · hanya lihat</span>
          @else
            <span class="settings-account-badge is-safe">Data terenkripsi</span>
          @endif
        </div>
      </div>

      <form method="post" action="{{ route('settings.update') }}" class="settings-account-form">
        @csrf
        <div class="settings-account-grid">
          <label class="settings-account-field-card is-current">
            <span class="settings-account-field-top">
              <span class="settings-account-field-eyebrow">Langkah 1</span>
              <span class="settings-account-field-preview">Rahasiakan</span>
            </span>
            <span class="settings-account-field-title">Password Sekarang</span>
            <span class="settings-account-field-hint">Masukkan password yang kamu pakai saat ini.</span>
            <input type="password" name="current_password" autocomplete="current-password" required @if ($developerAccessMode ?? false) disabled aria-disabled="true" @endif>
          </label>

          <label class="settings-account-field-card is-new">
            <span class="settings-account-field-top">
              <span class="settings-account-field-eyebrow">Langkah 2</span>
              <span class="settings-account-field-preview">6+ karakter</span>
            </span>
            <span class="settings-account-field-title">Password Baru</span>
            <span class="settings-account-field-hint">Gunakan kombinasi huruf besar/kecil, angka, dan simbol.</span>
            <input type="password" name="new_password" autocomplete="new-password" required minlength="6" @if ($developerAccessMode ?? false) disabled aria-disabled="true" @endif>
          </label>

          <label class="settings-account-field-card is-confirm">
            <span class="settings-account-field-top">
              <span class="settings-account-field-eyebrow">Langkah 3</span>
              <span class="settings-account-field-preview">Cocok</span>
            </span>
            <span class="settings-account-field-title">Konfirmasi Password Baru</span>
            <span class="settings-account-field-hint">Ketik ulang untuk memastikan tidak ada salah ketik.</span>
            <input type="password" name="new_password_confirm" autocomplete="new-password" required minlength="6" @if ($developerAccessMode ?? false) disabled aria-disabled="true" @endif>
          </label>
        </div>

        <div class="settings-account-actions">
          <button class="btn" type="submit" @if ($passwordDisabled) disabled aria-disabled="true" @endif>Ubah Password</button>
        </div>
      </form>
    </section>
@endsection
