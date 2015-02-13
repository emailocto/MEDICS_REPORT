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

Route::get('/', function()
{
	return View::make('hello');
});

Route::get('users', function()
{
	//return 'Users!';
	//return View::make('users');
	$users = user::all();
	return View::make('users')->with('users', $users);
});

Route::get('logout', function()
{
	//add logout hashing for security
  Auth::logout();
  return Redirect::to('login');
});

Route::get('login', array('uses' => 'LoginController@showLogin'));
Route::post('login', array('uses' => 'LoginController@doLogin'));

Route::get('register', array('uses' => 'RegistrationController@showRegistration'));
Route::post('register', array('uses' => 'RegistrationController@doRegister'));
