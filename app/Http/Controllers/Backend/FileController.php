<?php

namespace Kommercio\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kommercio\Http\Controllers\Controller;
use Kommercio\Models\File;
use Kommercio\Models\Media;

class FileController extends Controller{
    public function upload(Request $request)
    {
        $rules = $this->getRules($request, $request->get('type', 'image'));

        $this->validate($request, $rules);

        $uploadedFiles = [];

        foreach($request->files as $file_name=>$files){
            foreach($files as $file_idx=>$file){
                $uploadFile = new File();

                if($uploadFile->saveFile($file)){
                    $uploadedFiles[] = [
                        'id' => $uploadFile->id,
                        'filename' => $uploadFile->filename,
                        'path' => $uploadFile->folder.$uploadFile->filename
                    ];
                }
            }
        }

        return response()->json([
            'files' => $uploadedFiles
        ]);
    }

    public function getRules(Request $request, $type)
    {
        $rules = [];

        foreach($request->files as $file_name=>$files){
            $rules[$file_name] = 'required';

            foreach($files as $file_idx=>$file){
                $rules[$file_name.'.'.$file_idx] = $this->getFileRule($type).'|max:'.File::MAXIMUM_SIZE;
            }
        }

        return $rules;
    }

    public function getFileRule($type)
    {
        switch($type){
            case 'image':
                return 'image';
        }
    }
}