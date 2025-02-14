<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\MemberModel;
use App\Models\TransactionModel;
use App\Libraries\TenantService;
use Config\Database;

class Member extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();

        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $memberModel = new MemberModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $memberModel->findAll(),
        ];
        return $this->respond($response, 200);
    }

    public function getMembersPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        // Define the number of members per page
        $perPage = isset($input->perPage) ? $input->perPage : 10;

        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $MemberModel = new MemberModel($db);
        $members = $MemberModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $MemberModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $members,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalMembers" => $pager->getTotal(),
                "perPage" => $perPage
            ]   
        ];
        return $this->respond($response, 200);
    }

    public function getMembersWebsite()
    {
        // Retrieve tenantConfig from the headers
        $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
        if (!$tenantConfigHeader) {
            throw new \Exception('Tenant configuration not found.');
        }

        // Decode the tenantConfig JSON
        $tenantConfig = json_decode($tenantConfigHeader, true);

        if (!$tenantConfig) {
            throw new \Exception('Invalid tenant configuration.');
        }

        // Connect to the tenant's database
        $db = Database::connect($tenantConfig);
        // Load UserModel with the tenant database connection
        $MemberModel = new MemberModel($db);
        $members = $MemberModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $members], 200);
    }

  
    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'type'=> ['rules' => 'required'], 
            'name'=> ['rules' => 'required'], 
            'dob'=> ['rules' => 'required'],
            'bloodGroup'=> ['rules' => 'required'],
            'email'=> ['rules' => 'required'],
            'mobileNo'=> ['rules' => 'required'], 
            'address'=> ['rules' => 'required'], 
            'state'=> ['rules' => 'required'], 
            'district'=> ['rules' => 'required'], 
            'taluka'=> ['rules' => 'required'], 
            'pincode'=> ['rules' => 'required'], 
            'fees'=> ['rules' => 'required'],
            'transactionId'=> ['rules' => 'required'],
            'aadharCard'=> ['rules' => 'required'],
            'file'=> ['rules' => 'required']   
        ];
  
        if($this->validate($rules)){
            // Retrieve tenantConfig from the headers
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
            if (!$tenantConfigHeader) {
                throw new \Exception('Tenant configuration not found.');
            }

            // Decode the tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);

            if (!$tenantConfig) {
                throw new \Exception('Invalid tenant configuration.');
            }

            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new MemberModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Member Added Successfully'], 200);
        }else{
            $response = [
                'status'=>false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response , 409);
             
        }
            
    }

    public function update()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the course
        $rules = [
            'memberId' => ['rules' => 'required|numeric'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Retrieve tenantConfig from the headers
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
            if (!$tenantConfigHeader) {
                throw new \Exception('Tenant configuration not found.');
            }

            // Decode the tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);

            if (!$tenantConfig) {
                throw new \Exception('Invalid tenant configuration.');
            }

            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new MemberModel($db);

            // Retrieve the course by eventId
            $memberId = $input->memberId;
            $member = $model->find($memberId); // Assuming find method retrieves the course

            if (!$member) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $updateData = [
                'type' => $input->type,
                'name' => $input->name,
                'dob' => $input->dob,
                'bloodGroup' => $input->bloodGroup,
                'email' => $input->email,
                'mobileNo' => $input->mobileNo,
                'address' => $input->address,
                'state' => $input->state,
                'district' => $input->district,
                'taluka' => $input->taluka,
                'pincode' => $input->pincode,
                'fees' => $input->fees,
                'transactionId' => $input->transactionId,
                'aadharCard' => $input->aadharCard,
                'file' => $input->file

            ];

            // Update the course with new data
            $updated = $model->update($memberId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Member Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update course'], 500);
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
            'memberId' => ['rules' => 'required'], 
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Retrieve tenantConfig from the headers
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
            if (!$tenantConfigHeader) {
                throw new \Exception('Tenant configuration not found.');
            }

            // Decode the tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);

            if (!$tenantConfig) {
                throw new \Exception('Invalid tenant configuration.');
            }

            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new MemberModel($db);

            // Retrieve the course by eventId
            $memberId = $input->memberId;
            $member = $model->find($memberId); // Assuming find method retrieves the course

            if (!$member) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($memberId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Member Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete course'], 500);
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


    // website api
    // public function createWeb()
    // {
    //     $input = $this->request->getJSON();
    //     $rules = [
    //         // 'type'=> ['rules' => 'required'], 
    //         'name'=> ['rules' => 'required'], 
    //         // 'dob'=> ['rules' => 'required'],
    //         // 'bloodGroup'=> ['rules' => 'required'],
    //         // 'email'=> ['rules' => 'required'],
    //         'mobileNo'=> ['rules' => 'required'], 
    //         // 'address'=> ['rules' => 'required'], 
    //         // 'state'=> ['rules' => 'required'], 
    //         // 'district'=> ['rules' => 'required'], 
    //         // 'taluka'=> ['rules' => 'required'], 
    //         // 'pincode'=> ['rules' => 'required'], 
    //         // 'fees'=> ['rules' => 'required'],
    //         // 'aadharCard'=> ['rules' => 'required'],
    //         'transactionNo' => ['rules' => 'required'],
    //         'transactionDate' => ['rules' => 'required'],
    //         'paymentMode' => ['rules' => 'required'],
    //         'status' => ['rules' => 'required']
    //     ];

    //     if($this->validate($rules)){
    //         // Retrieve tenantConfig from the headers
    //         $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
    //         if (!$tenantConfigHeader) {
    //             throw new \Exception('Tenant configuration not found.');
    //         }

    //         // Decode the tenantConfig JSON
    //         $tenantConfig = json_decode($tenantConfigHeader, true);

    //         if (!$tenantConfig) {
    //             throw new \Exception('Invalid tenant configuration.');
    //         }

    //         // Connect to the tenant's database
    //         $db = Database::connect($tenantConfig);

    //         $member = [
    //             'type' => $input->type,
    //             'name' => $input->name,
    //             'dob' => $input->dob,
    //             'bloodGroup' => $input->bloodGroup,
    //             'email' => $input->email,
    //             'mobileNo' => $input->mobileNo,
    //             'address' => $input->address,
    //             'state' => $input->state,
    //             'district' => $input->district,
    //             'taluka' => $input->taluka,
    //             'pincode' => $input->pincode,
    //             'fees' => $input->fees,
    //             'aadharCard' => $input->aadharCard,
    //             'receiptNo' => $input->receiptNo,
    //         ];

    //         $model = new MemberModel($db);
        
    //         $memberId = $model->insert($member);
    //         $transaction = [
    //             'memberId' => $memberId,
    //             'transactionFor' => 'member',
    //             'transactionNo' => $input->transactionNo,
    //             'transactionDate' => $input->transactionDate,
    //             'razorpayNo' => $input->razorpayNo,
    //             'amount' => $input->fees,
    //             'paymentMode' => $input->paymentMode,
    //             'status' => $input->status
    //         ];
    //         $modelTransaction = new TransactionModel($db);
    //         $modelTransaction->insert($transaction);
            
    //         return $this->respond(['status'=>true,'message' => 'Member Added Successfully'], 200);
    //     }else{
    //         $response = [
    //             'status'=>false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response , 409);
            
    //     }
            
    // }

    public function createWeb()
    {
        $input = $this->request->getJSON();
        $rules = [
            'name' => ['rules' => 'required'],
            'mobileNo' => ['rules' => 'required'],
            'transactionNo' => ['rules' => 'required'],
            'transactionDate' => ['rules' => 'required'],
            'paymentMode' => ['rules' => 'required'],
            'status' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
            // Retrieve tenantConfig from the headers
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
            if (!$tenantConfigHeader) {
                throw new \Exception('Tenant configuration not found.');
            }
    
            // Decode the tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);
    
            if (!$tenantConfig) {
                throw new \Exception('Invalid tenant configuration.');
            }
    
            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
    
    
            // Insert the member into the database
            $model = new MemberModel($db);
            $lastMember = $model->select('receiptNo')->orderBy('memberId', 'DESC')->first();
            if($lastMember){
                preg_match('/\d+$/', $lastMember['receiptNo'], $matches);
                $lastNumber = (int) $matches[0]; // The numeric part of the receiptNo
                $nextNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            }else{
                $nextNumber = '00001';
            }
    
            // Generate a new receipt number based on the memberId
            $newReceiptNo = 'SPG/S/' . $nextNumber;
    
            // Prepare the member data
            $member = [
                'type' => $input->type,
                'name' => $input->name,
                'dob' => $input->dob,
                'bloodGroup' => $input->bloodGroup,
                'email' => $input->email,
                'mobileNo' => $input->mobileNo,
                'address' => $input->address,
                'state' => $input->state,
                'district' => $input->district,
                'taluka' => $input->taluka,
                'pincode' => $input->pincode,
                'fees' => $input->fees,
                'aadharCard' => $input->aadharCard,
                'receiptNo' => $newReceiptNo,  // This will be updated later
            ];
    
            $memberId = $model->insert($member);
    
            // Prepare the transaction data with the new receipt number
            $transaction = [
                'memberId' => $memberId,
                'transactionFor' => 'member',
                'transactionNo' => $input->transactionNo,
                'transactionDate' => $input->transactionDate,
                'razorpayNo' => $input->razorpayNo,
                'amount' => $input->fees,
                'paymentMode' => $input->paymentMode,
                'status' => $input->status,
                'receiptNo' => $newReceiptNo // Store the new receipt number in the transaction
            ];
    
            // Insert the transaction with the new receipt number
            $modelTransaction = new TransactionModel($db);
            $modelTransaction->insert($transaction);
    
            // Return a success response
            return $this->respond(['status' => true, 'message' => 'Member Added Successfully','data'=>$newReceiptNo], 200);
        } else {
            // Return validation errors
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }

}
