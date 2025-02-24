<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StudentModel;
use Config\Database;

class Student extends BaseController
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
        $studentModel = new StudentModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $studentModel->findAll()], 200);
    }

    public function getStudentsPaging()
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
        $studentModel = new StudentModel($db);
        $students = $studentModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $studentModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
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
        $studentModel = new StudentModel($db);
        $students = $studentModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $students], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        
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
            $db = \Config\Database::connect($tenantConfig);
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
        $input = $this->request->getJSON();
        
        // Validation rules for the studentId
        $rules = [
            'studentId' => ['rules' => 'required|numeric'], // Ensure studentId is provided and is numeric
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
            $model = new StudentModel($db);

            // Retrieve the student by studentId
            $studentId = $input->studentId;
            $student = $model->find($studentId); // Assuming find method retrieves the student

            if (!$student) {
                return $this->fail(['status' => false, 'message' => 'Student not found'], 404);
            }

            // Prepare the data to be updated (exclude studentId if it's included)
            $updateData = [
                'studentCode' => $input->studentCode,
                'generalRegisterNo' => $input->generalRegisterNo,
                'firstName' => $input->firstName,
                'middleName' => $input->middleName,
                'lastName' => $input->lastName,
                'motherName' => $input->motherName,
                'gender' => $input->gender,
                'birthDate' => $input->birthDate,
                'birthPlace' => $input->birthPlace,
                'nationality' => $input->nationality,
                'religion' => $input->religion,
                'category' => $input->category,
                'cast' => $input->cast,
                'subCast' => $input->subCast,
                'motherTongue' => $input->motherTongue,
                'bloodGroup' => $input->bloodGroup,
                'aadharNo' => $input->aadharNo,
                'medium' => $input->medium,
                'physicallyHandicapped' => $input->physicallyHandicapped,
                'educationalGap' => $input->educationalGap,
                'registeredDate' => $input->registeredDate
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
            $model = new StudentModel($db);

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

    // Connect to the tenant's database
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
