<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\EventModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Config\Database;

class Event extends BaseController
{
    use ResponseTrait;

    public function index()
    {
       // Insert the product data into the database
       $tenantService = new TenantService();
       // Connect to the tenant's database
       $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); // Load UserModel with the tenant database connection
        $EventModel = new EventModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $EventModel->findAll()], 200);
    }

    public function getAllEventByBusiness()
    {
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
        $eventModel = new EventModel($db);

        // Fetch events for the business
        $events = $eventModel->where('businessId', $businessId)->where('isDeleted', 0)->findAll();

        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $events], 200);
    }

    public function getEventsPaging()
{
    $input = $this->request->getJSON();

    $page = $input->page ?? 1;
    $perPage = $input->perPage ?? 10;
    $sortField = $input->sortField ?? 'eventId';
    $sortOrder = $input->sortOrder ?? 'asc';
    $filter = $input->filter ?? [];

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $eventModel = new EventModel($db);
    $query = $eventModel;

    if (!empty($filter)) {
        $filter = json_decode(json_encode($filter), true);

        foreach ($filter as $key => $value) {
            if (in_array($key, ['eventName', 'location'])) {
                $query->like($key, $value); 
            } else if ($key === 'eventDate') {
                $query->where($key, $value);
            }
        }

        // ✅ Date range filters
        if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
            $query->where('eventDate >=', $filter['startDate'])
                  ->where('eventDate <=', $filter['endDate']);
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
            $query->where('eventDate >=', date('Y-m-d', strtotime('-7 days')));
        }

        if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
            $query->where('eventDate >=', date('Y-m-d', strtotime('-30 days')));
        }
    }

    $query->where('isDeleted', 0);

    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    $events = $query->paginate($perPage, 'default', $page);
    $pager = $eventModel->pager;

    $response = [
        "status" => true,
        "message" => "All Event Data Fetched",
        "data" => $events,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ];

    return $this->respond($response, 200);
}


/*************  ✨ Windsurf Command ⭐  *************/
/**
 * Fetches all active and non-deleted events from the database, ordered by creation date in descending order.
 * Connects to the tenant's database using configuration from the request header.
 * Returns a JSON response containing event data.
 *
 * @return \CodeIgniter\HTTP\Response
 */

/*******  996a8854-7f44-44fa-98a5-117dfff1bae2  *******/
    public function getEventsWebsite()
    {// Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $EventModel = new EventModel($db);
        $events = $EventModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $events], 200);
    }

    public function create()
    {
        // Retrieve the input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'eventName'  => ['rules' => 'required'],
            'venue'  => ['rules' => 'required'],
            'eventDesc'  => ['rules' => 'required'],


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
            $profilePic= $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for the cover image
                $profilePicPath = FCPATH . 'uploads/'. $decoded->tenantName .'/eventImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/coverImages/' prefix
                $profilePicUrl = 'uploads/eventImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/eventImages/', '', $profilePicUrl);
    
                // Add the cover image URL to the input data
                $input['profilePic'] = $decoded->tenantName . '/eventImages/' .$profilePicUrl; 
            }
    
           
    
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new EventModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Event Added Successfully'], 200);
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
        
        // Validation rules for the studentId
        $rules = [
            'eventId' => ['rules' => 'required|numeric'], // Ensure studentId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); $model = new EventModel($db);

            // Retrieve the student by studentId
            $eventId = $input['eventId'];  // Corrected here
            $event = $model->find($eventId); // Assuming find method retrieves the student

            if (!$event) {
                return $this->fail(['status' => false, 'message' => 'event not found'], 404);
            }

            // Prepare the data to be updated (exclude studentId if it's included)
            $updateData = [

                'eventName' => $input['eventName'],  // Corrected here
                'eventDesc' => $input['eventDesc'],  // Corrected here
                'venue' => $input['venue'],  // Corrected here
                
               
            ];

            // Update the student with new data
            $updated = $model->update($eventId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'event Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update event'], 500);
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
            'eventId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new EventModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new EventModel($db);

            // Retrieve the lead by leadId
            $eventId = $input->eventId;
            $event = $model->find($eventId); // Assuming the find method retrieves the vendor
    
            
            $event = $model->find($eventId); // Assuming find method retrieves the lead

            if (!$event) {
                return $this->fail(['status' => false, 'message' => 'event not found'], 404);
            }
    
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($eventId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'event Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete event'], 500);
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
        $eventId = $this->request->getPost('eventId'); // Example field

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

        $model = new EventModel();
        $model->update($eventId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }


  public function getAllEvent()
{
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $EventModel = new EventModel($db);

    $input = $this->request->getJSON(true); // get JSON body
    $filter = $input['filter'] ?? [];

    if (isset($filter['eventId']) && !empty($filter['eventId'])) {
        // Fetch only the event with the given ID
        $eventId = $filter['eventId'];
        $event = $EventModel->where('id', $eventId)->first();
        return $this->respond([
            "status" => true,
            "message" => "Event fetched",
            "data" => $event ? [$event] : []
        ], 200);
    }

    // If no eventId provided, return all events
    return $this->respond([
        "status" => true,
        "message" => "All Data Fetched",
        "data" => $EventModel->findAll()
    ], 200);
}


    
}
