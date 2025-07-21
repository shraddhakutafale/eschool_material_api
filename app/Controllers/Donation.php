<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\DonationModel;
use App\Models\TransactionModel;
use CodeIgniter\API\ResponseTrait;
use App\Libraries\TenantService;
use Config\Database;

class Donation extends BaseController
{
    use ResponseTrait;

  public function index()
    {
        // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
         // Load UserModel with the tenant database connection
        $donationModel = new DonationModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $donationModel->findAll(),
        ];
        return $this->respond($response, 200);
    }


    // public function getDonationsPaging()
    // {
    //     $input = $this->request->getJSON();

    //     // Get the page number from the input, default to 1 if not provided
    //     $page = isset($input->page) ? $input->page : 1;
    //     $perPage = isset($input->perPage) ? $input->perPage : 10;
    //     $sortField = isset($input->sortField) ? $input->sortField : 'donationId';
    //     $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    //     $search = isset($input->search) ? $input->search : '';
    //     $filter = $input->filter;
        

    //     $tenantService = new TenantService();
        
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    //     // Load StaffModel with the tenant database connection
    //     $donationModel = new DonationModel($db);
    //     $transactionModel = new TransactionModel($db);
    //     $query = $donationModel
    //     ->join('transaction_mst', 'transaction_mst.memberId = donation_mst.donationId', 'left')  
    //     ->select('donation_mst.*, transaction_mst.transactionId, transaction_mst.transactionFor, 
    //             transaction_mst.transactionNo, transaction_mst.paymentMode, transaction_mst.amount, 
    //             transaction_mst.status, transaction_mst.razorpayNo, transaction_mst.transactionDate')  
    //     ->where('transaction_mst.transactionFor !=', 'member'); 
    

    //     $donation = $donationModel->orderBy($sortField, $sortOrder)->like('name', $search)->orLike('mobileNo', $search)->paginate($perPage, 'default', $page);
    //     if ($filter) {
    //         $filter = json_decode(json_encode($filter), true);
    //         $donation = $donationModel->like($filter)->paginate($perPage, 'default', $page);   
    //     }
    //     $pager = $donationModel->pager;

    //     $response = [
    //         "status" => true,
    //         "message" => "All Vendor Data Fetched",
    //         "data" => $donation,
    //         "pagination" => [
    //             "currentPage" => $pager->getCurrentPage(),
    //             "totalPages" => $pager->getPageCount(),
    //             "totalItems" => $pager->getTotal(),
    //             "perPage" => $perPage
    //         ]
    //     ];
    //     return $this->respond($response, 200);
    // }

    public function getDonationsPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'donationId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = isset($input->filter) ? $input->filter : null;

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        // Load models with the tenant database connection
        $donationModel = new DonationModel($db);
        $transactionModel = new TransactionModel($db);

        // Start building the query for donations
        $query = $donationModel
            ->join('transaction_mst', 'transaction_mst.memberId = donation_mst.donationId', 'left')  // Corrected join condition
            ->select('donation_mst.*, transaction_mst.transactionId, transaction_mst.transactionFor, 
                    transaction_mst.transactionNo, transaction_mst.paymentMode, transaction_mst.amount, 
                    transaction_mst.status, transaction_mst.razorpayNo, transaction_mst.transactionDate')
            ->where('transaction_mst.transactionFor !=', 'member')
            ->where('donation_mst.businessId', $input->businessId); 
        
        // Apply search filters (name or mobileNo)
        if ($search) {
            $query->groupStart()
                ->like('donation_mst.name', $search)
                ->orLike('donation_mst.mobileNo', $search)
                ->groupEnd();
        }

        // Apply additional filters from the input (if any)
        if ($filter) {
            $filter = json_decode(json_encode($filter), true);
            foreach ($filter as $key => $value) {
                if (in_array($key, ['name', 'mobileNo', 'amount', 'status'])) {
                    $query->like('donation_mst.' . $key, $value);  // Apply LIKE filter for specific fields
                } elseif ($key === 'createdDate') {
                    $query->where('donation_mst.' . $key, $value);  // Exact match filter for createdDate
                }
            }

            // Apply Date Range Filter (startDate and endDate)
            if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
                $query->where('donation_mst.createdDate >=', $filter['startDate'])
                    ->where('donation_mst.createdDate <=', $filter['endDate']);
            }

            // Apply Last 7 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
                $last7DaysStart = date('Y-m-d', strtotime('-7 days'));
                $query->where('donation_mst.createdDate >=', $last7DaysStart);
            }

            // Apply Last 30 Days Filter if requested
            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
                $last30DaysStart = date('Y-m-d', strtotime('-30 days'));
                $query->where('donation_mst.createdDate >=', $last30DaysStart);
            }
        }

        // Apply sorting and pagination
        $donations = $query->orderBy($sortField, $sortOrder)->paginate($perPage, 'default', $page);
        
        // Get pagination details
        $pager = $donationModel->pager;

        // Prepare response
        $response = [
            "status" => true,
            "message" => "All Donation Data Fetched",
            "data" => $donations,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];

        return $this->respond($response, 200);
    }


    public function getDonationsWebsite()
    {
      // Insert the product data into the database
      $tenantService = new TenantService();
      // Connect to the tenant's database
      $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
      
        // Load UserModel with the tenant database connection
        $DonationModel = new DonationModel($db);
        $donations = $DonationModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $donations], 200);
    }
    public function create()
    {
        $input = $this->request->getJson();
        log_message('info', json_encode($input));
        $rules = [
            'name'=> ['rules' => 'required'], 
            'mobileNo'=> ['rules' => 'required'], 
            'financialYear'=> ['rules' => 'required'],
            'amount'=> ['rules' => 'required'],
            'transactionNo' => ['rules' => 'required'],
            'transactionDate' => ['rules' => 'required'],
            'paymentMode' => ['rules' => 'required'],
            'razorpayNo' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
           // Insert the product data into the database
         $tenantService = new TenantService();
         // Connect to the tenant's database
         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        
    
            // Retrieve the last donation's receipt number
            $model = new DonationModel($db);
            $lastDonation = $model->select('receiptNo')->orderBy('donationId', 'DESC')->first();
    
            // Generate the next receipt number
            if ($lastDonation) {
                // Extract numeric part from the last receiptNo (e.g., SPG00001 -> 00001)
                preg_match('/(\d+)$/', $lastDonation['receiptNo'], $matches);
                $lastNumber = (int) $matches[0]; // The numeric part of the receiptNo
                $nextNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT); // Increment and pad to 5 digits
            } else {
                // If no donations exist, start from SPG00001
                $nextNumber = '00001';
            }
    
            // Format the receiptNo (e.g., SPG00001, SPG00002, ...)
            $newReceiptNo = 'SPG' . $nextNumber;
    
            // Prepare donation data
            $donation = [
                'name' => $input->name,
                'aadharCard' => $input->aadharCard,
                'email' => $input->email,
                'panNo' => $input->panNo,
                'mobileNo' => $input->mobileNo,
                'address' => $input->address,
                'financialYear' => $input->financialYear,
                'amount' => $input->amount,
                'receiptNo' => $newReceiptNo,
                'businessId' => $input->businessId ?? null, // Use businessId from input if available
            ];
    
            // Insert the donation record into the database
            $donationId = $model->insert($donation);
    
            // Prepare transaction data
            $transaction = [
                'memberId' => $donationId,
                'transactionFor' => 'donation',
                'transactionNo' => $input->transactionNo,
                'transactionDate' => $input->transactionDate,
                'razorpayNo' => $input->razorpayNo, // Optional field
                'amount' => $input->amount,
                'status' => 'success',
                'paymentMode' => $input->paymentMode,
                'receiptNo' => $newReceiptNo, // Store the new receipt number in the transaction
                'businessId' => $input->businessId ?? null // Use businessId from input if available
            ];
    
            // Insert the transaction record into the database
            $transactionModel = new TransactionModel($db);
            $transactionModel->insert($transaction);
            // log_message('Donation Success',$receiptNo);
            log_message('info', 'Donation successfully added with Receipt No: ' . $newReceiptNo);

            // Respond with success message
            return $this->respond(['status' => true, 'message' => 'Donation Added Successfully' ,'data' => $newReceiptNo], 200);
        } else {
            // Validation failed, return errors
            log_message('error', json_encode($this->validator->getErrors()));
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
            'donationId' => ['rules' => 'required|numeric'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
          // Insert the product data into the database
            $tenantService = new TenantService();
             // Connect to the tenant's database
           $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        
            $model = new DonationModel($db);

            // Retrieve the course by eventId
            $donationId = $input->donationId;
            $donation = $model->find($donationId); // Assuming find method retrieves the course

            if (!$donation) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $donation = [
                'name' => $input->name,
                'aadharCard' => $input->aadharCard,
                'email' => $input->email,
                'panNo' => $input->panNo,
                'mobileNo' => $input->mobileNo,
                'address' => $input->address,
                'financialYear' => $input->financialYear,
                'amount' => $input->amount,

            ];


            // Update the course with new data
            $updated = $model->update($donationId, $donation);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Donation Updated Successfully'], 200);
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
            'donationId' => ['rules' => 'required'], 
        ];

        // Validate the input
        if ($this->validate($rules)) {
          // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
         $model = new DonationModel($db);

            // Retrieve the course by eventId
            $donationId = $input->donationId;
            $donation = $model->find($donationId); // Assuming find method retrieves the course

            if (!$donation) {
                return $this->fail(['status' => false, 'message' => 'Donation not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($donationId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Donation Deleted Successfully'], 200);
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
            'name'=> ['rules' => 'required'], 
            'mobileNo'=> ['rules' => 'required'], 
            'financialYear'=> ['rules' => 'required'],
            'amount'=> ['rules' => 'required'],
            'transactionNo' => ['rules' => 'required'],
            'transactionDate' => ['rules' => 'required'],
            'paymentMode' => ['rules' => 'required'],
            'status' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
          // Insert the product data into the database
         $tenantService = new TenantService();
            // Connect to the tenant's database
         $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        
            // Retrieve the last donation's receipt number
            $model = new DonationModel($db);
            $lastDonation = $model->select('receiptNo')->orderBy('donationId', 'DESC')->first();
    
            // Generate the next receipt number
            if ($lastDonation) {
                // Extract numeric part from the last receiptNo (e.g., SPG00001 -> 00001)
                preg_match('/(\d+)$/', $lastDonation['receiptNo'], $matches);
                $lastNumber = (int) $matches[0]; // The numeric part of the receiptNo
                $nextNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT); // Increment and pad to 5 digits
            } else {
                // If no donations exist, start from SPG00001
                $nextNumber = '00001';
            }
    
            // Format the receiptNo (e.g., SPG00001, SPG00002, ...)
            $newReceiptNo = 'SPG' . $nextNumber;
    
            // Prepare donation data
            $donation = [
                'name' => $input->name,
                'mobileNo' => $input->mobileNo,
                'financialYear' => $input->financialYear,
                'amount' => $input->amount,
                'receiptNo' => $newReceiptNo, // Use the newly generated receipt number
            ];
    
            // Insert the donation record into the database
            $donationId = $model->insert($donation);
    
            // Prepare transaction data
            $transaction = [
                'memberId' => $donationId,
                'transactionFor' => 'donation',
                'transactionNo' => $input->transactionNo,
                'transactionDate' => $input->transactionDate,
                'razorpayNo' => $input->razorpayNo, // Optional field
                'amount' => $input->amount,
                'paymentMode' => $input->paymentMode,
                'status' => $input->status,
                'receiptNo' => $newReceiptNo // Store the new receipt number in the transaction

            ];
    
            // Insert the transaction record into the database
            $transactionModel = new TransactionModel($db);
            $transactionModel->insert($transaction);
            // log_message('Donation Success',$receiptNo);
            log_message('info', 'Donation successfully added with Receipt No: ' . $newReceiptNo);

            // Respond with success message
            return $this->respond(['status' => true, 'message' => 'Donation Added Successfully' ,'data' => $newReceiptNo], 200);
        } else {
            // Validation failed, return errors
            log_message('error', json_encode($this->validator->getErrors()));
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ];
            return $this->fail($response, 409);
        }
    }
    


}
