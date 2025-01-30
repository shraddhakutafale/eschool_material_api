<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\EventModel;
use Config\Database;

class Event extends BaseController
{
    use ResponseTrait;

    public function index()
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
        $EventModel = new EventModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $EventModel->findAll()], 200);
    }

    public function getEventsPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        // Define the number of items per page
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
        $EventModel = new EventModel($db);
        $courses = $EventModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $EventModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $courses,
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
        $EventModel = new EventModel($db);
        $courses = $EventModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $courses], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        $rules = [
            'eventName' => ['rules' => 'required'],
            'eventDesc' => ['rules' => 'required'],

            'venue' => ['rules' => 'required'],
            'startDate' => ['rules' => 'required'],
            'endDate' => ['rules' => 'required'],

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
            $model = new EventModel($db);
        
            $model->insert($input);
             
            return $this->respond(['status'=>true,'message' => 'Event Added Successfully'], 200);
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
        print_r($input);
        
        // Validation rules for the course
        $rules = [
            'eventId' => ['rules' => 'required|numeric'], // Ensure eventId is provided and is numeric
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
            $model = new EventModel($db);

            // Retrieve the course by eventId
            $eventId = $input->eventId;
            $event = $model->find($eventId); // Assuming find method retrieves the course

            if (!$event) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            // Prepare the data to be updated (exclude eventId if it's included)
            $updateData = [

               ' eventName'=> $input->eventName,
                'eventDesc'=> $input->eventDesc,

                'venue'=> $input->venue,
                'endDate'=>$input->endDate,
                'startDate'=>$input->startDate
            ];

            // Update the course with new data
            $updated = $model->update($eventId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Course Updated Successfully'], 200);
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
            'eventId' => ['rules' => 'required'], // Ensure eventId is provided and is numeric
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
            $model = new EventModel($db);

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
