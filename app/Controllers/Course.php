<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ItemModel;
use App\Models\FeeModel;
use App\Models\ShiftModel;
use App\Models\SubjectModel;
use App\Models\ItemCategory;
use App\Models\ItemFeeMapModel;
use App\Models\ItemShiftMapModel;
use App\Models\ItemSubjectMapModel;


use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Course extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $itemModel = new ItemModel($db);
        $items = $itemModel
            ->where('item_mst.isDeleted', 0)
            ->findAll();
        return $this->respond(['status' => true, 'message' => 'All items fetched successfully', 'data' => $items], 200);
    }

  public function getAllPaging()
{
    $input = $this->request->getJSON();

    // 🔒 Validate JSON input
    if (!$input || !isset($input->itemTypeId) || !isset($input->businessId)) {
        return $this->respond([
            "status" => false,
            "message" => "Invalid or missing input. 'itemTypeId' and 'businessId' are required.",
            "data" => []
        ], 422);
    }

    // ✅ Default values
    $page = isset($input->page) ? $input->page : 1;
    $perPage = isset($input->perPage) ? $input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'itemId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? $input->search : '';
    $filter = isset($input->filter) ? $input->filter : null;

    // ✅ Tenant-aware DB loading
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // ✅ Models
    $itemModel = new ItemModel($db);
    $itemFeeMapModel = new ItemFeeMapModel($db);
    $feeModel = new FeeModel($db);
    $itemShiftMapModel = new ItemShiftMapModel($db);
    $shiftModel = new ShiftModel($db);
    $itemSubjectMapModel = new ItemSubjectMapModel($db);
    $subjectModel = new SubjectModel($db);

    // ✅ Base query
    $query = $itemModel->where('isDeleted', 0)
        ->where('itemTypeId', $input->itemTypeId)
        ->where('businessId', $input->businessId);

    // 🔍 Search
    if (!empty($search)) {
        $query->groupStart()
              ->like('itemName', $search)
              ->orLike('mrp', $search)
              ->groupEnd();
    }

    // 🔍 Filters
    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true); // Ensure it's an array

        foreach ($filter as $key => $value) {
            if (in_array($key, ['itemName', 'mrp', 'sku'])) {
                $query->like($key, $value);
            } else if ($key === 'createdDate') {
                $query->where($key, $value);
            }
        }

        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('createdDate >=', $filter['startDate'])
                  ->where('createdDate <=', $filter['endDate']);
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
            $query->where('createdDate >=', date('Y-m-d', strtotime('-7 days')));
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
            $query->where('createdDate >=', date('Y-m-d', strtotime('-30 days')));
        }
    }

    // 📦 Sorting
    $query->orderBy($sortField, $sortOrder);

    // ✅ Pagination + fetching
    $item = $query->paginate($perPage, 'default', $page);

    // 🔄 Process fees, shifts, subjects
    foreach ($item as $key => $value) {
        // Fees
        $itemFeeMapArray = $itemFeeMapModel->where('itemId', $value['itemId'])->where('isDeleted', 0)->findAll();
        $fees = [];
        foreach ($itemFeeMapArray as $fee) {
            $feeArray = $feeModel->where('feeId', $fee['feeId'])->where('isDeleted', 0)->first();
            if ($feeArray) {
                $fees[] = $feeArray['amount'];
            }
        }
        $item[$key]['fees'] = $fees;

        // Shifts
        $itemShiftMapArray = $itemShiftMapModel->where('itemId', $value['itemId'])->where('isDeleted', 0)->findAll();
        $shifts = [];
        foreach ($itemShiftMapArray as $shift) {
            $shiftArray = $shiftModel->where('shiftId', $shift['shiftId'])->where('isDeleted', 0)->first();
            if ($shiftArray) {
                $shifts[] = $shiftArray['startTime'] . '-' . $shiftArray['endTime'];
            }
        }
        $item[$key]['shifts'] = $shifts;

        // Subjects
        $itemSubjectMapArray = $itemSubjectMapModel->where('itemId', $value['itemId'])->where('isDeleted', 0)->findAll();
        $subjects = [];
        foreach ($itemSubjectMapArray as $subject) {
            $subjectArray = $subjectModel->where('subjectId', $subject['subjectId'])->where('isDeleted', 0)->first();
            if ($subjectArray) {
                $subjects[] = $subjectArray['subjectName'];
            }
        }
        $item[$key]['subjects'] = $subjects;
    }

    // 📊 Pagination info
    $pager = $itemModel->pager;

    // ✅ Response
    return $this->respond([
        "status" => true,
        "message" => "All item data fetched",
        "data" => $item,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ], 200);
}


    public function create()
    {
        // Retrieve the input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'itemName' => ['rules' => 'required']
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
            $coverImage = $this->request->getFile('coverImage');
            $coverImageName = null;

            if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
                // Define the upload path for the cover image
                $coverImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemImages/';
                if (!is_dir($coverImagePath)) {
                    mkdir($coverImagePath, 0777, true); // Create directory if it doesn't exist
                }

                // Move the file to the desired directory with a unique name
                $coverImageName = $coverImage->getRandomName();
                $coverImage->move($coverImagePath, $coverImageName);

                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $coverImageUrl = 'uploads/itemImages/' . $coverImageName;
                $coverImageUrl = str_replace('uploads/itemImages/', '', $coverImageUrl);

                // Add the cover image URL to the input data
                $input['coverImage'] = $decoded->tenantName . '/itemImages/' . $coverImageUrl; 
            }

            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ItemModel($db);
            $itemId = $model->insert($input);

            // Check if feesDetails are provided
            if (isset($input['feesDetails']) && !empty($input['feesDetails'])) {
                // Get the fee details from the input
                $feesDetails = $input['feesDetails'];

                // Ensure there are fee details to process
                if (count($feesDetails) > 0) {
                    // Initialize the ItemFeeMapModel
                    $itemFeeMapModel = new ItemFeeMapModel($db);

                    // Start a transaction for fee mappings to ensure data integrity
                    $db->transStart(); // Begin a transaction

                    // Loop through each feeId and insert the mapping
                    foreach ($feesDetails as $feeId) {
                        // Insert into the ItemFeeMap table
                        $itemFeeMapModel->insert([
                            'itemId' => $itemId,
                            'feeId' => $feeId
                        ]);
                    }

                    // Commit the transaction if all inserts were successful
                    $db->transComplete(); // Complete the transaction

                    // Check if the transaction was successful
                    if ($db->transStatus() === false) {
                        // Rollback if the transaction failed
                        $db->transRollback();
                        // Handle the error (e.g., log it or throw an exception)
                        log_message('error', 'Transaction failed while inserting fee mappings');
                    } else {
                        // Commit successful, you can perform additional actions if needed
                        log_message('info', 'Successfully inserted fee mappings');
                    }
                }
            }

            // Check if shiftDetails are provided
            if (isset($input['shiftDetails']) && !empty($input['shiftDetails'])) {
                // Get the shift details from the input
                $shiftDetails = $input['shiftDetails'];

                // Ensure there are shift details to process
                if (count($shiftDetails) > 0) {
                    
                    // Initialize the ItemShiftMapModel
                    $itemShiftMapModel = new ItemShiftMapModel($db);

                    // Start a transaction for shift mappings to ensure data integrity
                    $db->transStart(); // Begin a transaction

                    // Loop through each shiftId and insert the mapping
                    foreach ($shiftDetails as $shiftId) {
                        
                        // Insert into the ItemShiftMap table
                        $itemShiftMapModel->insert([
                            'itemId' => $itemId,
                            'shiftId' => $shiftId
                        ]);
                    }

                    // Commit the transaction if all inserts were successful
                    $db->transComplete(); // Complete the transaction

                    // Check if the transaction was successful
                    if ($db->transStatus() === false) {
                        // Rollback if the transaction failed
                        $db->transRollback();
                        // Handle the error (e.g., log it or throw an exception)
                        log_message('error', 'Transaction failed while inserting shift mappings');
                    } else {
                        // Commit successful, you can perform additional actions if needed
                        log_message('info', 'Successfully inserted shift mappings');
                    }
                }
            }

            // Check if subjectDetails are provided
            if (isset($input['subjectDetails']) && !empty($input['subjectDetails'])) {
                // Get the subject details from the input
                $subjectDetails = $input['subjectDetails'];

                // Ensure there are subject details to process
                if (count($subjectDetails) > 0) {
                    // Initialize the ItemSubjectMapModel
                    $itemSubjectMapModel = new ItemSubjectMapModel($db);

                    // Start a transaction for subject mappings to ensure data integrity
                    $db->transStart(); // Begin a transaction

                    // Loop through each subjectId and insert the mapping
                    foreach ($subjectDetails as $subjectId) {
                        // Insert into the ItemSubjectMap table
                        $itemSubjectMapModel->insert([
                            'itemId' => $itemId,
                            'subjectId' => $subjectId
                        ]);
                    }

                    // Commit the transaction if all inserts were successful    
                    $db->transComplete(); // Complete the transaction

                    // Check if the transaction was successful
                    if ($db->transStatus() === false) {
                        // Rollback if the transaction failed
                        $db->transRollback();
                        // Handle the error (e.g., log it or throw an exception)
                        log_message('error', 'Transaction failed while inserting subject mappings');
                    } else {
                        // Commit successful, you can perform additional actions if needed
                        log_message('info', 'Successfully inserted subject mappings');
                    }
                }
            }

            return $this->respond(['status' => true, 'message' => 'Item Added Successfully'], 200);
        } else {
            // If validation fails, return the error messages
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ];
            return $this->fail($response, 409);
        }
    }


    public function update()
    {
        $input = $this->request->getPost();
    
        // Validation rules for the item
        $rules = [
            'itemId' => ['rules' => 'required|numeric'], // Ensure itemId is provided and is numeric
        ];
    
        // Validate the input
        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
            $model = new ItemModel($db);
    
           // Retrieve the item by itemId
           $itemId = $input['itemId'];  // Corrected here
           $item = $model->find($itemId); // Assuming find method retrieves the item
    
            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Item not found'], 404);
            }
    
        
         // Prepare the data to be updated (exclude itemId if it's included)
         $updateData = [
            'itemName' => $input['itemName'],  // Corrected here
            'itemCategoryId' => $input['itemCategoryId'],  // Corrected here
            'mrp' => $input['mrp'],  // Corrected here
            'discountType' => $input['discountType'],  // Corrected here
            'discount' => $input['discount'],  // Corrected here
            'barcode' => $input['barcode'],  // Corrected here
            'description' => $input['description'],  // Corrected here
            'itemTypeId' => $input['itemTypeId'],  // Corrected here
            'sku' => $input['sku'], 
            'hsnCode' => $input['hsnCode'],
            'feature' =>$input['feature']

        ];              
    
            // Handle cover image update
            $coverImage = $this->request->getFile('coverImage');
            if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
                // Handle cover image upload as in create() method
                $key = "Exiaa@11";
                $header = $this->request->getHeader("Authorization");
                $token = null;
    
                // extract the token from the header
                if (!empty($header)) {
                    if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                        $token = $matches[1];
                    }
                }
    
                $decoded = JWT::decode($token, new Key($key, 'HS256'));
                $coverImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemImages/';
    
                if (!is_dir($coverImagePath)) {
                    mkdir($coverImagePath, 0777, true); // Create directory if it doesn't exist
                }
    
                $coverImageName = $coverImage->getRandomName();
                $coverImage->move($coverImagePath, $coverImageName);
    
                // Add the new cover image URL to the update data
                $input['coverImage'] = $decoded->tenantName . '/itemImages/' . $coverImageName;
            }
    
            // Handle product image update (if new images are uploaded)
            $productImages = $this->request->getFiles('images');  // 'images' is the name for multiple images
            $imageUrls = [];
    
            if ($productImages && count($productImages) > 0) {
                foreach ($productImages as $image) {
                    if ($image && $image->isValid() && !$image->hasMoved()) {
                        // Handle image upload as in create() method
                        $productImagePath = FCPATH . 'uploads/' . $decoded->tenantName . '/itemSlideImages/';
                        if (!is_dir($productImagePath)) {
                            mkdir($productImagePath, 0777, true);
                        }
    
                        $imageName = $image->getRandomName();
                        $image->move($productImagePath, $imageName);
    
                        $imageUrls[] = 'uploads/itemSlideImages/' . $imageName;
                    }
                }
    
                // If there are multiple images, join the URLs with commas and save in the input data
                if (!empty($imageUrls)) {
                    $input['productImages'] = implode(',', $imageUrls);
                }
            }
    
            // Update the item with new data
            $updated = $model->update($itemId, $updateData);
    
            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Item Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update item'], 500);
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
            'itemId' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ItemModel($db);

            // Retrieve the course by eventId
            $itemId = $input->itemId;
            $item = $model->find($itemId); // Assuming find method retrieves the course

            if (!$item) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($itemId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Item Deleted Successfully'], 200);
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

  public function getAllCategory()
{
    $input = $this->request->getJSON();

    // 🔐 Decode JWT token to get businessId
    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;

    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        $token = $matches[1];
    }

    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $businessId = $decoded->businessId;

    // Connect to tenant DB
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $model = new ItemCategory($db);

    // ✅ Restrict by businessId
    $itemCategories = $model
        ->where('isDeleted', 0)
        ->where('businessId', $businessId)
        ->findAll();

    return $this->respond([
        "status" => true,
        "message" => "All Category Data Fetched",
        "data" => $itemCategories
    ], 200);
}


    public function getAllFee()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $feeModel = new FeeModel($db);
        $fees = $feeModel->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Fees fetched successfully', 'data' => $fees], 200);
    }

    public function getAllShift()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $shiftModel = new ShiftModel($db);
        $shifts = $shiftModel->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Shifts fetched successfully', 'data' => $shifts], 200);
    }

    public function getAllSubject()
    {
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $subjectModel = new SubjectModel($db);
        $subjects = $subjectModel->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Subjects fetched successfully', 'data' => $subjects], 200);
    }

    public function getFeePaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'feeId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load CustomerModel with the tenant database connection
        $feeModel = new FeeModel($db);
    
        $query = $feeModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['perticularName', 'amount'])) {
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
    
        // Ensure that the "deleted" status is 0 (active records)
        $query = $feeModel->where('isDeleted', 0)->where('businessId', $input->businessId); // Apply the deleted check at the beginning
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $fees = $query->paginate($perPage, 'default', $page);
        $pager = $feeModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Customer Data Fetched",
            "data" => $fees,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }
    

    public function createFee()
    {
        // Retrieve the input data from the request
        $input = $this->request->getJSON();
        
        // Define validation rules for required fields
        $rules = [
            'perticularName' => ['rules' => 'required'],
            'amount' => ['rules' => 'required']
        ];
    
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new FeeModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Fee Added Successfully'], 200);
        } else {
            // If validation fails, return the error messages
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ];
            return $this->fail($response, 409);
        }
    }


    // public function updateFee()
    // {
    //     $input = $this->request->getJSON();
        
    //     // Validation rules for the vendor
    //     $rules = [
    //         'feeId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
    //     ];

    //     // Validate the input
    //     if ($this->validate($rules)) {
    //         $tenantService = new TenantService();
    //     // Connect to the tenant's database
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    //     $model = new FeeModel($db);

    //         // Retrieve the vendor by vendorId
    //         // $feeId = $input ->$feeId;
    //         // $fee = $model->find($feeId); 

    //         $fee = $model->find($input->feeId);




    //     if (!$fee) {
    //         return $this->fail(['status' => false, 'message' => 'Fee not found'], 404);
    //      }

            
    //      $updateData = [
    //         'perticularName' => $input -> perticularName,  // Corrected here
    //         'amount' => $input -> amount,  // Corrected here
        
    //     ];     

    //         // Update the vendor with new data
    //      $updated = $model->update($fee, $updateData);


    //      if ($updated) {
    //          return $this->respond(['status' => true, 'message' => 'Fee Updated Successfully'], 200);
    //     } else {
    //         return $this->fail(['status' => false, 'message' => 'Failed to update Fee'], 500);
    //     }


    //     } else {
    //         // Validation failed
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }



    public function updateFee()
    {
        $input = $this->request->getJSON();

        // Validation rules for the lead
        $rules = [
            'feeId' => ['rules' => 'required|numeric'], // Ensure leadId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
            $model = new FeeModel($db);  // Use LeadModel for lead-related operations

            // Retrieve the lead by leadId
            $feeId = $input->feeId;  // Corrected here
            $fee = $model->find($feeId); // Assuming find method retrieves the lead

            if (!$fee) {
                return $this->fail(['status' => false, 'message' => 'Fee not found'], 404);
            }

            // Prepare the data to be updated (exclude leadId if it's included)
            $updateData = [
                'perticularName' => $input -> perticularName,  // Corrected here
                'amount' => $input -> amount,  // Corrected here
            ];

            // Update the lead with new data
            $updated = $model->update($feeId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Fee Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update Fee'], 500);
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
    


    public function deleteFee()
    {
        $input = $this->request->getJSON();

        // Validation rules for the customer
        $rules = [
            'feeId' => ['rules' => 'required'], // Ensure customerId is provided
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   
            $model = new FeeModel($db);

            // Retrieve the customer by customerId
            $feeId = $input->feeId;
            $fee = $model->where('feeId', $feeId)->where('isDeleted', 0)->first(); // Only find active customers

            if (!$fee) {
                return $this->fail(['status' => false, 'message' => 'Customer not found or already deleted'], 404);
            }

            // Perform a soft delete (mark as deleted instead of removing the record)
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($feeId, $updateData);
            

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Fee marked as deleted'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete fee'], 500);
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

    public function assignFee(){
        $input = $this->request->getJSON();

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemFeeMapModel($db);
        if(is_array($input)){
            foreach($input as $fee){
                if($fee->itemFeeMapId == 0 || $fee->itemFeeMapId == null || $fee->itemFeeMapId == ''){
                    $model->insert(['itemId' => $fee->itemId, 'feeId' => $fee->feeId, 'isDeleted' => $fee->isDeleted]);
                } else {
                    $model->update($fee->itemFeeMapId, ['itemId' => $fee->itemId, 'feeId' => $fee->feeId, 'isDeleted' => $fee->isDeleted]);
                }
            }
        } 
        return $this->respond(['status' => true, 'message' => 'Fee Assigned Successfully'], 200);
    }

    public function getFeesByItem(){
        $input = $this->request->getJSON();

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemFeeMapModel($db);
        $fees = $model->where('itemId', $input->itemId)->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Fees fetched successfully', 'data' => $fees], 200);
    }


    public function getShiftPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'shiftId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load CustomerModel with the tenant database connection
        $shiftModel = new ShiftModel($db);
    
        $query = $shiftModel->where('businessId', $input->businessId);
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['shiftName', 'startTime', 'endTime'])) {
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
    
        // Ensure that the "deleted" status is 0 (active records)
        $query = $shiftModel->where('isDeleted', 0)->where('businessId', $input->businessId); // Apply the deleted check at the beginning
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $shifts = $query->paginate($perPage, 'default', $page);
        $pager = $shiftModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Customer Data Fetched",
            "data" => $shifts,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }
    

    public function createShift()
    {
        // Retrieve the input data from the request
        $input = $this->request->getJSON();
        
        // Define validation rules for required fields
        $rules = [
            'shiftName' => ['rules' => 'required'],
            'startTime' => ['rules' => 'required'],
            'endTime' => ['rules' => 'required'],

        ];
    
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ShiftModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Shift Added Successfully'], 200);
        } else {
            // If validation fails, return the error messages
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ];
            return $this->fail($response, 409);
        }
    } 

    // public function updateShift()
    // {
    //     $input = $this->request->getJSON();
        
    //     // Validation rules for the vendor
    //     $rules = [
    //         'shiftId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
    //     ];

    //     // Validate the input
    //     if ($this->validate($rules)) {
    //         $tenantService = new TenantService();
    //     // Connect to the tenant's database
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    //     $model = new ShiftModel($db);

    //         // Retrieve the vendor by vendorId
    //         // $feeId = $input ->$feeId;
    //         // $fee = $model->find($feeId); 

    //         $shift = $model->find($input->shiftId);




    //     if (!$shift) {
    //         return $this->fail(['status' => false, 'message' => 'Shift not found'], 404);
    //      }

            
    //      $updateData = [
    //         'shiftName' => $input -> shiftName,  
    //         'startTime' => $input -> startTime,  
    //         'endTime' => $input -> endTime, 
    //         'emailTime' => $input -> emailTime 
    //     ];     

    //         // Update the vendor with new data
    //      $updated = $model->update($shift, $updateData);


    //      if ($updated) {
    //          return $this->respond(['status' => true, 'message' => 'Shift Updated Successfully'], 200);
    //     } else {
    //         return $this->fail(['status' => false, 'message' => 'Failed to update Shift'], 500);
    //     }


    //     } else {
    //         // Validation failed
    //         $response = [
    //             'status' => false,
    //             'errors' => $this->validator->getErrors(),
    //             'message' => 'Invalid Inputs'
    //         ];
    //         return $this->fail($response, 409);
    //     }
    // }





    public function  updateShift()
    {
        $input = $this->request->getJSON();

        // Validation rules for the lead
        $rules = [
            'shiftId' => ['rules' => 'required|numeric'], // Ensure leadId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Insert the product data into the database
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
            $model = new ShiftModel($db);  // Use LeadModel for lead-related operations

            // Retrieve the lead by leadId
            $shiftId = $input->shiftId;  // Corrected here
            $shift = $model->find($shiftId); // Assuming find method retrieves the lead

            if (!$shift) {
                return $this->fail(['status' => false, 'message' => 'Shift not found'], 404);
            }

            // Prepare the data to be updated (exclude leadId if it's included)
            $updateData = [
            'shiftName' => $input -> shiftName,  
            'startTime' => $input -> startTime,  
            'endTime' => $input -> endTime, 
            'emailTime' => $input -> emailTime 
            ];

            // Update the lead with new data
            $updated = $model->update($shiftId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Shift Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update shift'], 500);
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


    public function deleteShift()
    {
        $input = $this->request->getJSON();

        // Validation rules for the customer
        $rules = [
            'shiftId' => ['rules' => 'required'], // Ensure customerId is provided
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   
            $model = new ShiftModel($db);

            // Retrieve the customer by customerId
            $shiftId = $input->shiftId;
            $shift = $model->where('shiftId', $shiftId)->where('isDeleted', 0)->first(); // Only find active customers

            if (!$shift) {
                return $this->fail(['status' => false, 'message' => 'Shift not found or already deleted'], 404);
            }

            // Perform a soft delete (mark as deleted instead of removing the record)
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($shiftId, $updateData);
            

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Shift marked as deleted'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete shift'], 500);
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

    public function assignShift(){
        $input = $this->request->getJSON();

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemShiftMapModel($db);
        if(is_array($input)){
            foreach($input as $shift){
                if($shift->itemShiftMapId == 0 || $shift->itemShiftMapId == null || $shift->itemShiftMapId == ''){
                    $model->insert(['itemId' => $shift->itemId, 'shiftId' => $shift->shiftId, 'isDeleted' => $shift->isDeleted]);
                } else {
                    $model->update($shift->itemShiftMapId, ['itemId' => $shift->itemId, 'shiftId' => $shift->shiftId, 'isDeleted' => $shift->isDeleted]);
                }
            }
        } 
        return $this->respond(['status' => true, 'message' => 'Shift Assigned Successfully'], 200);
    }

    public function getShiftsByItem(){
        $input = $this->request->getJSON();

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemShiftMapModel($db);
        $shifts = $model->where('itemId', $input->itemId)->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Shifts fetched successfully', 'data' => $shifts], 200);
    }

    public function getSubjectPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'subjectId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load CustomerModel with the tenant database connection
        $subjectModel = new SubjectModel($db);
    
        $query = $subjectModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['subjectName'])) {
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
    
        // Ensure that the "deleted" status is 0 (active records)
        $query = $subjectModel->where('businessId', $input->businessId)->where('isDeleted', 0);
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $subjects = $query->paginate($perPage, 'default', $page);
        $pager = $subjectModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Customer Data Fetched",
            "data" => $subjects,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }
    
    public function createSubject()
    {
        // Retrieve the input data from the request
        $input = $this->request->getJSON();
        
        // Define validation rules for required fields
        $rules = [
            'subjectName' => ['rules' => 'required'],
          

        ];
    
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new SubjectModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Subject Added Successfully'], 200);
        } else {
            // If validation fails, return the error messages
            $response = [
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs',
            ];
            return $this->fail($response, 409);
        }
    }

    public function updateSubject()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the vendor
        $rules = [
            'subjectId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new SubjectModel($db);

            // Retrieve the vendor by vendorId
            // $feeId = $input ->$feeId;
            // $fee = $model->find($feeId); 

            $subject = $model->find($input->subjectId);




        if (!$subject) {
            return $this->fail(['status' => false, 'message' => 'Subject not found'], 404);
         }

            
         $updateData = [
            'subjectName' => $input -> subjectName,  
            'subjectDesc' => $input -> subjectDesc 
           
        ];     

            // Update the vendor with new data
         $updated = $model->update($subject, $updateData);


         if ($updated) {
             return $this->respond(['status' => true, 'message' => 'Subject Updated Successfully'], 200);
        } else {
            return $this->fail(['status' => false, 'message' => 'Failed to update Subject'], 500);
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


    public function deleteSubject()
    {
        $input = $this->request->getJSON();

        // Validation rules for the customer
        $rules = [
            'subjectId' => ['rules' => 'required'], // Ensure customerId is provided
        ];

        // Validate the input
        if ($this->validate($rules)) {
            // Connect to the tenant's database
            $tenantService = new TenantService();
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   
            $model = new SubjectModel($db);

            // Retrieve the customer by customerId
            $subjectId = $input->subjectId;
            $subject = $model->where('subjectId', $subjectId)->where('isDeleted', 0)->first(); // Only find active customers

            if (!$subject) {
                return $this->fail(['status' => false, 'message' => 'Subject not found or already deleted'], 404);
            }

            // Perform a soft delete (mark as deleted instead of removing the record)
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($subjectId, $updateData);
            

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Subject marked as deleted'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete subject'], 500);
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

    public function assignSubject(){
        $input = $this->request->getJSON();

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemSubjectMapModel($db);
        if(is_array($input)){
            foreach($input as $subject){
                if($subject->itemSubjectMapId == 0 || $subject->itemSubjectMapId == null || $subject->itemSubjectMapId == ''){
                    $model->insert(['itemId' => $subject->itemId, 'subjectId' => $subject->subjectId, 'isDeleted' => $subject->isDeleted]);
                } else {
                    $model->update($subject->itemSubjectMapId, ['itemId' => $subject->itemId, 'subjectId' => $subject->subjectId, 'isDeleted' => $subject->isDeleted]);
                }
            }
        } 
        return $this->respond(['status' => true, 'message' => 'Subject Assigned Successfully'], 200);
    }

    public function getSubjectsByItem(){
        $input = $this->request->getJSON();

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ItemSubjectMapModel($db);
        $subjects = $model->where('itemId', $input->itemId)->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Subjects fetched successfully', 'data' => $subjects], 200);
    }

}
