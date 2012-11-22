<?php

class ProgrammeSections_Controller extends Admin_Controller
{

    public $restful = true;
    public $views = 'sections';
    protected $model = 'ProgrammeSection';

    public function get_index()
    {
    	//$this->data[$this->views] = ProgrammeSection::order_by('order','asc')->get();
       // return View::make('admin.'.$this->views.'.index',$this->data);
       return Redirect::to('/fields/programmes');
    }

    public function get_edit($object_id = false){
    	// Do our checks to make sure things are in place
    	if(!$object_id) return Redirect::to($this->views);
    	$object = ProgrammeSection::find($object_id);
    	if(!$object) return Redirect::to($this->views);
    	$this->data['section'] = $object;
      
    	return View::make('admin.'.$this->views.'.form',$this->data);
    }

    /**
     * Our user subject create function
     *
     **/
    public function get_create(){
        $this->data['create'] = true;

        return View::make('admin.'.$this->views.'.form',$this->data);
    }

    public function post_delete(){
        $rules = array(
            'id'  => 'required|exists:programmesections',
        );
        $validation = Validator::make(Input::all(), $rules);
        if ($validation->fails())
        {
            Messages::add('error','You tried to delete a user that doesn\'t exist.');
            return Redirect::to('/fields/programmes');
        }else{
            $section = ProgrammeSection::find(Input::get('id'));
            $section->delete();
            Messages::add('success','Section Removed');
            return Redirect::to('/fields/programmes');
        }
    }

    public function post_create(){
        $rules = array(
            'name'  => 'required|unique:programmesections|max:255',
        );
        $validation = Validator::make(Input::all(), $rules);
        if ($validation->fails())
        {
            Messages::add('error',$validation->errors->all());
            return Redirect::to('/'.$this->views.'/create')->with_input();
        }else{
            $section = new ProgrammeSection;
            $section->name = Input::get('name');

            $section->save();
 
            Messages::add('success','New Section Added');
            return Redirect::to('/fields/programmes');
        }
    }

    public function post_edit(){
        
        $rules = array(
            'id'  => 'required|exists:programmesections,id',
            'name'  => 'required|max:255|unique:programmesections,name,'.Input::get('id'),
        );
        
        $validation = Validator::make(Input::all(), $rules);
        
        if ($validation->fails())
        {
            Messages::add('error',$validation->errors->all());
            return Redirect::to('/'.$this->views.'/edit/'.Input::get('id'));
        }
        else
        {
            $section = ProgrammeSection::find(Input::get('id'));
   
            $section->name = Input::get('name');

            $section->save();

            Messages::add('success','Section updated');
            return Redirect::to('/fields/programmes');
        }
    }
    
    /**
     * Routing for POST /reorder
     *
     * This allows fields to be reordered via an AJAX request from the UI
     */
    public function post_reorder()
    {
        $model = $this->model;
        $model::reorder(Input::get('order'));
        die();
    }

}