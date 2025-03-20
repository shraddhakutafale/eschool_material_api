<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\OfferModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

use Config\Database;


class Offer extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load VendorModel with the tenant database connection
        $OfferModel = new OfferModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $OfferModel->findAll()], 200);
    }


    public function getOffersPaging() {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'offerId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load StaffModel with the tenant database connection
        $offerModel = new OfferModel($db);
    
        $query = $offerModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['offerName'])) {
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
        $query->where('isDeleted', 0);
    
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $offers = $query->paginate($perPage, 'default', $page);
        $pager = $offerModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Staff Data Fetched",
            "data" => $offers,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];
    
        return $this->respond($response, 200);
    }
    
    
    public function create()
    {
        $input = $this->request->getPost();
        
        // Validation rules for other fields
        $rules = [
            'offerName'=> ['rules' => 'required'],
            'discountType'=> ['rules' => 'required'],

            
        ];
    
        // Validate the incoming data
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
            
            $decoded = JWT::decode($token, new Key($key, 'HS256')); $key = "Exiaa@11";
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
            $profilePic = $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for the cover image
                $profilePicPath = FCPATH . 'uploads/'. $decoded->tenantName .'/offerImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/profilePics/' prefix
                $profilePicUrl = 'uploads/offerImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/offerImages/', '', $profilePicUrl);
    
                // Add the cover image URL to the input data
                $input['profilePic'] = $profilePicUrl; 
                $input['profilePic'] = $decoded->tenantName . '/offerImages/' .$profilePicUrl;
            }
    
              
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new \App\Models\OfferModel($db);
    
          
            // Insert the student data into the database
            $model->insert($input);
    
            // Return success response
            return $this->respond([
                'status' => true,
                'message' => 'Offer Added Successfully',
                'data' => $input
            ], 200);
    
        } else {
            // If validation fails, return errors
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
        $input = $this->request->getPost();
        
        $rules = [
            'offerId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new OfferModel($db);

            $offerId = $input['offerId'];
            $offer = $model->find($offerId); 
            



            if (!$offer) {
                return $this->fail(['status' => false, 'message' => 'Offer not found'], 404);
            }

            // Prepare the data to be updated (exclude vendorId if it's included)
            $updateData = [
                'offerName' =>$input['offerName'],
                'discountType' =>$input['discountType'],
                'selectOffer' =>$input['selectOffer'],
                'allCategory' =>$input['allCategory'],
                'allItems' =>$input['allItems'],
 
            ];

            // Update the vendor with new data
            $updated = $model->update($offerId, $updateData);


            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Offer Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update offer'], 500);
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
        

        $rules = [
            'offerId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new OfferModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new OfferModel($db);

            // Retrieve the lead by leadId
            $offerId = $input->offerId;
            $offer = $model->find($offerId); // Assuming the find method retrieves the vendor
    
            
            $offer = $model->find($offerId); // Assuming find method retrieves the lead

            if (!$offer) {
                return $this->fail(['status' => false, 'message' => 'offer not found'], 404);
            }
    
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($offerId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'offer Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete offer'], 500);
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
    

    public function uploadPageProfile()
    {
        // Retrieve form fields
        $vendorId = $this->request->getPost('offerId'); // Example field

        // Retrieve the file
        $file = $this->request->getFile('photoUrl');

        // Validate file
        if (!$file->isValid()) {
            return $this->fail($file->getErrorString());
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
            return $this->fail('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
        }

        // Validate file type and size
        if ($file->getSize() > 2048 * 1024) {
            return $this->fail('Invalid file type or size exceeds 2MB');
        }

        // Generate a random file name and move the file
        $newName = $file->getRandomName();
        $filePath = '/uploads/' . $newName;
        $file->move(WRITEPATH . '../public/uploads', $newName);

        // Save file and additional data in the database
        $data = [
            'photoUrl' => $newName,
        ];

        $model = new VendorModel();
        $model->update($vendorId, $data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }
}
