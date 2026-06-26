@extends('layouts.rec_plain', [
    'title' => 'Unggah Pertanyaan Sulit',
    'settings' => $settings,
    'bodyClass' => 'page-dg-public page-public-difficult-question',
])

@section('content')
    @php
        $errorMessages = [
            'missing_question' => 'Isi pertanyaan terlebih dahulu.',
            'password_short' => 'Password minimal 4 karakter.',
            'password_mismatch' => 'Konfirmasi password tidak sama.',
            'invalid_whatsapp' => 'Nomor WhatsApp terlalu panjang.',
            'save_failed' => 'Pertanyaan gagal disimpan. Coba ulangi lagi.',
        ];
    @endphp

    @if ($submitted)
        <div class="alert success">Pertanyaan berhasil dikirim. Simpan password yang Anda buat untuk melihat jawaban nanti.</div>
    @endif

    @if ($errorCode !== '' && isset($errorMessages[$errorCode]))
        <div class="alert danger">{{ $errorMessages[$errorCode] }}</div>
    @endif

    <section class="card public-question-card">
      <div class="public-question-head">
        <span class="public-question-kicker">Pertanyaan Sulit</span>
        <h2>Unggah Pertanyaan Sulit</h2>
        <p>Buat password pribadi saat mengirim pertanyaan. Password ini dipakai untuk membuka jawaban setelah tim pengelola menjawab.</p>
      </div>
      <form method="post" action="{{ route('public.difficult-question.store') }}" class="form-grid public-question-form">
        @csrf
        <input type="hidden" name="action" value="submit_difficult_question">
        <label class="public-question-field">Nama (opsional)<input type="text" name="asker_name" maxlength="120" value="{{ (string) ($old['asker_name'] ?? '') }}" placeholder="Boleh dikosongkan"></label>
        <label class="public-question-field">Nomor WhatsApp (opsional)<input type="tel" name="asker_whatsapp" maxlength="40" inputmode="tel" autocomplete="tel" value="{{ (string) ($old['asker_whatsapp'] ?? '') }}" placeholder="08xxxxxxxxxx"></label>
        <label class="public-question-field public-question-field-full">Pertanyaan <span class="required-mark">*</span><textarea name="question_text" rows="7" maxlength="6000" required placeholder="Tulis pertanyaan yang ingin dijawab...">{{ (string) ($old['question_text'] ?? '') }}</textarea></label>
        <div class="public-question-password-panel">
          <div class="public-question-password-copy"><strong>Password Jawaban</strong><span>Simpan password ini. Password diperlukan untuk membuka jawaban Anda nanti.</span></div>
          <div class="public-question-password-fields">
            <label class="public-question-field">Password <span class="required-mark">*</span><input type="password" name="question_password" minlength="4" required autocomplete="new-password"></label>
            <label class="public-question-field">Ulangi Password <span class="required-mark">*</span><input type="password" name="question_password_confirm" minlength="4" required autocomplete="new-password"></label>
          </div>
        </div>
        <div class="form-actions public-question-actions">
          <button class="btn" type="submit">Kirim Pertanyaan</button>
          <a class="btn ghost" href="{{ route('public.difficult-question.answer') }}">Lihat Jawaban</a>
          <a class="btn ghost" href="{{ url('/') }}">Kembali</a>
        </div>
      </form>
    </section>
@endsection
