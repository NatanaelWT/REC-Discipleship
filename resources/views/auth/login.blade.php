@extends('layouts.rec_plain', [
    'title' => 'Login',
    'settings' => $settings,
    'bodyClass' => 'page-login',
])

@section('content')
    @php
        $churchName = trim((string) ($settings['church_name'] ?? app_church_name()));
        if ($churchName === '') {
            $churchName = app_church_name();
        }
        $loginPillars = [
            [
                'eyebrow' => 'Firman',
                'title' => 'Berakar pada Injil',
                'description' => 'Setiap proses administrasi mendukung pertumbuhan rohani dan pengajaran yang sehat.',
            ],
            [
                'eyebrow' => 'Komunitas',
                'title' => 'Bertumbuh bersama',
                'description' => 'Data, jurnal, dan koordinasi pelayanan disatukan untuk membangun kehidupan bergereja.',
            ],
            [
                'eyebrow' => 'Pelayanan',
                'title' => 'Tertib dan siap pakai',
                'description' => 'Portal ini membantu tim bekerja rapi agar fokus pelayanan tetap terjaga.',
            ],
        ];
    @endphp

    @if ($errorCode === 'locked')
        @php render_alert('danger', 'Terlalu banyak percobaan login. Coba lagi dalam ' . format_lock_wait_label($waitSeconds) . '.'); @endphp
    @elseif ($errorCode !== '')
        @php render_alert('danger', 'Username atau password salah.'); @endphp
    @elseif ($expired)
        @php render_alert('danger', 'Sesi login berakhir karena tidak aktif. Silakan login kembali.'); @endphp
    @elseif ($accountRemoved)
        @php render_alert('danger', 'Akun ini sudah tidak aktif. Silakan login dengan akun yang tersedia.'); @endphp
    @endif

    <section class="login-shell">
      <aside class="login-brand-panel">
        <div class="login-brand-top">
          <span class="login-brand-tag">Portal Internal REC</span>
          <span class="login-brand-tag is-soft">REC Indonesia</span>
        </div>
        <div class="login-brand-hero">
          <div class="login-brand-logo-wrap">
            <img src="/assets/logo.png" alt="Logo {{ $churchName }}" decoding="async">
          </div>
          <div class="login-brand-copy">
            <p class="login-brand-kicker">{{ $churchName }}</p>
            <h1>Administrasi yang tertib untuk menopang pemuridan.</h1>
            <p>Gunakan portal ini untuk mengelola pemuridan, jurnal pertemuan, dan kebutuhan pelayanan REC secara rapi.</p>
          </div>
        </div>
        <div class="login-brand-grid">
          @foreach ($loginPillars as $pillar)
            <article class="login-brand-pillar">
              @if (trim((string) ($pillar['eyebrow'] ?? '')) !== '')
                <span class="login-brand-pillar-eyebrow">{{ $pillar['eyebrow'] }}</span>
              @endif
              @if (trim((string) ($pillar['title'] ?? '')) !== '')
                <strong class="login-brand-pillar-title">{{ $pillar['title'] }}</strong>
              @endif
              @if (trim((string) ($pillar['description'] ?? '')) !== '')
                <p>{{ $pillar['description'] }}</p>
              @endif
            </article>
          @endforeach
        </div>
        <div class="login-brand-footer">
          <span class="login-brand-badge">Komunitas</span>
          <span class="login-brand-badge">Pemuridan</span>
          <span class="login-brand-badge">Pelayanan</span>
        </div>
      </aside>
      <section class="card login-card">
        <div class="login-head">
          <div class="login-eyebrow">Akses Admin</div>
          <div class="login-title">Masuk</div>
          <div class="login-sub">Gunakan akun untuk mengakses dashboard internal, data pemuridan, dan modul pelayanan REC.</div>
        </div>
        <form method="post" action="{{ route('auth.login.store') }}" class="form-grid login-form">
          @csrf
          <input type="hidden" name="action" value="login">
          <label class="login-field">Username<input type="text" name="username" required autocomplete="username" placeholder="Masukkan username" autofocus spellcheck="false"></label>
          <label class="login-field">Password<input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password"></label>
          <div class="form-actions login-actions">
            <button class="btn" type="submit">Masuk</button>
            <a class="btn ghost" href="{{ route('home', [], false) }}">Kembali</a>
          </div>
          <p class="login-note">Halaman ini ditujukan untuk akun internal yang sudah terdaftar.</p>
        </form>
      </section>
    </section>
@endsection
