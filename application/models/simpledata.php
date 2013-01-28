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
	 *
	 * @param boolean $empty_default_value some select lists can have an empty 'please select' or 'none' value in them. Defaults to false.
	 *
	 * @return array $options List of items in the format id => item_title.
	 */
	public static function all_as_list($year = false, $empty_default_value = 0)
	{
		$model = get_called_class();

		// If this datatype cannot be separated by year, make year false.
		if (!static::$data_by_year) $year = false;
		
		// the 'defaulttonone' cache key is used for options lists which need 'none' in their select listings
		$cache_key = ( $empty_default_value != 0 ) ? "$model-$year-defaulttonone-options-list" : "$model-$year-options-list";

		if (isset(static::$list_cache[$cache_key])) return static::$list_cache[$cache_key];
		
		return static::$list_cache[$cache_key] = Cache::remember($cache_key, function() use ($year, $model, $empty_default_value)
		{
			$options = array();
			// set the 'none' select value, as per the $empty_default_value param
			if ( $empty_default_value != 0 )
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
		
		// empty cache for defaulttonone cache key as well
		Cache::forget("$model--defaulttonone-options-list");

		if ($year)
		{
			Cache::forget("$model-$year-options-list");
			// empty cache for defaulttonone cache key as well
			Cache::forget("$model-$year-defaulttonone-options-list");
		}
	}

	/**
	 * Override Eloquent's save so that we generate a new json file for our API
	 */
	public function save()
	{
		$saved = $this->raw_save();

		if ($saved)
		{	
			static::clear_all_as_list_cache($this->year);
			// Only store / refresh cache if this is NOT a revisionble item
			// revisionble items only store on "make_live" not "save"
			if(!is_subclass_of($this, "Revisionable")){
				static::generate_api_data();
				API::purge_output_cache();
			}
			
		}

		return $saved;
	}

	/**
	 * Raw_save: Call eloquents save method directly to save an item with no special logic.
	 * 
	 */
	public function raw_save()
	{
		return parent::save();
	}

	/**
	 * get API Data
	 * Return cached data from data type
	 *
	 * @param year (Unused - PHP requires signature not to change)
	 * @return data Object
	 */
	public static function get_api_data($year = false)
	{
		// generate keys
		$model = strtolower(get_called_class());
		$cache_key = 'api-'.$model;

		// Get data from cache (or generate it)
		return (Cache::has($cache_key)) ? Cache::get($cache_key) : static::generate_api_data($year);
	}

	/**
	 * generate API data
	 * Get live version of API data from database
	 *
	 * @param year (Unused - PHP requires signature not to change)
	 * @param data (Unused - PHP requires signature not to change)
	 */
	public static function generate_api_data($year = false, $data = false)
	{
		// keys
		$model = strtolower(get_called_class());
		$cache_key = 'api-'.$model;
		// make data
		$data = array();
		foreach (static::all() as $record) {
			$data[$record->id] = $record->to_array();
		}
		// Store data in to cache
		Cache::put($cache_key, $data, 2628000);
		// return
		return $data;
	}

	/**
	 * This function replaces the passed-in ids with their actual record
	 * @param $ids List of ids to lookup
	 * @param $year Unused, but needed for method signature in programme (they have to be the same)
	 * @return array of objects matching id's
	 */
	public static function replace_ids_with_values($ids, $year = false)
	{
		// If nothing is set, return an empty array
		if(trim($ids) == '') return array();
		// Get list of ids to swap out & grab api data from cache
		$id_array = explode(',', $ids);
		$cached_data = static::get_api_data();
		// Create new array of actual values matching the ids from the cache
		$values = array();
		foreach ($id_array as $id) 
		{
			$values[] = isset($cached_data[$id]) ? $cached_data[$id] : '';
		}

		return $values;
	}
	
	public static function all_active()
	{
		return static::where('hidden', '=', false)->get();
	}
	
	public function delete()
	{
		$this->hidden = true;
		$this->save();
	}
	
	/**
	*
	*/
	public function delete_for_test()
	{
		parent::delete();
	}
}

class NoValidationException extends \Exception {}