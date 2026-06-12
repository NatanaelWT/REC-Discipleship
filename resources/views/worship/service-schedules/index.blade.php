@extends('layouts.rec_app', [
    'title' => 'Penatalayan Ibadah Umum',
    'settings' => $settings,
    'currentPage' => 'worship_penatalayan',
    'showTitle' => false,
])

@section('content')
    @php
        render_condition_alerts([
            ['when' => $saved, 'tone' => 'success', 'message' => 'Jadwal penatalayan berhasil disimpan.'],
            ['when' => $deleted, 'tone' => 'success', 'message' => 'Jadwal penatalayan berhasil dihapus.'],
        ]);

        render_mapped_error_alert($errorCode, [
            'invalid_schedule' => 'Jadwal penatalayan yang dipilih tidak ditemukan.',
        ]);
    @endphp

    @include('worship.service-schedules.partials.hero')
    @include('worship.service-schedules.partials.editor')
    @include('worship.service-schedules.partials.archive')
    @include('worship.service-schedules.partials.script')
@endsection
