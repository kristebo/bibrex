<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/about', function()
{
	return Response::view('hello');
});

Route::get('/', function()
{
	return Redirect::action('LoansController@getIndex');
});

App::missing(function($exception)
{
    return Response::view('errors.missing', array(), 404);
});

Route::controller('users', 'UsersController');

Route::controller('loans', 'LoansController');

Route::controller('documents', 'DocumentsController');

Route::controller('things', 'ThingsController');

Route::controller('reminders', 'RemindersController');

Route::controller('logs', 'LogsController');
