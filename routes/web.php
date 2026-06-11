<?php

use App\Http\Controllers\Legacy\AdminController;
use App\Http\Controllers\Legacy\AuthController;
use App\Http\Controllers\Legacy\CompatibilityController;
use App\Http\Controllers\Legacy\DiscipleshipController;
use App\Http\Controllers\Legacy\MemberController;
use App\Http\Controllers\Legacy\PublicController;
use App\Http\Controllers\Legacy\SecureFileController;
use App\Http\Controllers\Legacy\WorshipController;
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
        Route::match(['GET', 'POST'], '/jurnal-dg', [PublicController::class, 'dgBranch'])->name('dg.branch');
        Route::match(['GET', 'POST'], '/jurnal-dg/laporan', [PublicController::class, 'dgReport'])->name('dg.report');
        Route::match(['GET', 'POST'], '/umpan-balik-anggota', [PublicController::class, 'memberFeedbackBranch'])->name('member-feedback.branch');
        Route::match(['GET', 'POST'], '/umpan-balik-anggota/form', [PublicController::class, 'memberFeedback'])->name('member-feedback.form');
        Route::match(['GET', 'POST'], '/pertanyaan-sulit/kirim', [PublicController::class, 'difficultQuestionSubmit'])->name('difficult-question.submit');
        Route::match(['GET', 'POST'], '/pertanyaan-sulit/jawaban', [PublicController::class, 'difficultAnswerLookup'])->name('difficult-question.answer');
        Route::match(['GET', 'POST'], '/menu-kosong', [PublicController::class, 'menuEmpty'])->name('menu-empty');
    });

    Route::prefix('materi')->name('materials.')->group(function (): void {
        Route::match(['GET', 'POST'], '/', [PublicController::class, 'materials'])->name('index');
        Route::match(['GET', 'POST'], '/preview', [PublicController::class, 'materialPreview'])->name('preview');
        Route::match(['GET', 'POST'], '/download', [PublicController::class, 'materialDownload'])->name('download');
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
        Route::match(['GET', 'POST'], '/target', [DiscipleshipController::class, 'targets'])->name('targets');
        Route::match(['GET', 'POST'], '/pertanyaan-sulit', [DiscipleshipController::class, 'difficultQuestions'])->name('difficult-questions');
    });

    Route::prefix('ibadah')->name('worship.')->group(function (): void {
        Route::match(['GET', 'POST'], '/penatalayan', [WorshipController::class, 'penatalayan'])->name('penatalayan');
        Route::match(['GET', 'POST'], '/penatalayan/gambar', [WorshipController::class, 'penatalayanImage'])->name('penatalayan.image');
    });

    Route::match(['GET', 'POST'], '/file-aman', [SecureFileController::class, 'show'])->name('secure-file.show');
});
