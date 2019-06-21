<?php

namespace LadyBird\StreamImport\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use LadyBird\StreamImport\Factory;
use Validator;

class ImportController extends Controller
{
    //private $file_path="file.json";
    private $model;
    protected $saved_file = 'file.json';

    public function getDbCols($source)
    {
        $factory = new Factory();
        $this->model = $factory->make($source);

        if (empty($this->model->getHidden())) {
            return array_unique($this->model->getFillable());
        } else {
            return array_unique(array_merge($this->model->getFillable(), $this->model->getHidden()));
        }
    }

    public function parseImport($source, Request $request)
    {
        $returnArray = [];

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->getRealPath();

        $data = array_map('str_getcsv', file($path));

        if ($request->has('header')) {
            array_shift($data);
        }

        //dd($this);

        Storage::disk('local')->put($this->saved_file, json_encode($data));

        //$csv_data = $data[0];
        $db_cols = $this->getDbCols($source);

        $returnArray['csv_sample_row'] = ($data[0]);
        $returnArray['database_columns'] = array_unique($db_cols);

        //return json_encode($data[0]);

        return response()->json($returnArray, 200);
    }

    public function processImport(Request $request, $source)
    {
        $factory = new Factory();
        $this->model = $factory->make($source);

        $relations = $this->model->relationships();

        //dd($relations);

        $tempArray = [];
        $final_json_array = [];
        $temp_file_contents = Storage::disk('local')->get($this->saved_file);
        $temp_file_contents = json_decode($temp_file_contents, true);

        foreach ($temp_file_contents as $row) {
            foreach ($request->fields as $key => $value) {
                $tempArray[$value] = $row[$key];
            } //foreach inner

            ksort($tempArray);

            foreach ($tempArray as $key => $value) {
                if (in_array(str_replace('_id', '', $key), array_keys($relations))) {
                    $relationForThisKey = $relations[str_replace('_id', '', $key)];
                    $relationModel = new $relationForThisKey['model']();

                    $relationColumns = $relationModel->getConnection()->getSchemaBuilder()->getColumnListing($relationModel->getTable());
                    $searchData = $relationModel->whereLike(array_values($relationColumns), $value)->get();

                    foreach ($searchData as $data) {
                        $replace = $data->id;
                    }
                    $tempArray[$key] = $replace;
                }
            }

            //dd();

            if (empty($headers)) {
                $headers = array_keys($tempArray);
            }

            array_push($final_json_array, $tempArray);
            $tempArray = [];
        } //foreach

        foreach (array_chunk($final_json_array, 1000) as $t) {
            for ($i = 0; $i<1000; $i++) {
                dump($t[$i]);
                $validator = Validator::make($t[$i], $this->model->rules);

                if ($validator->fails()) {
                    return response()->json($validator->messages()->getMessages(), 200);
                } else {
                    $this->model::insert($t[$i]);
                }
            } //for
        } //foreach

        return response()->json(['message' => 'Successfully Inserted'], 200);
    }
}
