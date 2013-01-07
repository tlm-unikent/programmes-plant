<?php
class SimpleData extends Eloquent {

	/**
	 * Validation object once it has been created.
	 */
	public static $validation = null;

	/**
	 * The rules for validation in standard Laravel validation arrays.
	 */
	public static $rules = array();

	/**
	 * A variable caching the output of all_as_list across all SimpleData's children for fast response without hitting the database.
	 * 
	 * The format is 'child' => array(id => field), e.g. 'schools' => array('1' => 'Humanities', '2' => 'Arts')
	 */
	public static $list_cache = array();

	/**
	 * Name of field containing item title. By default this is set to "name"
	 */
	public static $title_field = 'name';

	/**
	 * Does this model seperate items by year? (by default this is false.)
	 */
	public static $data_by_year = false;

	/**
	 * Validates input for Field.
	 * 
	 * @param array $input The input in Laravel input format.
	 * @param array $rules An array of Laravel validations which will overwrite the defaults for the class.
	 * @return $validaton The Laravel validation object.
	 */
	public static function is_valid($rules = null)
	{
		if (! is_null($rules))
		{
			static::$rules = array_merge(static::$rules, $rules);
		}

		$input = Input::all();

		static::$validation = Validator::make($input, static::$rules);

		return static::$validation->passes();
	}

	/**
	 * Get the name of the filed containing the "title" for a given data type.
	 * 
	 * @return string $title_field Name of field containing item title.
	 */
	public static function get_title_field()
	{
		return static::$title_field;
	}

	/**
	 * Gives a flat array of id => item_title for all items.
	 * 
	 * Used generally to create select dropdowns.
	 * 
	 * This has two levels of caching. First a database lookup cache, then an in memory cache.
	 * 
	 * This is done for application performance.
	 * 
	 * @param string $year The year from which to get the array.
	 
	 * @param boolean $empty_default_value some select lists can have an empty 'please select' or 'none' value in them. Defaults to false.
	 
	 * @return array $options List of items in the format id => item_title.
	 */
	public static function all_as_list($year = false, $empty_default_value = 0)
	{
		$model = get_called_class();

		// If this datatype cannot be separated by year, make year false.
		if (!static::$data_by_year) $year = false;

		$cache_key = ( $empty_default_value != 0 ) ? "$model-$year-defaulttonone-options-list" : "$model-$year-options-list";

		if (isset(static::$list_cache[$cache_key])) return static::$list_cache[$cache_key];
		
		return static::$list_cache[$cache_key] = Cache::remember($cache_key, function() use ($year, $model, $empty_default_value)
		{
			$options = array();
			// set the 'none' select value, as per the $empty_default_value param
			if ( isset($empty_default_value) && $empty_default_value != 0 )
			{
				$options[0] = __('fields.empty_default_value');
			}

			$title_field = $model::get_title_field();

			if (! $year)
			{
				$data = $model::order_by($title_field,'asc')->get(array('id', $title_field));
			}
			else 
			{
				$data = $model::where('year','=', $year)->order_by($title_field,'asc')->get(array('id',$title_field));
			}

			foreach ($data as $record)
			{
				$options[$record->id] = $record->$title_field;
			}

			return $options;
		}, 2628000); // Cache forever.
	}

	public function populate_from_input()
	{
		if (is_null(static::$validation))
		{
			throw new NoValidationException('No validation');
		}

		$input = Input::all();

		// Remove _wysihtml5_mode entirely.
		unset($input['_wysihtml5_mode']);

		$this->fill($input);
	}

	/**
	 * Clears the in memory and disc cache generated by the all_as_list function.
	 * 
	 * @param int $year The year to remove in addition to flashing the cache of all elements.
	 * @return void
	 */
	public static function clear_all_as_list_cache($year = false)
	{
		$model = get_called_class();

		// Flash the in memory cache.
		$model::$list_cache = false;

		// Flash the disc cache and the year if asked for.
		Cache::forget("$model--options-list");

		if ($year)
		{
			Cache::forget("$model-$year-options-list");
		}
	}

	/**
	 * Override Eloquent's save so that we generate a new json file for our API
	 */
	public function save()
	{
		$saved = parent::save();

		if ($saved)
		{	
			static::clear_all_as_list_cache($this->year);
			static::generate_json();
		}

		return $saved;
	}

	/**
	 * Generate a json file that represents the records in this model
	 */
	private static function generate_json()
	{
		$cache_location = path('storage') .'api/';
		$cache_file = $cache_location.get_called_class().'.json';
		$data = array();

		foreach (static::all() as $record) {
			$data[$record->id] = $record->to_array();
		}

		// if our $cache_location isnt available, create it
		if (!is_dir($cache_location)) 
		{
			mkdir($cache_location, 0755, true);
		}

		file_put_contents($cache_file, json_encode($data));
	}

	/**
	 * This function replaces the passed-in ids with their actual record
	 */
	public static function replace_ids_with_values($ids)
	{
		$ds_fields = static::where_in('id', explode(',',$ids))->get();
		$values = array();

		foreach ($ds_fields as $ds_field) 
		{
			$values[$ds_field->id] = $ds_field->to_array();
		}

		return $values;
	}
}

class NoValidationException extends \Exception {}