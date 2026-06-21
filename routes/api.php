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
        Route::get('operations/export', 'Api\OperationController@export')->name('operations.export');
        Route::get('operations/byCategories', 'Api\OperationController@getByCategories')->name('operations.byCategories');

        // Operation Gradings — specific paths must come before {id} wildcard
        Route::get('operationGradings/export', 'Api\OperationGradingController@export')->name('operationGradings.export');
        Route::get('operationGradings', 'Api\OperationGradingController@index')->name('operationGradings.index');
        Route::get('operationGradings/byOperation/{operationId}', 'Api\OperationGradingController@getByOperation')->name('operationGradings.byOperation');
        Route::get('operationGradings/byGrade/{gradeId}', 'Api\OperationGradingController@getByGrade')->name('operationGradings.byGrade');
        Route::get('operationGradings/byProductCategory/{productCategoryId}', 'Api\OperationGradingController@getByProductCategory')->name('operationGradings.byProductCategory');
        Route::put('operationGradings/resequence', 'Api\OperationGradingController@resequence')->name('operationGradings.resequence');
        Route::get('operationGradings/{id}', 'Api\OperationGradingController@show')->name('operationGradings.show');
        Route::post('operationGradings', 'Api\OperationGradingController@store')->name('operationGradings.store');
        Route::put('operationGradings/{id}', 'Api\OperationGradingController@update')->name('operationGradings.update');

        // Operation Grading Skills
        Route::get('operationGradingSkills', 'Api\OperationGradingSkillController@index')->name('operationGradingSkills.index');
        Route::get('operationGradingSkills/{id}', 'Api\OperationGradingSkillController@show')->name('operationGradingSkills.show');
        Route::get('operationGradingSkills/byOperationGrading/{operationGradingId}', 'Api\OperationGradingSkillController@getByOperationGrading')->name('operationGradingSkills.byOperationGrading');
        Route::get('operationGradingSkills/bySkill/{skillId}', 'Api\OperationGradingSkillController@getBySkill')->name('operationGradingSkills.bySkill');
        Route::post('operationGradingSkills', 'Api\OperationGradingSkillController@store')->name('operationGradingSkills.store');
        Route::put('operationGradingSkills/{id}', 'Api\OperationGradingSkillController@update')->name('operationGradingSkills.update');

        // Product Operation Gradings — specific paths must come before {id} wildcard
        Route::get('productOperationGradings', 'Api\ProductOperationGradingController@index')->name('productOperationGradings.index');
        Route::get('productOperationGradings/byProduct/{productId}', 'Api\ProductOperationGradingController@getByProduct')->name('productOperationGradings.byProduct');
        Route::get('productOperationGradings/byOperationGrading/{operationGradingId}', 'Api\ProductOperationGradingController@getByOperationGrading')->name('productOperationGradings.byOperationGrading');
        Route::put('productOperationGradings/resequence', 'Api\ProductOperationGradingController@resequence')->name('productOperationGradings.resequence');
        Route::get('productOperationGradings/{id}', 'Api\ProductOperationGradingController@show')->name('productOperationGradings.show');
        Route::post('productOperationGradings', 'Api\ProductOperationGradingController@store')->name('productOperationGradings.store');
        Route::put('productOperationGradings/{id}', 'Api\ProductOperationGradingController@update')->name('productOperationGradings.update');

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
