<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\StudentModel;
use CodeIgniter\API\ResponseTrait;
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
         $StudentModel = new StudentModel($db);
         return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $StudentModel->findAll()], 200);
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
        $StudentModel = new StudentModel($db);
        $student = $StudentModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $StudentModel->pager;

        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $student,
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
        $StudentModel = new StudentModel($db);
        $student = $StudentModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $student], 200);
    }


    public function create()
    {

        $input = $this->request->getJSON();
        $rules = [

                'studentCode' => ['rules' => 'required'],
                'generalRegisterNo' => ['rules' => 'required'],
                'firstName' => ['rules' => 'required'],
                'middleName' => ['rules' => 'required'],
                'lastName' => ['rules' => 'required'],
                'motherName' => ['rules' => 'required'],
                'gender' => ['rules' => ''],
                'birthDate' => ['rules' => 'required'],
                'birthPlace' => ['rules' => ''],
                'nationality' => ['rules' => ''],
                'religion' => ['rules' => ''],
                'category' => ['rules' => ''],
                'caste' => ['rules' => ''],
                'subCaste' => ['rules' => ''],
                'motherTongue' => ['rules' => ''],
                'bloodGroup' => ['rules' => 'required'],
                'aadharNo' => ['rules' => 'required'],
                'medium' => ['rules' => 'required'],
                'physicallyHandicapped' => ['rules' => 'required'],
                'educationalGap' => ['rules' => 'required'],


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
        $db = \Config\Database::connect($tenantConfig);
        $model = new \App\Models\StudentModel($db);

        // Student creation logic
        $input = $this->request->getJSON();
        $model->insert($input);

        return $this->respond(["status" => true, 'message' => ' Student Registered Successfully'], 200);

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
        
        // Validation rules for the student
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
            'studentCode' =>$input->studentCode,
            'generalRegisterNo' =>$input->generalRegisterNo,
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
            'caste' => $input->caste,
            'subCaste' => $input->subCaste,
            'motherTongue' => $input->motherTongue,
            'bloodGroup' => $input->bloodGroup,
            'aadharNo' => $input->aadharNo,
            'medium' => $input->medium,
            'physicallyHandicapped' => $input->physicallyHandicapped,
            'educationalGap' => $input->educationalGap,


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
            $student = $model->find($studentId); // Assuming find method retrieves the Student

            if (!$student) {
                return $this->fail(['status' => false, 'message' => 'Student not found'], 404);
            }

            // Proceed to delete the student
            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($memberId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Student Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete student'], 500);
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
}
