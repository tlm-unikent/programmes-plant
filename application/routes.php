<?php

/*
 * Anything admin facing requires authorisation.
 */

if (Request::env() == 'test')
{
	$before = '';
}
else {
	$before = 'auth';
}

Route::group(array('before' => ''), function(){

	// index
	Route::any('/', 'programmes@index');
	Route::any('([0-9]{4})', 'programmes@index');

	// programme list
	Route::any('([0-9]{4})/(ug|pg)', 'programmes@list');

	// Roles managment
	Route::get('([0-9]{4})/(ug|pg)/roles', 'roles@index');

	// Automatic routing of RESTful controller
	Route::controller('roles');

	// Do global settings
	Route::any('([0-9]{4})/(ug|pg)/programmesettings', 'programmesettings@index');
	Route::any('([0-9]{4})/(ug|pg)/programmesettings/(:any)', 'programmesettings@(:3)');
	Route::any('([0-9]{4})/(ug|pg)/programmesettings/(:any)/(:num)', 'programmesettings@(:3)');
	Route::get('([0-9]{4})/(ug|pg)/programmesettings/(:num)/(:any)/(:num)', 'programmesettings@(:4)');

	// Do Programmes
	Route::any('([0-9]{4})/(ug|pg)/programmes', 'programmes@list');
	Route::any('([0-9]{4})/(ug|pg)/programmes/(:any?)/(:num?)', 'programmes@(:3)');
	Route::get('([0-9]{4})/(ug|pg)/programmes/(:num)/(:any)/(:num)', 'programmes@(:4)');
	Route::get('([0-9]{4})/(ug|pg)/programmes/deliveries/(:num)', 'programmes@deliveries');

	// Access fields systems
	Route::any('(ug|pg)/fields/standard', 'programmefields@index');
	Route::any('(ug|pg)/fields/standard/(:any?)', 'programmefields@(:2)');
	Route::any('(ug|pg)/fields/standard/(:any?)/(:num?)', 'programmefields@(:2)');
	Route::post('(ug|pg)/fields/programmes/reorder', 'programmefields@reorder');
	// Customised routing for immutable fields
	Route::any('fields/immutable', 'globalsettingfields@index');
	Route::any('fields/immutable/(:any?)/(:num?)', 'globalsettingfields@(:1)');
	
	// Customised routing for sections
	Route::any('(ug|pg)/sections', 'programmesections@index');
	Route::post('(ug|pg)/sections/reorder', 'programmesections@reorder');
	Route::any('(ug|pg)/sections/(:any?)/(:num?)', 'programmesections@(:2)');

	// Do global settings
	Route::any('([0-9]{4})/globalsettings', 'globalsettings@index');
	Route::any('([0-9]{4})/globalsettings/(:any)', 'globalsettings@(:2)');
	Route::any('([0-9]{4})/globalsettings/(:any)/(:num)', 'globalsettings@(:2)');
	Route::any('([0-9]{4})/globalsettings/(:num?)/(:any?)/(:num?)', 'globalsettings@(:3)');

	// System settings
	Route::any('settings', 'settings@index');

	// Customised routing for research staff
	Route::any('staff', 'staff@index');
	Route::any('staff/(:any?)/(:num?)', 'staff@(:1)');
	
	// Customised routing for campuses
	Route::any('campuses', 'campuses@index');
	Route::any('campuses/(:any?)/(:num?)', 'campuses@(:1)');

	// Customised routing for schools
	Route::any('schools', 'schools@index');
	Route::any('schools/(:any?)/(:num?)', 'schools@(:1)');

	// Customised routing for faculties
	Route::any('faculties', 'faculties@index');
	Route::any('faculties/(:any?)/(:num?)', 'faculties@(:1)');


	// Customised routing for awards
	Route::any('(ug|pg)/awards', 'awards@index');
	Route::any('(ug|pg)/awards/(:any?)/(:num?)', 'awards@(:2)');

	// Customised routing for leaflets
	Route::any('(ug|pg)/leaflets', 'leaflets@index');
	Route::any('(ug|pg)/leaflets/(:any?)/(:num?)', 'leaflets@(:2)');

	// Customised routing for subjects
	Route::any('(ug|pg)/subjects', 'subjects@index');
	Route::any('(ug|pg)/subjects/(:any?)/(:num?)', 'subjects@(:2)');

	// Customised routing for subject categories
	Route::any('(ug|pg)/subjectcategories', 'subjectcategories@index');
	Route::any('(ug|pg)/subjectcategories/(:any?)/(:num?)', 'subjectcategories@(:2)');

	// Users system
	Route::any('users', 'users@index');
	Route::any('users/(add|edit|delete)/(:num?)', 'users@(:1)');



	// Editing suite
	Route::controller('editor');

	// API

	// Routing for undergraduate API
	Route::any(array(
			'/api/([0-9]{4}|current)/(undergraduate|postgraduate)',
			'/api/([0-9]{4}|current)/(undergraduate|postgraduate)/programmes.(json|xml)',
			'/api/([0-9]{4}|current)/(undergraduate|postgraduate)/programmes'
			
	), 'api@index');

	Route::get(array('/api/([0-9]{4}|current)/(undergraduate|postgraduate)/programmes/(:num?)','/api/([0-9]{4})/(undergraduate|postgraduate)/programmes/(:num?).(json|xml)'), 'api@programme');
	Route::any(array('/api/([0-9]{4}|current)/(undergraduate|postgraduate)/subjects','/api/([0-9]{4})/(undergraduate|postgraduate)/subjects.(json|xml)'), 'api@subject_index');

	Route::get(array('/api/(undergraduate|postgraduate)/(:any).(json|xml)', '/api/(undergraduate|postgraduate)/(:any)'), 'api@data_for_level');

	Route::get(array('/api/(:any).(json|xml)', '/api/(:any)'), 'api@data');


	Route::any('/api/preview/(undergraduate|postgraduate)/(:any?)', 'api@preview');

	Route::any('/api/simpleview/(undergraduate|postgraduate)/(:any?)', 'api@simpleview');
	
	
	// XCRI-CAP Feed
	Route::any('/api/([0-9]{4}|current)/(undergraduate|postgraduate)/xcri-cap', 'api@xcri_cap');
	Route::any('/api/([0-9]{4}|current)/xcri-cap', 'api@xcri_cap');
});

// Login/out
Route::any('login', 'auth@login');
Route::any('logout', 'auth@logout');

Event::listen('404', function()
{
	return Response::error('404');
});

Event::listen('500', function()
{
	return Response::error('500');
});

Route::filter('csrf', function()
{
	if (Request::forged()) return Response::error('500');
});

Route::filter('before', function()
{
	// Push IE to max avaliable.
	header('X-UA-Compatible: IE=Edge,chrome=1');
});

Route::filter('auth', function($permissions)
{
    Session::put('referrer', URL::current());

    // Check user is logged in
    if (Auth::guest()) 
    {
    	return Redirect::to('login');
    }

	// If there are permissions, check user has them
	if (sizeof($permissions) !== 0 && !Auth::user()->can($permissions))
	{
		//User is not allowed here. Tell them
		$page = View::make('admin.inc.no_permissions', array("perms" => $permissions));

		return View::make('layouts.admin', array('content'=> $page));
	}

	// All okay?
});