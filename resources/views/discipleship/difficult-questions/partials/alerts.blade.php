@if ($answered)
    <div class="alert success">Jawaban pertanyaan berhasil disimpan.</div>
@endif

@if ($errorCode !== '' && isset($errorMessages[$errorCode]))
    <div class="alert danger">{{ $errorMessages[$errorCode] }}</div>
@endif
