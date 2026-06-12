<?php

use App\Http\Controllers\Legacy\AdminController;
use App\Http\Controllers\Legacy\AuthController;
use App\Http\Controllers\Legacy\CompatibilityController;
use App\Http\Controllers\Legacy\DiscipleshipController;
use App\Http\Controllers\Legacy\MemberController;
use App\Http\Controllers\Legacy\PublicController;
use App\Http\Controllers\Legacy\SecureFileController;
use App\Http\Controllers\Legacy\WorshipController;
use App\Http\Controllers\PublicPortal\DgMeetingReportController as PublicDgMeetingReportController;
use App\Http\Controllers\PublicPortal\DgReportBranchController as PublicDgReportBranchController;
use App\Http\Controllers\PublicPortal\DifficultAnswerController as PublicDifficultAnswerController;
use App\Http\Controllers\PublicPortal\DifficultQuestionController as PublicDifficultQuestionController;
use App\Http\Controllers\Discipleship\DifficultQuestionController as DiscipleshipDifficultQuestionController;
use App\Http\Controllers\Discipleship\TargetController as DiscipleshipTargetController;
use App\Http\Controllers\PublicPortal\MaterialController as PublicMaterialController;
use App\Http\Controllers\PublicPortal\MaterialDownloadController as PublicMaterialDownloadController;
use App\Http\Controllers\PublicPortal\MaterialPreviewController as PublicMaterialPreviewController;
use App\Http\Controllers\PublicPortal\MemberFeedbackJournalController as PublicMemberFeedbackJournalController;
use App\Http\Controllers\Worship\ServiceScheduleController as WorshipServiceScheduleController;
use App\Http\Controllers\Worship\ServiceScheduleImageController as WorshipServiceScheduleImageController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

$legacyMiddleware = [
    ValidateCsrfToken::class,
    StartSession::class,
    ShareErrorsFromSession::class,
];

