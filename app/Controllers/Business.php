<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\BusinessModel;
use App\Models\BusinessCategoryModel;

class Business extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $businessModel = new BusinessModel();
        $businessCategoryModel = new BusinessCategoryModel();

        $businesses = $businessModel->findAll();
        foreach ($businesses as $key => $business) {
            $businessCategory = $businessCategoryModel->where('categoryId', $business['businessCategoryId'])->first();
            $businesses[$key]['businessCategory'] = $businessCategory;
        }

        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $businesses], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'businessName' => ['rules' => 'required'],
            'businessDesc' => ['rules' => 'required'],


            'address' => ['rules' => 'required'],

            'businessCategoryId' => ['rules' => 'required'],

            'secondaryContactNo' => ['rules' => 'required'],
            'tags' => ['rules' => 'required'],

            'email' => ['rules' => 'required'],

            'createdDate' => ['rules' => 'required'],


            'primaryContactNo' => ['rules' => 'required'],

        ];
  
        if($this->validate($rules)){
            $model = new BusinessModel();
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Page Added Successfully'], 200);
        }else{
            $response = [
                'status'=>false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response , 409);
             
        }
            
    }

    public function getBusinessesPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        // Define the number of items per page
        $perPage = isset($input->perPage) ? $input->perPage : 10;

        $businessModel = new BusinessModel();
        $businessCategoryModel = new BusinessCategoryModel();

        $businesses = $businessModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $businessModel->pager;

        foreach ($businesses as $key => $business) {
            $businessCategory = $businessCategoryModel->where('categoryId', $business['businessCategoryId'])->first();
            $businesses[$key]['businessCategory'] = $businessCategory;
        }

        $response = [
            'status' => true,
            'message' => 'All Business Fetched',
            'data' => $businesses,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $pager->getPerPage()
            ]
        ];

        return $this->respond($response, 200);
    }

    public function update($id = null)
    {
        // Ensure the business ID is provided
        if (!$id) {
            return $this->failNotFound('Business ID is required');
        }
    
        // Retrieve JSON data from the request
        $input = $this->request->getJSON();
    
        // Define validation rules
        $rules = [
            'businessName' => ['rules' => 'required'],
            'businessDesc' => ['rules' => 'required'],
            'address' => ['rules' => 'required'],
            'businessCategoryId' => ['rules' => 'required'],
            'primaryContactNo' => ['rules' => 'required'],
            'secondaryContactNo' => ['rules' => 'required'],
            'email' => ['rules' => 'required|valid_email'],
            'tags' => ['rules' => 'required']
        ];
    
        // Validate input data
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
    
        // // Check if logo or cover files are uploaded
        // $logoFile = $this->request->getFile('businessLogo');
        // $coverFile = $this->request->getFile('businessCover');
    
        $data = [
            'businessName' => $input->businessName,
            'businessDesc' => $input->businessDesc,
            'address' => $input->address,
            'businessCategoryId' => $input->businessCategoryId,
            'primaryContactNo' => $input->primaryContactNo,
            'secondaryContactNo' => $input->secondaryContactNo,
            'email' => $input->email,
            'tags' => $input->tags,
        ];
    
        // // Handle logo upload if provided
        // if ($logoFile && $logoFile->isValid() && !$logoFile->hasMoved()) {
        //     $newLogoName = $logoFile->getRandomName();
        //     $logoFile->move(WRITEPATH . '../public/uploads', $newLogoName);
        //     $data['businessLogo'] = $newLogoName;
        // }
    
        // // Handle cover upload if provided
        // if ($coverFile && $coverFile->isValid() && !$coverFile->hasMoved()) {
        //     $newCoverName = $coverFile->getRandomName();
        //     $coverFile->move(WRITEPATH . '../public/uploads', $newCoverName);
        //     $data['businessCover'] = $newCoverName;
        // }
    
        $businessModel = new BusinessModel();
        $result = $businessModel->update($id, $data);
    
        if ($result) {
            return $this->respond(['status' => true, 'message' => 'Business updated successfully', 'data' => $data], 200);
        } else {
            return $this->failServerError('Failed to update business');
        }
    }
    
    
}
