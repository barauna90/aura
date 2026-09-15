<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EssayController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NotebookController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StudyPlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// ---------- Público ----------
Route::get('/', LandingController::class)->name('landing');
Route::get('/r/{code}', [ReferralController::class, 'landing'])->name('referral.landing');
Route::post('/webhooks/asaas', [WebhookController::class, 'asaas'])->name('webhooks.asaas');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/cadastro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/cadastro', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/recuperar-senha', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/recuperar-senha', [AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/redefinir-senha/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/redefinir-senha', [AuthController::class, 'reset'])->name('password.update');
});

// ---------- Área do aluno ----------
Route::middleware('auth')->group(function () {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');

    Route::get('/inicio', DashboardController::class)->name('dashboard');
    Route::get('/onboarding', [ProfileController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding', [ProfileController::class, 'storeOnboarding']);

    Route::get('/provas', [ExamController::class, 'index'])->name('exams.index');
    Route::get('/provas/{exam}', [ExamController::class, 'show'])->name('exams.show');
    Route::post('/provas/{exam}/iniciar', [ExamController::class, 'start'])->name('exams.start');
    Route::get('/cadernos/{booklet}/pdf', [ExamController::class, 'pdf'])->name('booklets.pdf');
    Route::view('/simulados', 'app.simulados')->name('simulados');

    Route::prefix('sessao/{session}')->name('sessions.')->group(function () {
        Route::get('/', [SessionController::class, 'show'])->name('show');
        Route::post('/iniciar', [SessionController::class, 'start'])->name('start');
        Route::get('/estado', [SessionController::class, 'state'])->name('state');
        Route::post('/respostas', [SessionController::class, 'answers'])->name('answers');
        Route::post('/pausar', [SessionController::class, 'pause'])->name('pause');
        Route::post('/continuar', [SessionController::class, 'resume'])->name('resume');
        Route::post('/encerrar', [SessionController::class, 'finish'])->name('finish');
        Route::get('/resultado', [SessionController::class, 'result'])->name('result');
    });

    Route::get('/redacao', [EssayController::class, 'index'])->name('essays.index');
    Route::post('/redacao/proposta/{prompt}', [EssayController::class, 'startFromPrompt'])->name('essays.from_prompt');
    Route::post('/redacao/sessao/{session}', [EssayController::class, 'startFromSession'])->name('essays.from_session');
    Route::get('/redacao/{essay}', [EssayController::class, 'edit'])->name('essays.edit');
    Route::put('/redacao/{essay}/rascunho', [EssayController::class, 'draft'])->name('essays.draft');
    Route::post('/redacao/{essay}/enviar', [EssayController::class, 'submit'])->name('essays.submit');
    Route::get('/redacao/{essay}/relatorio', [EssayController::class, 'report'])->name('essays.report');

    Route::get('/plano', [StudyPlanController::class, 'index'])->name('study.plan');
    Route::post('/plano/gerar', [StudyPlanController::class, 'generate'])->name('study.generate');
    Route::patch('/plano/tarefas/{task}', [StudyPlanController::class, 'updateTask'])->name('study.task');
    Route::post('/plano/metas', [StudyPlanController::class, 'storeGoal'])->name('study.goal');
    Route::delete('/plano/metas/{goal}', [StudyPlanController::class, 'destroyGoal'])->name('study.goal.destroy');

    Route::get('/caderno-de-erros', [NotebookController::class, 'index'])->name('notebook.index');
    Route::put('/caderno-de-erros/{entry}/nota', [NotebookController::class, 'note'])->name('notebook.note');
    Route::post('/caderno-de-erros/{entry}/revisar', [NotebookController::class, 'review'])->name('notebook.review');

    Route::get('/desempenho', [PerformanceController::class, 'index'])->name('performance');
    Route::get('/desempenho/comparar', [PerformanceController::class, 'compare'])->name('performance.compare');
    Route::get('/guia', GuideController::class)->name('guide');

    Route::get('/indique', [ReferralController::class, 'index'])->name('referral.index');
    Route::post('/indique/saque', [ReferralController::class, 'withdraw'])->name('referral.withdraw');

    Route::get('/assinatura', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/assinatura/cupom', [SubscriptionController::class, 'checkCoupon'])->name('subscription.coupon');
    Route::post('/assinatura/assinar', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/assinatura/pagamento/{payment}', [SubscriptionController::class, 'payment'])->name('subscription.payment');
    Route::post('/assinatura/cancelar', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/perfil/exportar', [ProfileController::class, 'export'])->name('profile.export');
    Route::delete('/perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/ajuda', [HelpController::class, 'index'])->name('help');
    Route::post('/ajuda/professor', [HelpController::class, 'ask'])->name('help.ask');
});

// ---------- Administração ----------
Route::middleware(['auth', 'role:REVIEWER'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/conteudo', [Admin\ContentController::class, 'index'])->name('content.index');
    Route::get('/conteudo/{exam}', [Admin\ContentController::class, 'show'])->name('content.show');
    Route::get('/conteudo/{exam}/validar', [Admin\ContentController::class, 'validate'])->name('content.validate');
    Route::post('/conteudo/{exam}/etapa', [Admin\ContentController::class, 'advance'])->name('content.advance');
    Route::post('/conteudo/{exam}/rejeitar', [Admin\ContentController::class, 'reject'])->name('content.reject');

    Route::middleware('role:ADMIN')->group(function () {
        Route::post('/conteudo', [Admin\ContentController::class, 'storeExam'])->name('content.store');
        Route::post('/conteudo/{exam}/cadernos', [Admin\ContentController::class, 'storeBooklet'])->name('content.booklet');
        Route::post('/conteudo/cadernos/{booklet}/gabarito', [Admin\ContentController::class, 'storeAnswerKey'])->name('content.answer_key');
        Route::post('/conteudo/{exam}/redacao', [Admin\ContentController::class, 'storeEssayPrompt'])->name('content.essay_prompt');
        Route::post('/conteudo/edicoes/{year}/regras-zero', [Admin\ContentController::class, 'storeZeroRules'])->name('content.zero_rules');
        Route::put('/conteudo/{exam}', [Admin\ContentController::class, 'updateExam'])->name('content.update');
        Route::put('/conteudo/questoes/{question}/gabarito', [Admin\ContentController::class, 'updateAnswer'])->name('content.answer');

        Route::get('/planos', [Admin\PlanController::class, 'index'])->name('plans.index');
        Route::post('/planos', [Admin\PlanController::class, 'store'])->name('plans.store');

        Route::get('/cupons', [Admin\CouponController::class, 'index'])->name('coupons.index');
        Route::post('/cupons', [Admin\CouponController::class, 'store'])->name('coupons.store');

        Route::get('/indicacoes', [Admin\ReferralController::class, 'index'])->name('referrals.index');
        Route::put('/indicacoes', [Admin\ReferralController::class, 'update'])->name('referrals.update');
        Route::post('/indicacoes/comissoes/{commission}/bloquear', [Admin\ReferralController::class, 'block'])->name('referrals.block');
        Route::post('/indicacoes/{conversion}/bloquear', [Admin\ReferralController::class, 'blockConversion'])->name('referrals.block_conversion');
        Route::post('/indicacoes/saques/{withdrawal}/pagar', [Admin\ReferralController::class, 'pay'])->name('referrals.pay');

        Route::get('/bolsas', [Admin\ScholarshipController::class, 'index'])->name('scholarships.index');
        Route::post('/bolsas', [Admin\ScholarshipController::class, 'store'])->name('scholarships.store');
        Route::post('/bolsas/{scholarship}/revogar', [Admin\ScholarshipController::class, 'revoke'])->name('scholarships.revoke');
        Route::post('/bolsas/patrocinadores', [Admin\ScholarshipController::class, 'storeSponsor'])->name('scholarships.sponsor');

        Route::get('/usuarios', [Admin\UserController::class, 'index'])->name('users.index');
        Route::put('/usuarios/{user}', [Admin\UserController::class, 'update'])->name('users.update');

        Route::get('/configuracoes', [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::put('/configuracoes', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/configuracoes/asaas/testar', [Admin\SettingsController::class, 'testAsaas'])->name('settings.asaas_test');
    });
});
