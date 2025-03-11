<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\MemberModel;
use App\Models\TransactionModel;
use App\Libraries\TenantService;
use Config\Database;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

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
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'memberId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load StaffModel with the tenant database connection
        $memberModel = new MemberModel($db);
        $transactionModel = new TransactionModel($db); // Assuming you have a TransactionModel

        $query = $memberModel
        ->join('transaction_mst', 'transaction_mst.memberId = member_mst.memberId', 'left')  // Join the transaction_mst table with MemberModel
        ->select('member_mst.*, transaction_mst.transactionId, transaction_mst.transactionFor, transaction_mst.transactionNo, 
                  transaction_mst.paymentMode, transaction_mst.amount, transaction_mst.status, transaction_mst.razorpayNo, 
                  transaction_mst.transactionDate')
        ->where('transaction_mst.transactionFor !=', 'donation'); 
    

        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['name', 'mobileNo', 'fees', 'type'])) {
                    $query->like($key, $value); // LIKE filter for specific fields
                } else if ($key === 'createdDate') {
                    $query->where($key, $value); // Exact match filter for createdDate
                }
            }
    
            // Apply Date Range Filter (startDate and endDate)
            if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
                $query->where('createdDate >=', $filter['startDate'])
                      ->where('createdDate <=', $filter['endDate']);
            }
    
            // Apply Last 7 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
                $last7DaysStart = date('Y-m-d', strtotime('-7 days'));  // 7 days ago from today
                $query->where('createdDate >=', $last7DaysStart);
            }
    
            // Apply Last 30 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
                $last30DaysStart = date('Y-m-d', strtotime('-30 days'));  // 30 days ago from today
                $query->where('createdDate >=', $last30DaysStart);
            }
        }

        $member = $memberModel->orderBy($sortField, $sortOrder)->like('name', $search)->orLike('mobileNo', $search)->paginate($perPage, 'default', $page);
        if ($filter) {
            $filter = json_decode(json_encode($filter), true);
            $member = $memberModel->like($filter)->paginate($perPage, 'default', $page);   
        }
        $pager = $memberModel->pager;

        $response = [
            "status" => true,
            "message" => "All Vendor Data Fetched",
            "data" => $member,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];

        return $this->respond($response, 200);
    }
    

    public function getMembersWebsite()
    {
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 

        // Load UserModel with the tenant database connection
        $MemberModel = new MemberModel($db);
        $members = $MemberModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $members], 200);
    }

  
   
    public function create()
    {
        $input = $this->request->getPost();
        log_message('info', json_encode($input));
        $rules = [
            'type'=> ['rules' => 'required'], 
            'name'=> ['rules' => 'required'], 
            'mobileNo'=> ['rules' => 'required'],
            'aadharCard'=> ['rules' => 'required'],
            'address'=> ['rules' => 'required'],
            'fees'=> ['rules' => 'required'],
            'transactionNo' => ['rules' => 'required'],
            'transactionDate' => ['rules' => 'required'],
            'paymentMode' => ['rules' => 'required'],
            'razorpayNo' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
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
            // Handle image upload for the cover image
            $profileImage = $this->request->getFile('profileImage');
            $profileImageName = null;

            if ($profileImage && $profileImage->isValid() && !$profileImage->hasMoved()) {
                // Define the upload path for the cover image
                $profileImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/profileImage/';
                if (!is_dir($profileImagePath)) {
                    mkdir($profileImagePath, 0777, true); // Create directory if it doesn't exist
                }

                // Move the file to the desired directory with a unique name
                $profileImageName = $profileImage->getRandomName();
                $profileImage->move($profileImagePath, $profileImageName);

                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $profileImageUrl = 'uploads/profileImage/' . $profileImageName;
                $profileImageUrl = str_replace('uploads/profileImage/', '', $profileImageUrl);

                // Add the cover image URL to the input data
                $profileImageUrl = $decoded->tenantName . '/profileImage/' . $profileImageUrl; 
                // Add the profile image URL to the input data
                 $input['profileImage'] = $profileImageUrl;

                
            }

            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
    
    
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
                'type' => $input['type'],
                'name' => $input['name'],
                'dob' => $input['dob'],
                'bloodGroup' => $input['bloodGroup'],
                'aadharCard' => $input['aadharCard'],
                'email' => $input['email'],
                'mobileNo' => $input['mobileNo'],
                'address' => $input['address'],
                'state' => $input['state'],
                'district' => $input['district'],
                'taluka' => $input['taluka'],
                'pincode' => $input['pincode'],
                'fees' => $input['fees'],
                'receiptNo' => $newReceiptNo,  // This will be updated later
                'profileImage' => isset($input['profileImage']) ? $input['profileImage'] : null  // Save the profile image URL to the database

            ];
    
            $memberId = $model->insert($member);
    
            // Prepare the transaction data with the new receipt number
            $transaction = [
                'memberId' => $memberId,
                'transactionFor' => 'member',
                'transactionNo' => $input['transactionNo'],
                'transactionDate' => $input['transactionDate'],
                'razorpayNo' => $input['razorpayNo'],
                'amount' => $input['fees'],
                'paymentMode' => $input['paymentMode'],
                'receiptNo' => $newReceiptNo // Store the new receipt number in the transaction
            ];
    
            // Insert the transaction with the new receipt number
            $modelTransaction = new TransactionModel($db);
            $modelTransaction->insert($transaction);
            // log_message('Member Success',$newReceiptNo);
            log_message('info', 'Member successfully added with Receipt No: ' . $newReceiptNo);

            // Return a success response
            return $this->respond(['status' => true, 'message' => 'Member Added Successfully','data'=>$newReceiptNo], 200);
        } else {
            // log_message('Member Failure',$this->validator->getErrors());
            log_message('error', json_encode($this->validator->getErrors()));

            // Return validation errors
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
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

         // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
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
                'aadharCard' => $input->aadharCard,
                'email' => $input->email,
                'mobileNo' => $input->mobileNo,
                'address' => $input->address,
                'state' => $input->state,
                'district' => $input->district,
                'taluka' => $input->taluka,
                'pincode' => $input->pincode,
                'fees' => $input->fees,
         
                
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

            // Insert the product data into the database
             $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
            $model = new MemberModel($db);

            // Retrieve the course by eventId
            $memberId = $input->memberId;
            $member = $model->find($memberId); // Assuming find method retrieves the course

            if (!$member) {
                return $this->fail(['status' => false, 'message' => 'Member not found'], 404);
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



    public function createWeb()
    {
        $input = $this->request->getJSON();
        log_message('info', json_encode($input));
        $rules = [
            'type'=> ['rules' => 'required'], 
            'name'=> ['rules' => 'required'], 
            'mobileNo'=> ['rules' => 'required'],
            'fees'=> ['rules' => 'required'],
            'transactionNo' => ['rules' => 'required'],
            'transactionDate' => ['rules' => 'required'],
            'paymentMode' => ['rules' => 'required'],
            'status' => ['rules' => 'required'],
            'razorpayNo' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
    
    
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
            // log_message('Member Success',$newReceiptNo);
            log_message('info', 'Member successfully added with Receipt No: ' . $newReceiptNo);

            // Return a success response
            return $this->respond(['status' => true, 'message' => 'Member Added Successfully','data'=>$newReceiptNo], 200);
        } else {
            // log_message('Member Failure',$this->validator->getErrors());
            log_message('error', json_encode($this->validator->getErrors()));

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