@if ($pwChanged)
    <div class="alert success">Password berhasil diubah.</div>
@elseif ($errorCode !== '' && isset($errorMessages[$errorCode]))
    <div class="alert danger">{{ $errorMessages[$errorCode] }}</div>
@endif
