<?php


class Rollover_Task {

	protected static $to_unset = array('id', 'created_by', 'created_at', 'updated_at', 'live', 'locked_to', 'current_revision', 'live_revision');

	protected static $to_unset_for_settings = array('id', 'created_at', 'updated_at', 'current_revision', 'live_revision');

	/**
	 * must be run as UG/PG
	 */
	public function run($arguments = array())
	{
		echo "Please select a type: rollover:ug 2014 2015 \n";
	}

	/**
	* wrapper for doing all the settings rollover tasks
	*/
	public function settings($arguments = array())
	{
		static::immutable($arguments);

		static::overridable($arguments);

		die("Rollover complete. \n");
	}

	/**
	 * rollover ug programmes
	 */
	public function ug($arguments = array())
	{

		if(sizeof($arguments) != 2) die("Please provide a from and to year. \n");

		Auth::login(1);

		$from_year = $arguments[0];
		$to_year = $arguments[1];	

		// Foreach programme
		foreach(UG_Programme::where('year', '=', $from_year)->get() as $programme){
			// Copy data
			$attributes = $programme->attributes;

			// Remove attributes
			foreach(static::$to_unset as $unset){
				unset($attributes[$unset]);
			}	

			// Create a new copy in the next year
			$copy = new UG_Programme;
			$copy->fill($attributes);

			$copy->year = $to_year;
			$copy->save();
		}

		die("Rollover complete. \n");

	}

	/**
	 * rollover pg programmes
	 */
	public function pg($arguments = array())
	{
		if(sizeof($arguments) != 2) die("Please provide a from and to year. \n");

		Auth::login(1);

		$from_year = $arguments[0];
		$to_year = $arguments[1];

		// Foreach programme
		foreach(PG_Programme::where('year', '=', $from_year)->get() as $programme){

			$attributes = $programme->attributes;

			// Remove attributes
			foreach(static::$to_unset as $unset){
				unset($attributes[$unset]);
			}

			// Create a new copy in the next year
			$copy = new PG_Programme;
			$copy->fill($attributes);

			$copy->year = $to_year;
			$copy->save();
		}

		die("Rollover complete. \n");
	}

	/**
	* rollover immutable fields
	*/
	public function immutable($arguments = array())
	{
		if(sizeof($arguments) != 3) die("Please provide a from and to year. \n");

		Auth::login(1);

		$type = $arguments[0];
		$from_year = $arguments[1];
		$to_year = $arguments[2];

		// should only be one result but loop anyway
		foreach(GlobalSetting::where('year', '=', $from_year)->get() as $global_settings){

			// attributes from the original version
			$attributes = $global_settings->attributes;

			// remove attributes from the original version that we don't want for the new copy
			foreach(static::$to_unset_for_settings as $unset){
				unset($attributes[$unset]);
			}

			// create the copy using fill to push in the relevant attributes
			$global_settings_copy = new GlobalSetting;
			$global_settings_copy->fill($attributes);
			$global_settings_copy->year = $to_year;
			$global_settings_copy->save();

			// make the revision live
			$revision = $global_settings_copy->current_revision;
        	$global_settings_copy->make_revision_live($revision);
		}

	}

	/**
	* rollover programme settings (overridable fields)
	*/
	public function overridable($arguments = array())
	{
		if(sizeof($arguments) != 3) die("Please provide ug/pg, a from and to year. \n");

		Auth::login(1);

		$type = $arguments[0];
		$from_year = $arguments[1];
		$to_year = $arguments[2];

		$model = strtoupper($type) . "_ProgrammeSetting";

		// should only be one result but loop anyway
		foreach($model::where('year', '=', $from_year)->get() as $programme_settings){

			// attributes from the original version
			$attributes = $programme_settings->attributes;

			// remove attributes from the original version that we don't want for the new copy
			foreach(static::$to_unset_for_settings as $unset){
				unset($attributes[$unset]);
			}

			// create the copy using fill to push in the relevant attributes
			$programme_settings_copy = new $model;
			$programme_settings_copy->fill($attributes);
			$programme_settings_copy->year = $to_year;
			$programme_settings_copy->save();

			// make the revision live
			$revision = $programme_settings_copy->current_revision;
        	$programme_settings_copy->make_revision_live($revision);
		}

	}

}