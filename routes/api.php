<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BehaviourController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\DropoutController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IdCardController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\OnboardingWizardController;
use App\Http\Controllers\Api\ParentPortalController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentDocumentController;
use App\Http\Controllers\Api\StudentPhotoController;
use App\Http\Controllers\Api\StudentTimelineController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherAttendanceController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TeacherSeparationController;
use App\Http\Controllers\Api\TeacherSettlementController;
use App\Http\Controllers\Api\RecruitmentController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\TenantRegistrationController;
use App\Http\Controllers\Api\TransferCertificateController;
use App\Http\Controllers\Api\AppraisalController;
use App\Http\Controllers\Api\ExamConfigurationController;
use App\Http\Controllers\Api\ExamAdmitCardController;
use App\Http\Controllers\Api\ExamManagementController;
use App\Http\Controllers\Api\ExamPaperController;
use App\Http\Controllers\Api\ExamResultController;
use App\Http\Controllers\Api\HrAnalyticsController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\PayrollController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [TenantRegistrationController::class, 'register']);
Route::post('/check/email', [TenantRegistrationController::class, 'checkEmail']);
Route::post('/check/phone', [TenantRegistrationController::class, 'checkPhone']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [FileUploadController::class, 'upload']);

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::prefix('setup')->group(function () {
        Route::get('/status', [OnboardingWizardController::class, 'status']);
        Route::get('/step/{step}', [OnboardingWizardController::class, 'showStep']);
        Route::post('/step/1', [OnboardingWizardController::class, 'saveStep1']);
        Route::post('/step/2', [OnboardingWizardController::class, 'saveStep2']);
        Route::post('/step/3', [OnboardingWizardController::class, 'saveStep3']);
        Route::post('/step/4', [OnboardingWizardController::class, 'saveStep4']);
        Route::post('/step/{step}', [OnboardingWizardController::class, 'saveStep']);
        Route::post('/finish', [OnboardingWizardController::class, 'finish']);
    });

    Route::prefix('academic')->name('academic.')->group(function () {
        Route::get('/years', [AcademicYearController::class, 'index'])->name('years.index');
        Route::post('/years', [AcademicYearController::class, 'store'])->name('years.store');
        Route::get('/years/{year}', [AcademicYearController::class, 'show'])->name('years.show');
        Route::post('/years/{year}/activate', [AcademicYearController::class, 'activate'])->name('years.activate');
        Route::post('/years/{year}/archive', [AcademicYearController::class, 'archive'])->name('years.archive');
        Route::post('/years/{year}/copy', [AcademicYearController::class, 'copy'])->name('years.copy');

        Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
        Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
        Route::get('/classes/{school_class}', [ClassController::class, 'show'])->name('classes.show');
        Route::post('/classes/{school_class}/update', [ClassController::class, 'update'])->name('classes.update');
        Route::post('/classes/{school_class}/activate', [ClassController::class, 'activate'])->name('classes.activate');
        Route::post('/classes/{school_class}/inactivate', [ClassController::class, 'inactivate'])->name('classes.inactivate');
        Route::post('/classes/{school_class}/delete', [ClassController::class, 'delete'])->name('classes.delete');

        Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::get('/sections/{section}', [SectionController::class, 'show'])->name('sections.show');
        Route::post('/sections/{section}/update', [SectionController::class, 'update'])->name('sections.update');
        Route::post('/sections/{section}/activate', [SectionController::class, 'activate'])->name('sections.activate');
        Route::post('/sections/{section}/inactivate', [SectionController::class, 'inactivate'])->name('sections.inactivate');
        Route::post('/sections/{section}/delete', [SectionController::class, 'delete'])->name('sections.delete');

        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::get('/subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');
        Route::post('/subjects/{subject}/update', [SubjectController::class, 'update'])->name('subjects.update');
        Route::post('/subjects/{subject}/activate', [SubjectController::class, 'activate'])->name('subjects.activate');
        Route::post('/subjects/{subject}/inactivate', [SubjectController::class, 'inactivate'])->name('subjects.inactivate');
        Route::post('/subjects/{subject}/delete', [SubjectController::class, 'delete'])->name('subjects.delete');
    });

    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/by-class', [StudentController::class, 'byClass'])->name('byClass');
        Route::get('/by-section', [StudentController::class, 'bySection'])->name('bySection');
        Route::get('/{student}', [StudentController::class, 'show'])->name('show');
        Route::post('/{student}/activate', [StudentController::class, 'activate'])->name('activate');
        Route::post('/{student}/inactivate', [StudentController::class, 'inactivate'])->name('inactivate');
        Route::post('/{student}/delete', [StudentController::class, 'delete'])->name('delete');
        Route::post('/{student}/update', [StudentController::class, 'update'])->name('update');
        Route::post('/{student}/change-class', [StudentController::class, 'changeClass'])->name('changeClass');
        Route::post('/{student}/change-section', [StudentController::class, 'changeSection'])->name('changeSection');
        Route::post('/{student}/assign-section', [StudentController::class, 'assignSection'])->name('assignSection');
        Route::post('/{student}/set-roll', [StudentController::class, 'setRoll'])->name('setRoll');
        Route::get('/{student}/guardians', [StudentController::class, 'guardians'])->name('guardians');
        Route::post('/{student}/guardians', [StudentController::class, 'addGuardian'])->name('addGuardian');
        Route::post('/{student}/guardians/{guardian}/primary', [StudentController::class, 'setPrimaryGuardian'])->name('setPrimaryGuardian');
        Route::post('/{student}/guardians/{guardian}/delete', [StudentController::class, 'deleteGuardian'])->name('deleteGuardian');

        Route::get('/{student}/documents', [StudentDocumentController::class, 'index'])->name('documents');
        Route::post('/{student}/documents', [StudentDocumentController::class, 'store'])->name('documents.store');
        Route::post('/{student}/documents/{document}/delete', [StudentDocumentController::class, 'delete'])->name('documents.delete');

        Route::get('/{student}/photo', [StudentPhotoController::class, 'show'])->name('photo');
        Route::post('/{student}/photo', [StudentPhotoController::class, 'store'])->name('photo.store');
        Route::get('/{student}/photos/history', [StudentPhotoController::class, 'history'])->name('photo.history');

        Route::get('/{student}/timeline', [StudentTimelineController::class, 'index'])->name('timeline');

        Route::get('/id-card/templates', [IdCardController::class, 'templates'])->name('idCard.templates');
        Route::post('/id-card/templates', [IdCardController::class, 'storeTemplate'])->name('idCard.templates.store');
        Route::get('/{student}/id-card', [IdCardController::class, 'generate'])->name('idCard.generate');
        Route::post('/id-card/bulk-generate', [IdCardController::class, 'bulkGenerate'])->name('idCard.bulkGenerate');
    });

    Route::prefix('parent')->group(function () {
        Route::post('/link', [ParentPortalController::class, 'link'])->name('parent.link');
        Route::get('/children', [ParentPortalController::class, 'children'])->name('parent.children');
        Route::get('/dashboard', [ParentPortalController::class, 'dashboard'])->name('parent.dashboard');
        Route::get('/notifications', [ParentPortalController::class, 'notificationPrefs'])->name('parent.notifications');
        Route::post('/notifications', [ParentPortalController::class, 'updateNotificationPrefs'])->name('parent.notifications.update');
    });

    Route::prefix('behaviour')->group(function () {
        Route::get('/incidents', [BehaviourController::class, 'incidents']);
        Route::post('/incidents', [BehaviourController::class, 'storeIncident']);
        Route::get('/incidents/{incident}', [BehaviourController::class, 'showIncident']);
        Route::post('/incidents/{incident}/update', [BehaviourController::class, 'updateIncident']);
        Route::post('/incidents/{incident}/delete', [BehaviourController::class, 'deleteIncident']);
        Route::get('/actions', [BehaviourController::class, 'actions']);
        Route::post('/actions', [BehaviourController::class, 'storeAction']);
        Route::post('/actions/{action}/complete', [BehaviourController::class, 'completeAction']);
        Route::get('/positive', [BehaviourController::class, 'positiveBehaviours']);
        Route::post('/positive', [BehaviourController::class, 'storePositiveBehaviour']);
    });

    Route::prefix('health')->group(function () {
        Route::get('/records', [HealthController::class, 'healthRecords']);
        Route::post('/records', [HealthController::class, 'storeHealthRecord']);
        Route::get('/records/growth', [HealthController::class, 'growthChart']);
        Route::get('/vaccinations', [HealthController::class, 'vaccinations']);
        Route::post('/vaccinations', [HealthController::class, 'storeVaccination']);
        Route::get('/vaccinations/due', [HealthController::class, 'dueVaccinations']);
        Route::get('/medical-conditions', [HealthController::class, 'medicalConditions']);
        Route::post('/medical-conditions', [HealthController::class, 'storeMedicalCondition']);
        Route::get('/allergies', [HealthController::class, 'allergies']);
        Route::post('/allergies', [HealthController::class, 'storeAllergy']);
        Route::get('/allergies/critical', [HealthController::class, 'criticalAllergies']);
        Route::get('/checkups', [HealthController::class, 'checkups']);
        Route::post('/checkups', [HealthController::class, 'storeCheckup']);
        Route::get('/emergency-contacts', [HealthController::class, 'emergencyContacts']);
        Route::post('/emergency-contacts', [HealthController::class, 'storeEmergencyContact']);
        Route::post('/emergency-contacts/{contact}/set-primary', [HealthController::class, 'setPrimaryContact']);
        Route::get('/students/{student}/summary', [HealthController::class, 'studentHealthSummary']);
    });

    Route::prefix('tc')->group(function () {
        Route::get('/', [TransferCertificateController::class, 'index']);
        Route::post('/issue', [TransferCertificateController::class, 'issue']);
        Route::get('/{transferCertificate}', [TransferCertificateController::class, 'show']);
        Route::post('/{transferCertificate}/duplicate', [TransferCertificateController::class, 'issueDuplicate']);
        Route::post('/receive', [TransferCertificateController::class, 'receive']);
        Route::post('/{transferCertificate}/verify', [TransferCertificateController::class, 'verify']);
        Route::post('/verify-by-number', [TransferCertificateController::class, 'verifyByNumber']);
        Route::post('/{transferCertificate}/cancel', [TransferCertificateController::class, 'cancel']);
    });

    Route::prefix('dropouts')->group(function () {
        Route::get('/', [DropoutController::class, 'index']);
        Route::post('/', [DropoutController::class, 'store']);
        Route::get('/analytics', [DropoutController::class, 'analytics']);
        Route::get('/{dropoutRecord}', [DropoutController::class, 'show']);
        Route::post('/{dropoutRecord}/readmit', [DropoutController::class, 'readmit']);
        Route::get('/{dropoutRecord}/readmissions', [DropoutController::class, 'readmissionHistory']);
    });

    Route::prefix('attendance')->group(function () {
        Route::get('/periods', [AttendanceController::class, 'periods']);
        Route::post('/periods', [AttendanceController::class, 'storePeriod']);
        Route::post('/periods/{period}/update', [AttendanceController::class, 'updatePeriod']);
        Route::post('/periods/{period}/delete', [AttendanceController::class, 'deletePeriod']);

        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::post('/bulk', [AttendanceController::class, 'markBulk']);

        Route::get('/regularizations', [AttendanceController::class, 'regularizationRequests']);
        Route::post('/regularizations', [AttendanceController::class, 'requestRegularization']);
        Route::post('/regularizations/{regularization}/review', [AttendanceController::class, 'reviewRegularization']);

        Route::get('/fingerprint-devices', [AttendanceController::class, 'devices']);
        Route::post('/fingerprint-devices', [AttendanceController::class, 'storeDevice']);
        Route::post('/fingerprint-devices/{device}/update', [AttendanceController::class, 'updateDevice']);
        Route::get('/fingerprint-logs', [AttendanceController::class, 'fingerprintLogs']);
        Route::post('/fingerprint-logs', [AttendanceController::class, 'storeFingerprintLog']);

        Route::get('/reports/daily', [AttendanceController::class, 'dailyReport']);
        Route::get('/reports/monthly', [AttendanceController::class, 'monthlyReport']);

        Route::get('/{attendance}', [AttendanceController::class, 'show']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/admission', [ReportController::class, 'admission']);
        Route::get('/student-summary', [ReportController::class, 'studentSummary']);
        Route::get('/health-summary', [ReportController::class, 'healthSummary']);
        Route::get('/attendance-summary', [ReportController::class, 'attendanceSummary']);
        Route::get('/behaviour-summary', [ReportController::class, 'behaviourSummary']);
    });

    Route::prefix('teachers')->group(function () {
        Route::get('/', [TeacherController::class, 'index']);
        Route::post('/', [TeacherController::class, 'store']);

        Route::post('/assign-subject', [TeacherController::class, 'assignSubject']);
        Route::post('/subject-assignments/{assignment}/remove', [TeacherController::class, 'removeSubjectAssignment']);

        Route::post('/assign-class-teacher', [TeacherController::class, 'assignClassTeacher']);
        Route::get('/class-teacher-assignments', [TeacherController::class, 'classTeacherAssignments']);
        Route::post('/class-teacher-assignments/{assignment}/remove', [TeacherController::class, 'removeClassTeacherAssignment']);

        Route::get('/workload', [TeacherController::class, 'workload']);

        Route::get('/{teacher}', [TeacherController::class, 'show']);
        Route::post('/{teacher}/update', [TeacherController::class, 'update']);
        Route::post('/{teacher}/activate', [TeacherController::class, 'activate']);
        Route::post('/{teacher}/inactivate', [TeacherController::class, 'inactivate']);

        Route::get('/{teacher}/qualifications', [TeacherController::class, 'qualifications']);
        Route::post('/{teacher}/qualifications', [TeacherController::class, 'addQualification']);
        Route::post('/{teacher}/qualifications/{qualification}/delete', [TeacherController::class, 'deleteQualification']);

        Route::get('/{teacher}/trainings', [TeacherController::class, 'trainings']);
        Route::post('/{teacher}/trainings', [TeacherController::class, 'addTraining']);
        Route::post('/{teacher}/trainings/{training}/delete', [TeacherController::class, 'deleteTraining']);

        Route::get('/{teacher}/experiences', [TeacherController::class, 'experiences']);
        Route::post('/{teacher}/experiences', [TeacherController::class, 'addExperience']);
        Route::post('/{teacher}/experiences/{experience}/delete', [TeacherController::class, 'deleteExperience']);

        Route::get('/{teacher}/documents', [TeacherController::class, 'documents']);
        Route::post('/{teacher}/documents', [TeacherController::class, 'addDocument']);
        Route::post('/{teacher}/documents/{document}/verify', [TeacherController::class, 'verifyDocument']);
        Route::post('/{teacher}/documents/{document}/delete', [TeacherController::class, 'deleteDocument']);

        Route::get('/{teacher}/subject-assignments', [TeacherController::class, 'subjectAssignments']);
    });

    Route::prefix('teacher-attendance')->controller(TeacherAttendanceController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'mark');
        Route::post('/bulk', 'bulk');
        Route::get('/sheet', 'sheet');
        Route::get('/pending', 'pending');
        Route::get('/{attendance}', 'show')->whereUuid('attendance');
        Route::post('/{attendance}/update', 'update')->whereUuid('attendance');
        Route::get('/{attendance}/audit', 'audit')->whereUuid('attendance');
        Route::get('/regularizations', 'regularizations');
        Route::post('/regularizations', 'requestRegularization');
        Route::post('/regularizations/{regularization}/review', 'reviewRegularization')->whereUuid('regularization');
        Route::get('/reports/daily', 'dailyReport');
        Route::get('/reports/monthly', 'monthlyReport');
        Route::get('/reports/leave-period', 'leavePeriodReport');
        Route::post('/finalize', 'finalize');
        Route::get('/finalization', 'finalizationStatus');
        Route::get('/salary-preview', 'salaryPreview');
        Route::get('/settings', 'showSettings');
        Route::post('/settings', 'updateSettings');
        Route::get('/fingerprints', 'fingerprints');
        Route::post('/fingerprints', 'enrollFingerprint');
        Route::post('/fingerprints/{fingerprint}/delete', 'deleteFingerprint')->whereUuid('fingerprint');
        Route::get('/fingerprint-punches', 'fingerprintPunches');
        Route::post('/fingerprint-punches', 'storeFingerprintPunches');
        Route::post('/fingerprint-punches/process', 'processFingerprintPunches');
    });

    Route::prefix('leaves')->controller(LeaveController::class)->group(function () {
        Route::get('/types', 'leaveTypes');
        Route::post('/types', 'storeLeaveType');
        Route::post('/types/{leaveType}/update', 'updateLeaveType')->whereUuid('leaveType');
        Route::post('/types/{leaveType}/delete', 'deleteLeaveType')->whereUuid('leaveType');
        Route::get('/balances', 'balances');
        Route::post('/balances', 'createBalance');
        Route::get('/applications', 'applications');
        Route::post('/apply', 'apply');
        Route::post('/applications/{application}/review', 'review')->whereUuid('application');
        Route::post('/applications/{application}/cancel', 'cancel')->whereUuid('application');
    });

    Route::prefix('salary')->controller(SalaryController::class)->group(function () {
        Route::get('/structures', 'structures');
        Route::post('/structures', 'storeStructure');
        Route::post('/structures/{structure}/update', 'updateStructure')->whereUuid('structure');
        Route::post('/structures/{structure}/delete', 'deleteStructure')->whereUuid('structure');

        Route::get('/teacher-salaries', 'salaries');
        Route::post('/teacher-salaries', 'storeSalary');
        Route::post('/teacher-salaries/{salary}/update', 'updateSalary')->whereUuid('salary');

        Route::get('/payslips', 'payslips');
        Route::post('/payslips/generate', 'generatePayslips');
        Route::post('/payslips/{payslip}/mark-paid', 'markPaid')->whereUuid('payslip');
    });

    Route::prefix('teacher-separations')->controller(TeacherSeparationController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{separation}', 'show')->whereUuid('separation');
        Route::post('/{separation}/review', 'review')->whereUuid('separation');
        Route::post('/{separation}/withdraw', 'withdraw')->whereUuid('separation');
        Route::get('/{separation}/documents', 'documents')->whereUuid('separation');
        Route::post('/{separation}/documents', 'storeDocument')->whereUuid('separation');
        Route::post('/{separation}/documents/{document}/delete', 'deleteDocument')->whereUuid('separation')->whereUuid('document');
        Route::get('/{separation}/letter', 'letter')->whereUuid('separation');
    });

    Route::prefix('teacher-settlements')->controller(TeacherSettlementController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/preview', 'preview');
        Route::post('/', 'store');
        Route::get('/{settlement}', 'show')->whereUuid('settlement');
        Route::post('/{settlement}/approve', 'approve')->whereUuid('settlement');
        Route::post('/{settlement}/pay', 'pay')->whereUuid('settlement');
        Route::get('/{settlement}/receipt', 'receipt')->whereUuid('settlement');
    });

    Route::prefix('recruitment')->controller(RecruitmentController::class)->group(function () {
        Route::get('/jobs', 'index');
        Route::post('/jobs', 'store');
        Route::post('/apply', 'apply');
        Route::get('/jobs/{jobPost}/applications', 'applications')->whereUuid('jobPost');
        Route::post('/applications/{application}/status', 'updateStatus')->whereUuid('application');
        Route::post('/applications/{application}/interview', 'scheduleInterview')->whereUuid('application');
        Route::post('/interviews/{schedule}/feedback', 'submitFeedback')->whereUuid('schedule');
        Route::post('/applications/{application}/offer', 'sendOffer')->whereUuid('application');
        Route::post('/offers/{offer}/response', 'offerResponse')->whereUuid('offer');
    });

    Route::prefix('onboarding')->controller(OnboardingController::class)->group(function () {
        Route::get('/{application}/tasks', 'index')->whereUuid('application');
        Route::post('/{application}/tasks', 'store')->whereUuid('application');
        Route::post('/tasks/{task}/complete', 'complete')->whereUuid('task');
        Route::delete('/tasks/{task}', 'destroy')->whereUuid('task');
    });

    Route::prefix('exam')->group(function () {
        Route::get('types', [ExamConfigurationController::class, 'indexTypes']);
        Route::post('types', [ExamConfigurationController::class, 'storeType']);
        Route::get('grading-systems', [ExamConfigurationController::class, 'indexGradingSystems']);
        Route::post('grading-systems', [ExamConfigurationController::class, 'storeGradingSystem']);
        Route::post('grading-systems/{system}/rules', [ExamConfigurationController::class, 'storeGradingRule'])->whereUuid('system');
        Route::post('subject-configs', [ExamConfigurationController::class, 'storeSubjectConfig']);
        Route::post('calculate-grade', [ExamConfigurationController::class, 'calculate']);
        Route::get('calendars', [ExamManagementController::class, 'indexCalendars']);
        Route::post('calendars', [ExamManagementController::class, 'storeCalendar']);
        Route::get('schedules', [ExamManagementController::class, 'indexSchedules']);
        Route::post('schedules', [ExamManagementController::class, 'storeSchedule']);
        Route::post('schedules/{schedule}/seating/auto-allocate', [ExamManagementController::class, 'autoAllocateSeating'])->whereUuid('schedule');
        Route::get('rooms', [ExamManagementController::class, 'indexRooms']);
        Route::post('rooms', [ExamManagementController::class, 'storeRoom']);
        Route::get('papers', [ExamPaperController::class, 'index']);
        Route::post('papers', [ExamPaperController::class, 'generate']);
        Route::get('papers/{paper}', [ExamPaperController::class, 'show'])->whereUuid('paper');
        Route::get('admit-cards', [ExamAdmitCardController::class, 'index']);
        Route::post('admit-cards', [ExamAdmitCardController::class, 'generate']);
        Route::post('admit-cards/bulk-generate', [ExamAdmitCardController::class, 'bulkGenerate']);
        Route::post('admit-cards/verify', [ExamAdmitCardController::class, 'verify']);
        Route::get('marks', [ExamResultController::class, 'indexMarks']);
        Route::post('marks/bulk', [ExamResultController::class, 'enterMarks']);
        Route::post('marks/{mark}/verify', [ExamResultController::class, 'verifyMark'])->whereUuid('mark');
        Route::get('results', [ExamResultController::class, 'indexResults']);
        Route::post('results/publish', [ExamResultController::class, 'publishResults']);
        Route::get('results/{result}', [ExamResultController::class, 'showResult'])->whereUuid('result');
    });
    Route::prefix('payroll')->group(function () {
        Route::get('processes', [PayrollController::class, 'indexProcesses']);
        Route::get('processes/{id}', [PayrollController::class, 'showProcess']);
        Route::post('bulk-process', [PayrollController::class, 'processBulk']);
        Route::post('bulk-lock/{id}', [PayrollController::class, 'lockProcess']);
        Route::get('components', [PayrollController::class, 'indexComponents']);
        Route::post('components', [PayrollController::class, 'storeComponent']);
        Route::get('grades', [PayrollController::class, 'indexGrades']);
        Route::post('grades', [PayrollController::class, 'storeGrade']);
        Route::get('tax-slabs', [PayrollController::class, 'indexTaxSlabs']);
        Route::post('tax-slabs', [PayrollController::class, 'storeTaxSlab']);
        Route::post('calculate-preview', [PayrollController::class, 'calculatePreview']);
    });

    Route::prefix('loans')->group(function () {
        Route::get('/', [LoanController::class, 'index']);
        Route::post('/', [LoanController::class, 'store']);
        Route::get('/{loan}', [LoanController::class, 'show'])->whereUuid('loan');
        Route::post('/{loan}/approve', [LoanController::class, 'approve'])->whereUuid('loan');
    });

    Route::prefix('appraisal')->group(function () {
        Route::get('kpis', [AppraisalController::class, 'indexKpis']);
        Route::post('kpis', [AppraisalController::class, 'storeKpi']);
        Route::post('submit-review', [AppraisalController::class, 'submitReview']);
        Route::post('finalize-review/{review}', [AppraisalController::class, 'finalizeReview'])->whereUuid('review');
        Route::post('grant-increment/{review}', [AppraisalController::class, 'grantIncrement'])->whereUuid('review');
        Route::get('reviews', [AppraisalController::class, 'indexReviews']);
    });

    Route::prefix('hr-analytics')->group(function () {
        Route::get('dashboard', [HrAnalyticsController::class, 'dashboard']);
        Route::get('payroll-cost', [HrAnalyticsController::class, 'payrollCostAnalysis']);
    });

});





