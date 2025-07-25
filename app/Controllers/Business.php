<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\BusinessModel;
use App\Models\BusinessCategoryModel;
use App\Models\BusinessSubCategoryModel;
use App\Models\UserBusiness;

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

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
            'userId' => ['rules' => 'required'],
        ];
  
        if($this->validate($rules)){
            $model = new BusinessModel();
            $userBusiness = new UserBusiness();

            if($model->insert($input)){
                $userBusiness->insert(['userId' => $input->userId, 'businessId' => $model->getInsertID()]);
                return $this->respond(["status" => true, 'message' => 'Business Created Successfully'], 200);
            }else{
                return $this->respond(["status" => false, 'message' => 'Business Not Created'], 200);
            }
             
            
        }else{
            $response = [
                'status'=>false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->response($response , 409);
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

    public function update()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the course
        $rules = [
            'businessId' => ['rules' => 'required|numeric'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           
            // Connect to the tenant's database
            $model = new BusinessModel();

            // Retrieve the course by eventId
            $businessId = $input->businessId;
            $business = $model->find($businessId); // Assuming find method retrieves the course

            if (!$business) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $updateData = [
                'businessName' => $input->businessName,
                'businessDesc' => $input->businessDesc,
                'timings' => $input->timings,
                'aboutUs' => $input->aboutUs,
                'address' => $input->address,
                'tags' => $input->tags,
                'businessCategoryId' => $input->businessCategoryId,
                'photoBase64Url' => $input->photoBase64Url,
                'logoUrl' => $input->logoUrl,
                'themeColor' => $input->themeColor,
                'primaryContactNo' => $input->primaryContactNo,
                'secondaryContactNo' => $input->secondaryContactNo,
                'pageUrl' => $input->pageUrl,
                'userId' => $input->userId,

            ];

            // Update the course with new data
            $updated = $model->update($businessId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Business Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update Business'], 500);
            }
        } else {
            // Validation failed
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }

    public function delete()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the course
        $rules = [
            'businessId' => ['rules' => 'required'], 

        ];

        // Validate the input
        if ($this->validate($rules)) {

          

            // Connect to the tenant's database
            $model = new BusinessModel();

            // Retrieve the business by eventId
            $businessId = $input->businessId;
            $business = $model->find($businessId); 

            if (!$business) {
                return $this->fail(['status' => false, 'message' => 'Business not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($businessId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Business Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete Business'], 500);
            }
        } else {
            // Validation failed
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }

    public function getAllBusinessByUser(){
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
  
        // extract the token from the header
        if(!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }
        
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        $userId = $decoded->userId;
        $model = new UserBusiness();
        $businessModel = new BusinessModel();
        $userBusinesses = $model->where('userId', $userId)->findAll();
        $businesses = array();
        foreach ($userBusinesses as $key => $userBusiness) {
            $business = $businessModel->find($userBusiness['businessId']);
            array_push($businesses, $business);
        }
        return $this->respond(['status' => true, 'message' => 'All Business Fetched', 'data' => $businesses], 200);

    }

    public function getAllBusinessCategory()
    {
        $categories = new BusinessCategoryModel;
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $categories->findAll()], 200);
    }

    public function getAllBusinessSubCategory()
    {
        $input = $this->request->getJSON();
        $subCategories = new BusinessSubCategoryModel;
        if (isset($input->businessCategoryId)) {
            $subCategories = $subCategories->where('categoryId', $input->businessCategoryId);
            return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $subCategories->findAll()], 200);
        }else {
            return $this->respond(["status" => true, "message" => "Category ID is required", "data"=> []], 200);
        }
        
    }
    
}
