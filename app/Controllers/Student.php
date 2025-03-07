<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StudentModel;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

use Config\Database;

class Student extends BaseController
{
    use ResponseTrait;

    public function index()
    {
         
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $studentModel = new StudentModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $studentModel->findAll()], 200);
    }

    public function getStudentsPaging()
    {
        $input = $this->request->getJSON();

        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'studentId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
        

        $tenantService = new TenantService();
        
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load studentModel with the tenant database connection
        $studentModel = new StudentModel($db);

        $query = $studentModel;

        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);

            foreach ($filter as $key => $value) {
                if (in_array($key, ['studentCode','generalRegisterNo','firstName', 'lastName', 'medium', 'registeredDate'])) {
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

        
        $query->where('isDeleted',0);
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Get Paginated Results
        $students = $query->paginate($perPage, 'default', $page);
        $pager = $studentModel->pager;

        $response = [
            "status" => true,
            "message" => "All Student Data Fetched",
            "data" => $students,
            "pagination" => [
                "currentPage" => $pager->getCurrentPage(),
                "totalPages" => $pager->getPageCount(),
                "totalItems" => $pager->getTotal(),
                "perPage" => $perPage
            ]
        ];

        return $this->respond($response, 200);
    }

    public function getStudentsWebsite()
    {
         
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $studentModel = new StudentModel($db);
        $students = $studentModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $students], 200);
    }

    public function create()
    {
        $input = $this->request->getPost();
        
        // Validation rules for other fields
        $rules = [
            'studentCode'=> ['rules' => 'required'], 
            'firstName'=> ['rules' => 'required'],
            // 'middleName'=> ['rules' => 'required'],
            // 'lastName'=> ['rules' => 'required'],
            // 'motherName'=> ['rules' => 'required'], 
            // 'gender'=> ['rules' => 'required'], 
            // 'birthDate'=> ['rules' => 'required'], 
            // 'birthPlace'=> ['rules' => 'required'], 
            // 'religion'=> ['rules' => 'required'], 
            // 'category'=> ['rules' => 'required'], 
            // 'cast'=> ['rules' => 'required'], 
            // 'subCast'=> ['rules' => 'required'], 
            // 'motherTongue'=> ['rules' => 'required'], 
            // 'bloodGroup'=> ['rules' => 'required'], 
            // 'aadharNo'=> ['rules' => 'required'], 
            // 'medium'=> ['rules' => 'required'], 
            // 'physicallyHandicapped'=> ['rules' => 'required'], 
            // 'educationalGap'=> ['rules' => 'required'], 
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
                $profilePicPath = FCPATH . 'uploads/'. $decoded->tenantName .'/studentImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/profilePics/' prefix
                $profilePicUrl = 'uploads/studentImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/studentImages/', '', $profilePicUrl);
    
                // Add the cover image URL to the input data
                $input['profilePic'] = $profilePicUrl; 
                $input['profilePic'] = $decoded->tenantName . '/studentImages/' .$profilePicUrl;
            }
    
              
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new \App\Models\StudentModel($db);
    
          
            // Insert the student data into the database
            $model->insert($input);
    
            // Return success response
            return $this->respond([
                'status' => true,
                'message' => 'Student Added Successfully',
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
        
        // Validation rules for the studentId
        $rules = [
            'studentId' => ['rules' => 'required|numeric'], // Ensure studentId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); $model = new StudentModel($db);

            // Retrieve the student by studentId
            $studentId = $input['studentId'];  // Corrected here
            $student = $model->find($studentId); // Assuming find method retrieves the student

            if (!$student) {
                return $this->fail(['status' => false, 'message' => 'Student not found'], 404);
            }

            // Prepare the data to be updated (exclude studentId if it's included)
            $updateData = [

                'studentCode' => $input['studentCode'],  // Corrected here
                'generalRegisterNo' => $input['generalRegisterNo'],
                'firstName' => $input['firstName'],
                'middleName' => $input['middleName'],
                'lastName' => $input['lastName'],
                'motherName' => $input['motherName'],
                'gender' => $input['gender'],
                'birthDate' => $input['birthDate'],
                'birthPlace' => $input['birthPlace'],
                'nationality' => $input['nationality'],
                'religion' => $input['religion'],
                'category' => $input['category'],
                'cast' => $input['cast'],
                'subCast' => $input['subCast'],
                'motherTongue' => $input['motherTongue'],
                'bloodGroup' => $input['bloodGroup'],
                'aadharNo' => $input['aadharNo'],
                'medium' => $input['medium'],
                'physicallyHandicapped' => $input['physicallyHandicapped'],
                'registeredDate' => $input['registeredDate'],

            ];

            // Update the student with new data
            $updated = $model->update($studentId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Student Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update student'], 500);
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
        
        // Validation rules for the student
        $rules = [
            'studentId' => ['rules' => 'required'], // Ensure studentId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
             
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config')); $model = new StudentModel($db);

            // Retrieve the student by studentId
            $studentId = $input->studentId;
            $student = $model->find($studentId); // Assuming find method retrieves the student

            if (!$student) {
                return $this->fail(['status' => false, 'message' => 'Student not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($studentId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Student Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete Student'], 500);
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
        $studentId = $this->request->getPost('studentId'); // Example field

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

        $model = new StudentModel();
        $model->update($studentId,$data);

        return $this->respond([
            'status' => 201,
            'message' => 'File and data uploaded successfully',
            'data' => $data,
        ]);
    }


    public function getStudentById($studentId)
{
     
    $tenantService = new TenantService();
    // Connect to the tenant's database
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // Load the StudentModel with the tenant database connection
    $studentModel = new StudentModel($db);

    // Fetch student by ID
    $student = $studentModel->find($studentId); // find method returns a single record by its ID

    // Check if student was found
    if (!$student) {
        throw new \Exception('Student not found.');
    }

    // Respond with the student data
    return $this->respond(["status" => true, "message" => "Student fetched successfully", "data" => $student], 200);
}


public function uploadStudentImage()
{
    // Retrieve the student ID from POST data
    $studentId = $this->request->getPost('studentId');

    // Retrieve the uploaded image
    $file = $this->request->getFile('studentImage');

    // Validate if the file is valid
    if (!$file->isValid()) {
        return $this->fail($file->getErrorString(), 400);
    }

    // Validate the file type (only allow image types)
    $mimeType = $file->getMimeType();
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
        return $this->fail('Invalid file type. Only JPEG, PNG, and GIF are allowed.', 400);
    }

    // Validate file size (limit to 2MB)
    if ($file->getSize() > 2048 * 1024) {
        return $this->fail('File size exceeds 2MB', 400);
    }

    // Generate a random name for the uploaded file
    $newName = $file->getRandomName();
    $filePath = '/uploads/' . $newName;

    // Move the file to the designated directory
    if (!$file->move(WRITEPATH . '../public/uploads', $newName)) {
        return $this->fail('Failed to move the file to the server directory.', 500);
    }

    // Prepare the data to be saved
    $data = [
        'studentImage' => $newName,
    ];

     
    $tenantService = new TenantService();
    // Connect to the tenant's database
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $studentModel = new StudentModel($db);

    // Update the student with the new image URL
    $update = $studentModel->update($studentId, $data);

    if ($update) {
        return $this->respond([
            'status' => 201,
            'message' => 'Student image uploaded successfully',
            'data' => $data,
        ]);
    } else {
        return $this->fail('Failed to update student with the image.', 500);
    }
}

}