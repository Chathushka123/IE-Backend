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
    Route::get('fpos/{fpo}/generateLayout', 'Api\FpoController@generateLayout')->name('fpos.generateLayout');

    // Route::get('search', 'Api\SearchController@search')->name('search.index');
    Route::group(['middleware' => \App\Http\Middleware\JwtAuthenticate::class], function () {
        Route::post('novelSearch', 'Api\SearchController@novelSearch')->name('novelSearch');
        Route::post('getFunctionalPermission', 'Api\SearchController@getFunctionalPermission')->name('getFunctionalPermission');

        ///////////////////////////  For Excel Report  /////////////////////
        Route::get('export', 'ImportExportController@export')->name('export');
        Route::get('importExportView', 'ImportExportController@importExportView');
        Route::post('import', 'ImportExportController@import')->name('import');

        // Employees
        Route::get('employees/export', 'Api\EmployeeController@export')->name('employees.export');
        Route::get('employees/dashboard', 'Api\EmployeeController@dashboard')->name('employees.dashboard');
        Route::get('employees/{id}', 'Api\EmployeeController@show')->name('employees.show');
        Route::post('employees', 'Api\EmployeeController@store')->name('employees.store');
        Route::put('employees/{id}', 'Api\EmployeeController@update')->name('employees.update');

        // Auth
        Route::get('user', 'Api\AuthController@user')->name('user.get');
        Route::post('logout', 'Api\AuthController@logout')->middleware('csrf.cookie')->name('logout');
        Route::get('user/stickers/{id}', 'Api\UserController@printStickers')->name('user.printStickers');

        // Search
        Route::get('searchByUuid/{uuid}', 'Api\SearchController@searchByUuid')->name('search.uuid');
        Route::post('searchByParameters', 'Api\SearchController@searchByParameters')->name('search.queryString');
        Route::post('searchByParametersJson', 'Api\SearchController@searchByParametersJson')->name('search.queryStringJson');

        // Operations
        Route::get('operations/byCategories', 'Api\OperationController@getByCategories')->name('operations.byCategories');

        // Operation Gradings
        Route::get('operationGradings', 'Api\OperationGradingController@index')->name('operationGradings.index');
        Route::get('operationGradings/{id}', 'Api\OperationGradingController@show')->name('operationGradings.show');
        Route::get('operationGradings/byOperation/{operationId}', 'Api\OperationGradingController@getByOperation')->name('operationGradings.byOperation');
        Route::get('operationGradings/byGrade/{gradeId}', 'Api\OperationGradingController@getByGrade')->name('operationGradings.byGrade');
        Route::post('operationGradings', 'Api\OperationGradingController@store')->name('operationGradings.store');
        Route::put('operationGradings/{id}', 'Api\OperationGradingController@update')->name('operationGradings.update');

        // Operation Skills
        Route::get('operationSkills', 'Api\OperationSkillController@index')->name('operationSkills.index');
        Route::get('operationSkills/{id}', 'Api\OperationSkillController@show')->name('operationSkills.show');
        Route::get('operationSkills/byOperation/{operationId}', 'Api\OperationSkillController@getByOperation')->name('operationSkills.byOperation');
        Route::get('operationSkills/bySkill/{skillId}', 'Api\OperationSkillController@getBySkill')->name('operationSkills.bySkill');
        Route::post('operationSkills', 'Api\OperationSkillController@store')->name('operationSkills.store');
        Route::put('operationSkills/{id}', 'Api\OperationSkillController@update')->name('operationSkills.update');

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
