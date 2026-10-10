<?php

use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function () {

    Route::post('login', 'Api\AuthController@login')->name('login');
    Route::post('register', 'Api\AuthController@register')->name('register');
    Route::post('refreshToken', 'Api\AuthController@refreshToken')->middleware('csrf.cookie')->name('refreshToken');
    Route::put('users/change-paasword-by-user-self', 'Api\UserController@changePasswordByUserSelf')
        ->middleware('throttle:5,1')
        ->name('users.changePasswordByUserSelf');
    Route::get('fpos/{fpo}/generateLayout', 'Api\FpoController@generateLayout')->name('fpos.generateLayout');

    // Route::get('search', 'Api\SearchController@search')->name('search.index');
    Route::group(['middleware' => [\App\Http\Middleware\JwtAuthenticate::class, \App\Http\Middleware\ResolveFactoryScope::class, \App\Http\Middleware\ResolveTimezoneScope::class]], function () {
        Route::post('novelSearch', 'Api\SearchController@novelSearch')->name('novelSearch');
        Route::post('getFunctionalPermission', 'Api\SearchController@getFunctionalPermission')->name('getFunctionalPermission');

        ///////////////////////////  For Excel Report  /////////////////////
        Route::get('export', 'ImportExportController@export')->name('export');
        Route::get('importExportView', 'ImportExportController@importExportView');
        Route::post('import', 'ImportExportController@import')->name('import');

        // Dashboard
        Route::get('dashboard/overview', 'Api\DashboardController@overview')->name('dashboard.overview');

        // Employees
        // POST, not GET: the export filter dialog can send several multi-value
        // (array) filters at once, which doesn't fit cleanly in a query string.
        Route::post('employees/export', 'Api\EmployeeController@export')->name('employees.export');
        Route::post('employees/filter', 'Api\EmployeeController@filter')->name('employees.filter');
        Route::post('employees/import', 'Api\EmployeeController@import')->name('employees.import');
        Route::get('employees/dashboard', 'Api\EmployeeController@dashboard')->name('employees.dashboard');
        Route::get('employees/distinct-values', 'Api\EmployeeController@distinctValues')->name('employees.distinctValues');
        Route::get('employees/{id}/journey', 'Api\EmployeeController@journey')->name('employees.journey');
        Route::get('employees/{id}', 'Api\EmployeeController@show')->name('employees.show');
        Route::post('employees', 'Api\EmployeeController@store')->name('employees.store');
        Route::put('employees/{id}', 'Api\EmployeeController@update')->name('employees.update');

        // Styles — export/import only; CRUD goes through the generic masterDetails endpoint
        // POST, not GET: the export filter dialog can send several multi-value
        // (array) filters at once, which doesn't fit cleanly in a query string.
        Route::post('styles/export', 'Api\StyleController@export')->name('styles.export');
        Route::post('styles/filter', 'Api\StyleController@filter')->name('styles.filter');
        Route::post('styles/import', 'Api\StyleController@import')->name('styles.import');
        Route::get('styles/byFactories', 'Api\StyleController@getByFactories')->name('styles.byFactories');

        // Auth
        Route::get('user', 'Api\AuthController@user')->name('user.get');
        Route::post('logout', 'Api\AuthController@logout')->middleware('csrf.cookie')->name('logout');
        Route::get('user/stickers/{id}', 'Api\UserController@printStickers')->name('user.printStickers');

        // Search
        Route::get('searchByUuid/{uuid}', 'Api\SearchController@searchByUuid')->name('search.uuid');
        Route::post('searchByParameters', 'Api\SearchController@searchByParameters')->name('search.queryString');
        Route::post('searchByParametersJson', 'Api\SearchController@searchByParametersJson')->name('search.queryStringJson');

        // Base Operations
        Route::get('baseOperations/export', 'Api\BaseOperationController@export')->name('baseOperations.export');
        Route::get('baseOperations/byCategories', 'Api\BaseOperationController@getByCategories')->name('baseOperations.byCategories');

        // Operations (was Operation Gradings) — specific paths must come before {id} wildcard
        Route::get('operations/export', 'Api\OperationController@export')->name('operations.export');
        Route::get('operations', 'Api\OperationController@index')->name('operations.index');
        Route::get('operations/byOperation/{operationId}', 'Api\OperationController@getByOperation')->name('operations.byOperation');
        Route::get('operations/byGrade/{gradeId}', 'Api\OperationController@getByGrade')->name('operations.byGrade');
        Route::get('operations/byProductCategory/{productCategoryId}', 'Api\OperationController@getByProductCategory')->name('operations.byProductCategory');
        Route::put('operations/resequence', 'Api\OperationController@resequence')->name('operations.resequence');
        Route::get('operations/{id}', 'Api\OperationController@show')->name('operations.show');
        Route::post('operations', 'Api\OperationController@store')->name('operations.store');
        Route::put('operations/{id}', 'Api\OperationController@update')->name('operations.update');

        // Operation Skills
        Route::get('operationSkills', 'Api\OperationSkillController@index')->name('operationSkills.index');
        Route::get('operationSkills/{id}', 'Api\OperationSkillController@show')->name('operationSkills.show');
        Route::get('operationSkills/byOperation/{operationId}', 'Api\OperationSkillController@getByOperation')->name('operationSkills.byOperation');
        Route::get('operationSkills/bySkill/{skillId}', 'Api\OperationSkillController@getBySkill')->name('operationSkills.bySkill');
        Route::post('operationSkills', 'Api\OperationSkillController@store')->name('operationSkills.store');
        Route::put('operationSkills/{id}', 'Api\OperationSkillController@update')->name('operationSkills.update');

        // Style Operations — specific paths must come before {id} wildcard
        Route::get('styleOperations', 'Api\StyleOperationController@index')->name('styleOperations.index');
        Route::get('styleOperations/byStyle/{styleId}', 'Api\StyleOperationController@getByStyle')->name('styleOperations.byStyle');
        Route::get('styleOperations/byOperation/{operationId}', 'Api\StyleOperationController@getByOperation')->name('styleOperations.byOperation');
        Route::put('styleOperations/resequence', 'Api\StyleOperationController@resequence')->name('styleOperations.resequence');
        Route::get('styleOperations/{id}', 'Api\StyleOperationController@show')->name('styleOperations.show');
        Route::post('styleOperations', 'Api\StyleOperationController@store')->name('styleOperations.store');
        Route::put('styleOperations/{id}', 'Api\StyleOperationController@update')->name('styleOperations.update');

        // Users — typeahead lookup (id/name/email only)
        Route::get('users/search', 'Api\UserController@search')->name('users.search');

        // Time Studies — specific paths must come before {id} wildcard
        Route::get('timeStudies', 'Api\TimeStudyController@index')->name('timeStudies.index');
        Route::get('timeStudies/byOperator/{employeeId}', 'Api\TimeStudyController@getByOperator')->name('timeStudies.byOperator');
        Route::get('timeStudies/byOperation/{operationId}', 'Api\TimeStudyController@getByOperation')->name('timeStudies.byOperation');
        Route::get('timeStudies/export', 'Api\TimeStudyController@export')->name('timeStudies.export');
        Route::get('timeStudies/dashboard', 'Api\TimeStudyController@dashboard')->name('timeStudies.dashboard');
        Route::get('timeStudies/skillMatrix', 'Api\TimeStudyController@skillMatrix')->name('timeStudies.skillMatrix');
        Route::get('timeStudies/skillMatrix/cell', 'Api\TimeStudyController@skillMatrixCell')->name('timeStudies.skillMatrixCell');
        Route::get('skillMatrixInsights', 'Api\SkillMatrixInsightsController@latest')->name('skillMatrixInsights.latest');
        Route::post('skillMatrixInsights/recalculate', 'Api\SkillMatrixInsightsController@recalculate')->name('skillMatrixInsights.recalculate');
        Route::get('skillMatrixInsights/cell', 'Api\SkillMatrixInsightsController@cell')->name('skillMatrixInsights.cell');
        Route::get('timeStudies/{id}', 'Api\TimeStudyController@show')->name('timeStudies.show');
        Route::post('timeStudies', 'Api\TimeStudyController@store')->name('timeStudies.store');

        // Team Plans — specific paths must come before {id} wildcard
        Route::get('teamPlans', 'Api\TeamPlanController@index')->name('teamPlans.index');
        Route::get('teamPlans/suggestSchedule', 'Api\TeamPlanController@suggestSchedule')->name('teamPlans.suggestSchedule');
        Route::get('teamPlans/byTeam/{teamId}', 'Api\TeamPlanController@getByTeam')->name('teamPlans.byTeam');
        Route::get('teamPlans/byStyle/{styleId}', 'Api\TeamPlanController@getByStyle')->name('teamPlans.byStyle');
        Route::put('teamPlans/resequence', 'Api\TeamPlanController@resequence')->name('teamPlans.resequence');
        Route::get('teamPlans/{id}', 'Api\TeamPlanController@show')->name('teamPlans.show');
        Route::post('teamPlans', 'Api\TeamPlanController@store')->name('teamPlans.store');
        Route::put('teamPlans/{id}', 'Api\TeamPlanController@update')->name('teamPlans.update');

        // Style Milestones — apparel-industry order lifecycle dates, 1:1 with a style
        Route::get('styleMilestones/byStyle/{styleId}', 'Api\StyleMilestoneController@getByStyle')->name('styleMilestones.byStyle');
        Route::post('styleMilestones/export', 'Api\StyleMilestoneController@export')->name('styleMilestones.export');
        Route::post('styleMilestones/filter', 'Api\StyleMilestoneController@filter')->name('styleMilestones.filter');
        Route::post('styleMilestones/import', 'Api\StyleMilestoneController@import')->name('styleMilestones.import');
        Route::post('styleMilestones', 'Api\StyleMilestoneController@store')->name('styleMilestones.store');
        Route::put('styleMilestones/{id}', 'Api\StyleMilestoneController@update')->name('styleMilestones.update');

        // Gap Analysis — live-computed Team Plan x Operation headcount/quality gap matrix
        Route::get('gapAnalysis', 'Api\GapAnalysisController@matrix')->name('gapAnalysis.matrix');
        Route::get('gapAnalysis/cell', 'Api\GapAnalysisController@cell')->name('gapAnalysis.cell');

        // Skill Depth / Bus-Factor — factory-wide rollup of the latest Skill Matrix Insights run
        Route::get('skillDepth', 'Api\SkillDepthController@report')->name('skillDepth.report');

        // HashStore
        Route::get('hashStores/getByUuid/{uuid}', 'Api\HashStoreController@getByUuid')->name('hashStores.getByUuid');
        Route::post('hashStores', 'Api\HashStoreController@store')->name('hashStores.store');
        // MasterDetail
        Route::post('masterDetails', 'Api\MasterDetailController')->name('masterDetails');



        //Permissions
        Route::post('permissions/isAuthorized', 'Api\PermissionController@isAuthorized')->name('integrations.isAuthorized');
        Route::post('permissions/getNavigator', 'Api\PermissionController@getNavigator')->name('integrations.getNavigator');
        Route::post('permissions/getPermissions', 'Api\PermissionController@getPermissions')->name('integrations.getPermissions');
        Route::post('permissions/updatePermissions', 'Api\PermissionController@updatePermissions')->name('integrations.updatePermissions');
        Route::post('permissions/changePassword', 'Api\UserController@changePassword')->name('integrations.changePassword');
    });
});

// Route::apiResources([
//     'companies' => 'Api\CompanyController'
// ]);
