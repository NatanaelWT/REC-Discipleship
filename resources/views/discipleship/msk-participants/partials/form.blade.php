@php
    $participant = is_array($participant ?? null) ? $participant : [];
    $batchMonth = (string) ($batchMonth ?? '');
    $closeActionAttr = (string) ($closeActionAttr ?? '');
    $mskStoreAction = (string) ($mskStoreAction ?? route('discipleship.msk-classes.store'));

    $participantId = trim((string) ($participant['id'] ?? ''));
    $fullName = trim((string) ($participant['full_name'] ?? ''));
    $gender = normalize_member_gender_value((string) ($participant['gender'] ?? ''));
    $birthDate = normalize_ymd_date((string) ($participant['birth_date'] ?? ''));
    $whatsapp = trim((string) ($participant['whatsapp'] ?? ''));
    $birthPlace = trim((string) ($participant['birth_place'] ?? ''));
    $address = trim((string) ($participant['address'] ?? ''));
    $email = strtolower(trim((string) ($participant['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
    }
    $notes = trim((string) ($participant['notes'] ?? ''));
    $mskMonth = import_normalize_month_strict((string) ($participant['msk_month'] ?? ''));
    if ($participantId === '' && $mskMonth === '') {
        $mskMonth = import_normalize_month_strict($batchMonth);
    }
    if ($participantId === '' && $mskMonth === '') {
        $mskMonth = date('Y-m');
    }
    $mskMonthBadgeLabel = $mskMonth !== '' ? format_indo_month($mskMonth) : 'Belum dipilih';

    $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
    $sessionMap = [];
    foreach ($sessionNumbers as $sessionNumber) {
        $sessionMap[(string) $sessionNumber] = true;
    }

    $photos = [];
    foreach (extract_msk_participant_photos($participant) as $photo) {
        $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
        if ($photoPath === '') {
            continue;
        }
        $photoName = trim((string) ($photo['name'] ?? '')) ?: 'Foto';
        $photos[] = ['path' => $photoPath, 'name' => $photoName];
    }

    $sessionCount = count($sessionMap);
    $progressPercent = max(0, min(100, (int) round(($sessionCount / 12) * 100)));
    $statusLabel = 'Belum';
    $statusClass = 'is-pending';
    if ($sessionCount === 12) {
        $statusLabel = 'Selesai';
        $statusClass = 'is-complete';
    } elseif ($sessionCount > 0) {
        $statusLabel = 'Proses';
        $statusClass = 'is-progress';
    }
    $formActionsClass = $closeActionAttr !== '' ? 'form-actions msk-form-actions is-right' : 'form-actions';
@endphp

<form method="post" action="{{ $mskStoreAction }}" enctype="multipart/form-data" class="form-grid msk-participant-form" data-msk-form>
  @csrf
  <input type="hidden" name="action" value="save_msk_participant">
  <input type="hidden" name="id" value="{{ $participantId }}">
  <input type="hidden" name="return_batch_month" value="{{ $batchMonth }}">
  <section class="msk-form-banner msk-form-full">
    <div class="msk-form-banner-copy">
      <span class="msk-form-banner-kicker">Peserta MSK</span>
      <h3>Lengkapi data peserta dengan rapi</h3>
      <p>Gunakan form ini untuk menyimpan identitas, kontak, batch MSK, dan progres sesi dalam satu alur yang ringkas.</p>
    </div>
    <div class="msk-form-banner-meta">
      <span class="msk-form-badge">Batch: {{ $mskMonthBadgeLabel }}</span>
      <span class="msk-form-badge is-status {{ $statusClass }}">{{ $statusLabel }} - {{ (string) $sessionCount }}/12 sesi - {{ (string) $progressPercent }}%</span>
    </div>
  </section>

  <section class="msk-form-section msk-form-full">
    <div class="msk-form-section-head">
      <div>
        <span class="msk-form-section-kicker">Identitas</span>
        <h3>Data dasar peserta</h3>
      </div>
      <p>Isi identitas utama peserta.</p>
    </div>
    <div class="msk-form-section-grid">
      <label class="msk-form-field"><span class="msk-form-field-label">Nama Peserta</span><input type="text" name="full_name" value="{{ $fullName }}" placeholder="Nama lengkap" data-msk-name-input></label>
      <label class="msk-form-field"><span class="msk-form-field-label">Jenis Kelamin</span><select name="gender" data-msk-gender><option value="">- Pilih -</option><option value="Laki-laki" @selected($gender === 'Laki-laki')>Laki-laki</option><option value="Perempuan" @selected($gender === 'Perempuan')>Perempuan</option></select></label>
      <label class="msk-form-field"><span class="msk-form-field-label">Tanggal Lahir</span><input type="date" name="birth_date" value="{{ $birthDate }}" data-msk-birth-date></label>
      <label class="msk-form-field"><span class="msk-form-field-label">Tempat Lahir</span><input type="text" name="birth_place" value="{{ $birthPlace }}" placeholder="Kota lahir" data-msk-birth-place></label>
    </div>
  </section>

  <section class="msk-form-section msk-form-full">
    <div class="msk-form-section-head">
      <div>
        <span class="msk-form-section-kicker">Kontak</span>
        <h3>Kontak dan lampiran</h3>
      </div>
      <p>Simpan alamat, nomor WhatsApp, email, dan lampiran foto peserta di area yang sama.</p>
    </div>
    <div class="msk-form-section-grid">
      <label class="msk-form-field is-wide"><span class="msk-form-field-label">Alamat</span><textarea name="address" rows="3" placeholder="Alamat domisili" data-msk-address>{{ $address }}</textarea></label>
      <label class="msk-form-field"><span class="msk-form-field-label">Email</span><input type="email" name="email" value="{{ $email }}" placeholder="email@contoh.com" data-msk-email></label>
      <label class="msk-form-field"><span class="msk-form-field-label">Nomor WhatsApp</span><input type="text" name="whatsapp" value="{{ $whatsapp }}" placeholder="08xxxxxxxxxx" data-msk-whatsapp></label>
      <label class="msk-form-field is-upload is-wide"><span class="msk-form-field-label">Upload Foto Peserta</span><input type="file" name="participant_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple><span class="msk-form-field-hint">JPG, PNG, atau WEBP. Bisa pilih lebih dari satu file.</span></label>
      @if (count($photos) > 0)
        <div class="msk-form-meta-card is-wide">
          <div class="member-photo-list msk-photo-list">
            <div class="member-photo-current">Foto saat ini</div>
            @foreach ($photos as $idx => $photo)
              @php
                  $photoPath = (string) ($photo['path'] ?? '');
                  $photoUrl = secure_upload_url($photoPath);
                  $photoLabel = trim((string) ($photo['name'] ?? '')) ?: ('Foto '.(string) ($idx + 1));
              @endphp
              @if ($photoUrl !== '')
                <div class="member-photo-item">
                  <a class="note-link" href="{{ $photoUrl }}" target="_blank" rel="noopener">{{ $photoLabel }}</a>
                  <label class="check-label"><input type="checkbox" name="remove_photo_paths[]" value="{{ $photoPath }}">Hapus</label>
                </div>
              @endif
            @endforeach
          </div>
        </div>
      @endif
    </div>
  </section>

  <section class="msk-form-section msk-form-full">
    <div class="msk-form-section-head">
      <div>
        <span class="msk-form-section-kicker">Progress</span>
        <h3>Batch dan progres sesi</h3>
      </div>
      <p>Atur batch MSK peserta, isi keterangan jika perlu, lalu tandai sesi yang sudah selesai.</p>
    </div>
    <div class="msk-form-section-grid">
      <label class="msk-form-field"><span class="msk-form-field-label">Bulan-Tahun MSK Diikuti</span><input type="month" name="batch_month" value="{{ $mskMonth }}" required></label>
      <label class="msk-form-field is-wide"><span class="msk-form-field-label">Keterangan</span><textarea name="notes" rows="3" placeholder="Catatan peserta...">{{ $notes }}</textarea></label>
    </div>
  </section>

  <fieldset class="dg-checklist msk-session-checklist msk-progress-fieldset msk-form-full">
    <legend>Checklist 12 Sesi MSK</legend>
    <div class="msk-session-grid">
      @for ($session = 1; $session <= 12; $session++)
        <label class="check-label"><input type="checkbox" name="session_numbers[]" value="{{ (string) $session }}" @checked(isset($sessionMap[(string) $session]))>Sesi {{ (string) $session }}</label>
      @endfor
    </div>
  </fieldset>

  <div class="{{ $formActionsClass }}">
    <button class="btn" type="submit">Simpan Peserta MSK</button>
    @if ($closeActionAttr === '')
      <a class="btn ghost" href="{{ route('discipleship.msk-classes', ['batch_month' => $batchMonth]) }}">Batal</a>
    @endif
  </div>
</form>