Route::withoutMiddleware($legacyMiddleware)->group(function (): void {
    Route::match(['GET', 'POST'], '/', [PublicController::class, 'home'])->name('home');
    Route::match(['GET', 'POST'], '/index.php', CompatibilityController::class)->name('legacy.compat');

    Route::match(['GET', 'POST'], '/login', [AuthController::class, 'login'])->name('auth.login');
    Route::match(['GET', 'POST'], '/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::match(['GET', 'POST'], '/pengaturan', [AdminController::class, 'settings'])->name('settings');

    Route::prefix('publik')->name('public.')->group(function (): void {
        Route::get('/jurnal-dg', [PublicDgReportBranchController::class, 'index'])->name('dg.branch');
        Route::get('/jurnal-dg/laporan', [PublicDgMeetingReportController::class, 'legacy'])->name('dg.report.legacy');
        Route::post('/jurnal-dg/laporan', [PublicDgMeetingReportController::class, 'store'])->name('dg.report.legacy-store');
        Route::get('/jurnal-dg/{branch}/laporan', [PublicDgMeetingReportController::class, 'create'])
            ->where('branch', '[A-Za-z0-9_-]+')
            ->name('dg.report');
        Route::post('/jurnal-dg/{branch}/laporan', [PublicDgMeetingReportController::class, 'store'])
            ->where('branch', '[A-Za-z0-9_-]+')
            ->name('dg.report.store');
        Route::match(['GET', 'POST'], '/umpan-balik-anggota', [PublicMemberFeedbackJournalController::class, 'selectBranch'])->name('member-feedback.branch');
        Route::get('/umpan-balik-anggota/form', [PublicMemberFeedbackJournalController::class, 'create'])->name('member-feedback.form');
        Route::post('/umpan-balik-anggota/form', [PublicMemberFeedbackJournalController::class, 'store'])->name('member-feedback.store');
        Route::get('/pertanyaan-sulit/kirim', [PublicDifficultQuestionController::class, 'create'])->name('difficult-question.submit');
        Route::post('/pertanyaan-sulit/kirim', [PublicDifficultQuestionController::class, 'store'])->name('difficult-question.store');
        Route::get('/pertanyaan-sulit/jawaban', [PublicDifficultAnswerController::class, 'show'])->name('difficult-question.answer');
        Route::post('/pertanyaan-sulit/jawaban', [PublicDifficultAnswerController::class, 'lookup'])->name('difficult-question.lookup');
        Route::match(['GET', 'POST'], '/menu-kosong', [PublicController::class, 'menuEmpty'])->name('menu-empty');
    });

    Route::prefix('materi')->name('materials.')->group(function (): void {
        Route::match(['GET', 'POST'], '/', [PublicMaterialController::class, 'legacyIndex'])->name('index');
        Route::match(['GET', 'POST'], '/preview', [PublicMaterialPreviewController::class, 'legacy'])->name('preview.legacy');
        Route::match(['GET', 'POST'], '/download', [PublicMaterialDownloadController::class, 'legacy'])->name('download.legacy');
        Route::get('/{menu}', [PublicMaterialController::class, 'show'])->name('show');
        Route::get('/{menu}/{churchFile}/preview', [PublicMaterialPreviewController::class, 'show'])->name('preview');
        Route::get('/{menu}/{churchFile}/download', [PublicMaterialDownloadController::class, 'download'])->name('download');
    });

    Route::prefix('jemaat')->name('members.')->group(function (): void {
        Route::match(['GET', 'POST'], '/dashboard', [MemberController::class, 'dashboard'])->name('dashboard');
        Route::match(['GET', 'POST'], '/data', [MemberController::class, 'index'])->name('index');
        Route::match(['GET', 'POST'], '/kelengkapan', [MemberController::class, 'completeness'])->name('completeness');
        Route::match(['GET', 'POST'], '/keluarga', [MemberController::class, 'families'])->name('families');
        Route::match(['GET', 'POST'], '/ulang-tahun', [MemberController::class, 'birthdays'])->name('birthdays');
    });

    Route::prefix('pemuridan')->name('discipleship.')->group(function (): void {
        Route::match(['GET', 'POST'], '/dashboard', [DiscipleshipController::class, 'dashboard'])->name('dashboard');
        Route::match(['GET', 'POST'], '/kelompok', [DiscipleshipController::class, 'groups'])->name('groups');
        Route::match(['GET', 'POST'], '/orang', [DiscipleshipController::class, 'people'])->name('people');
        Route::match(['GET', 'POST'], '/anggota', [DiscipleshipController::class, 'peopleList'])->name('people-list');
        Route::match(['GET', 'POST'], '/pohon', [DiscipleshipController::class, 'tree'])->name('tree');
        Route::match(['GET', 'POST'], '/pohon-v2', [DiscipleshipController::class, 'treeV2'])->name('tree-v2');
        Route::match(['GET', 'POST'], '/spiritual-journey', [DiscipleshipController::class, 'spiritualJourney'])->name('spiritual-journey');
        Route::match(['GET', 'POST'], '/laporan-dg', [DiscipleshipController::class, 'reportsRecap'])->name('reports-recap');
        Route::match(['GET', 'POST'], '/msk', [DiscipleshipController::class, 'mskClasses'])->name('msk-classes');
        Route::get('/target', [DiscipleshipTargetController::class, 'index'])->name('targets');
        Route::post('/target', [DiscipleshipTargetController::class, 'update'])->name('targets.update');
        Route::get('/pertanyaan-sulit', [DiscipleshipDifficultQuestionController::class, 'index'])->name('difficult-questions');
        Route::post('/pertanyaan-sulit', [DiscipleshipDifficultQuestionController::class, 'answerLegacy'])->name('difficult-questions.answer-legacy');
        Route::post('/pertanyaan-sulit/{difficultQuestion}/jawaban', [DiscipleshipDifficultQuestionController::class, 'answer'])->name('difficult-questions.answer');
    });

    Route::prefix('ibadah')->name('worship.')->group(function (): void {
        Route::get('/penatalayan', [WorshipServiceScheduleController::class, 'index'])->name('penatalayan');
        Route::post('/penatalayan', [WorshipServiceScheduleController::class, 'store'])->name('penatalayan.store');
        Route::match(['GET', 'POST'], '/penatalayan/gambar', [WorshipServiceScheduleImageController::class, 'download'])->name('penatalayan.image');
        Route::delete('/penatalayan/{month}', [WorshipServiceScheduleController::class, 'destroy'])
            ->where('month', '[0-9]{4}-[0-9]{2}')
            ->name('penatalayan.destroy');
    });

    Route::match(['GET', 'POST'], '/file-aman', [SecureFileController::class, 'show'])->name('secure-file.show');
});
