<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\EventModel;
use App\Libraries\TenantService;

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

    public function getEventsPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'eventId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load EventModel with the tenant database connection
        $eventModel = new EventModel($db);
    
        $query = $eventModel;
    
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);
    
            foreach ($filter as $key => $value) {
                if (in_array($key, ['eventName', 'eventDesc', 'venue'])) {
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
        $input = $this->request->getPost();
        
        // Validation Rules
        $rules = [
            'eventName'  => ['rules' => 'required'],
            'eventDesc'  => ['rules' => 'required'],
            'venue'      => ['rules' => 'required'],
            'startDate'  => ['rules' => 'required|valid_date'],
            'endDate'    => ['rules' => 'required|valid_date']
        ];
    
        if ($this->validate($rules)) {
            // Retrieve tenantConfig from headers
            $tenantConfigHeader = $this->request->getHeaderLine('X-Tenant-Config');
    
            if (!$tenantConfigHeader) {
                return $this->fail(['status' => false, 'message' => 'Tenant configuration not found'], 400);
            }
    
            // Decode tenantConfig JSON
            $tenantConfig = json_decode($tenantConfigHeader, true);
    
            if (!$tenantConfig) {
                return $this->fail(['status' => false, 'message' => 'Invalid tenant configuration'], 400);
            }
    
            // Connect to the tenant's database
            $db = Database::connect($tenantConfig);
            $model = new EventModel($db);
    
            // Insert event data
            $model->insert([
                'eventName' => $input['eventName'],  // Corrected here
                'eventDesc' => $input['eventDesc'],
                'venue' => $input['venue'],
                'startDate' => $input['startDate'],
                'endDate' => $input['endDate']

            ]);
    
            return $this->respond(['status' => true, 'message' => 'Event Added Successfully'], 200);
        } else {
            return $this->fail([
                'status' => false,
                'errors' => $this->validator->getErrors(),
                'message' => 'Invalid Inputs'
            ], 409);
        }
    }
    

    public function update()
    {
        $input = $this->request->getPost();

        // Validation rules for the event
        $rules = [
            'eventId' => ['rules' => 'required|numeric'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); 
            $model = new EventModel($db);  // Use EventModel for event-related operations

            // Retrieve the event by eventId
            $eventId = $input['eventId'];  // Corrected here
            $event = $model->find($eventId); // Assuming find method retrieves the event

            if (!$event) {
                return $this->fail(['status' => false, 'message' => 'event not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $updateData = [

                'eventName' => $input['eventName'],  // Corrected here
                'eventDesc' => $input['eventDesc'],
                'venue' => $input['venue'],
                'startDate' => $input['startDate'],
                'endDate' => $input['endDate']

            ];

            // Update the event with new data
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
        
        // Validation rules for the course
        $rules = [
            'eventId' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
           // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); $model = new EventModel($db);

            // Retrieve the course by eventId
            $eventId = $input->eventId;
            $event = $model->find($eventId); // Assuming find method retrieves the course

            if (!$event) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            // Proceed to delete the course
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($eventId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Course Deleted Successfully'], 200);
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


//     public function uploadPageProfile()
// {
//     // Retrieve form fields
//     $eventId = $this->request->getPost('eventId'); // Example field

//     // Retrieve the file
//     $file = $this->request->getFile('photoUrl');

//     // Validate file
//     if (!$file->isValid()) {
//         return $this->fail($file->getErrorString());
//     }

//     $mimeType = $file->getMimeType();
//     if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
//         return $this->fail('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
//     }

//     // Validate file size
//     if ($file->getSize() > 2048 * 1024) {
//         return $this->fail('File size exceeds 2MB');
//     }

//     // Generate a random file name and move the file
//     $newName = $file->getRandomName();
//     $file->move(WRITEPATH . '../public/uploads', $newName);

//     // Save file and additional data in the database
//     $data = [
//         'photoUrl' => $newName,
//     ];

//     $model = new EventModel();
//     $model->update($eventId, $data);

//     return $this->respond([
//         'status' => 201,
//         'message' => 'File uploaded successfully',
//         'data' => $data,
//     ]);
// }


public function uploadPageProfile()
{
    // Retrieve form fields
    $eventId = $this->request->getPost('eventId'); // Event ID for which logo is being uploaded

    // Retrieve the file
    $file = $this->request->getFile('logoUrl'); // Ensure the input name matches 'logoUrl'

    // Validate the file
    if (!$file->isValid()) {
        return $this->fail($file->getErrorString());
    }

    $mimeType = $file->getMimeType();
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
        return $this->fail('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
    }

    // Validate file size (optional)
    if ($file->getSize() > 2048 * 1024) {
        return $this->fail('File size exceeds 2MB');
    }

    // Generate a random file name and move the file to the uploads directory
    $newName = $file->getRandomName();
    $file->move(WRITEPATH . '../public/uploads', $newName);

    // Build the file URL or path
    $fileUrl = base_url('uploads/' . $newName);

    // Save file path (logoUrl) and additional data in the database
    $data = [
        'logoUrl' => $fileUrl, // Save the file URL in the database
    ];

   // Insert the product data into the database
   $tenantService = new TenantService();
   // Connect to the tenant's database
   $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $db = Database::connect($tenantConfig);
    $model = new EventModel($db);

    // Update the database record
    $model->update($eventId, $data);

    // Respond with success
    return $this->respond([
        'status' => 201,
        'message' => 'Logo uploaded successfully',
        'data' => $data,
    ]);
}


}
