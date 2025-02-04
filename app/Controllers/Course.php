<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CourseModel;
use App\Models\FeeModel;
use App\Models\ShiftModel;
use App\Models\SubjectModel;
use Config\Database;

class Course extends BaseController
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
        $courseModel = new CourseModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $courseModel->findAll()], 200);
    }

    public function getCoursesPaging()
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
        $courseModel = new CourseModel($db);
        $courses = $courseModel->orderBy('createdDate', 'DESC')->paginate($perPage, 'default', $page);
        $pager = $courseModel->pager;

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

    public function getCoursesWebsite()
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
        $courseModel = new CourseModel($db);
        $courses = $courseModel->orderBy('createdDate', 'DESC')->where('isActive', 1)->where('isDeleted', 0)->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $courses], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for other fields
        $rules = [
            'courseName' => ['rules' => 'required'],
            'courseDesc' => ['rules' => 'required'],
            'price' => ['rules' => 'required'],
            'duration' => ['rules' => 'required'],
            'discount' => ['rules' => 'required'],
            'startDate' => ['rules' => 'required'],
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
            $model = new \App\Models\CourseModel($db);
    
            // Handle image upload
            $image = $this->request->getFile('coverImage');
            $imageName = null;
    
            if ($image && $image->isValid() && !$image->hasMoved()) {
                // Define upload path
                $uploadPath = WRITEPATH . 'uploads/course_images/';
    
                // Ensure the directory exists
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
    
                // Move the file to the desired directory with a unique name
                $imageName = $image->getRandomName();
                $image->move($uploadPath, $imageName);
    
                // Get the URL of the uploaded image
                $imageUrl = base_url() . '/uploads/course_images/' . $imageName;
                $input->coverImage = $imageUrl;  // Save the image URL
            }
    
            // Insert the course data into the database
            $model->insert((array) $input);
    
            // Return success response
            return $this->respond([
                'status' => true,
                'message' => 'Course Added Successfully',
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
        
        // Validation rules for the course
        $rules = [
            'courseId' => ['rules' => 'required|numeric'], // Ensure courseId is provided and is numeric
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
            $model = new CourseModel($db);

            // Retrieve the course by courseId
            $courseId = $input->courseId;
            $course = $model->find($courseId); // Assuming find method retrieves the course

            if (!$course) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            // Prepare the data to be updated (exclude courseId if it's included)
            $updateData = [
                'courseName' => $input->courseName,
                'courseDesc' => $input->courseDesc,
                'price' => $input->price,
                'discount' => $input->discount,
                'startDate' => $input->startDate,
                'duration' => $input->duration,
                'finalPrice' => $input->finalPrice,
            ];

            // Update the course with new data
            $updated = $model->update($courseId, $updateData);

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
            'courseId' => ['rules' => 'required'], // Ensure courseId is provided and is numeric
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
            $model = new CourseModel($db);

            // Retrieve the course by courseId
            $courseId = $input->courseId;
            $course = $model->find($courseId); // Assuming find method retrieves the course

            if (!$course) {
                return $this->fail(['status' => false, 'message' => 'Course not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($courseId, $updateData);

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

    public function getCourseById($courseId)
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

        // Load the CourseModel with the tenant database connection
        $courseModel = new CourseModel($db);

        // Fetch course by ID
        $course = $courseModel->find($courseId); // find method returns a single record by its ID

        // Check if course was found
        if (!$course) {
            throw new \Exception('Course not found.');
        }

        // Respond with the course data
        return $this->respond(["status" => true, "message" => "Course fetched successfully", "data" => $course], 200);
    }


    public function uploadCourseImage()
    {
        // Retrieve the course ID from POST data
        $courseId = $this->request->getPost('courseId');

        // Retrieve the uploaded image
        $file = $this->request->getFile('courseImage');

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
            'courseImage' => $newName,
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
        $courseModel = new CourseModel($db);

        // Update the course with the new image URL
        $update = $courseModel->update($courseId, $data);

        if ($update) {
            return $this->respond([
                'status' => 201,
                'message' => 'Course image uploaded successfully',
                'data' => $data,
            ]);
        } else {
            return $this->fail('Failed to update course with the image.', 500);
        }
    }

    public function createFee(){
        $input = $this->request->getJSON();

        // Validation rules for other fields
        $rules = [
            'perticularName' => ['rules' => 'required'],
            'amount' => ['rules' => 'required'],
        ];

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
            $feeModel = new FeeModel($db);

            // Save the fee
            $feeModel->insert($input);

            return $this->respond([
                'status' => 201,
                'message' => 'Fee created successfully',
                'data' => $data,
            ]);
        }
    }

// Shift Methods
    public function getAllShift()
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
        $shiftModel = new ShiftModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $shiftModel->findAll()], 200);
    }

    public function createShift()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for other fields
        $rules = [
            'shiftName' => ['rules' => 'required'],
            'startTime1' => ['rules' => 'required'],
            'startTime2' => ['rules' => 'required'],
       
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
            $model = new \App\Models\ShiftModel($db);
    
            // Handle image upload
            $image = $this->request->getFile('coverImage');
            $imageName = null;
    
            if ($image && $image->isValid() && !$image->hasMoved()) {
                // Define upload path
                $uploadPath = WRITEPATH . 'uploads/shift_images/';
    
                // Ensure the directory exists
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
    
                // Move the file to the desired directory with a unique name
                $imageName = $image->getRandomName();
                $image->move($uploadPath, $imageName);
    
                // Get the URL of the uploaded image
                $imageUrl = base_url() . '/uploads/shift_images/' . $imageName;
                $input->coverImage = $imageUrl;  // Save the image URL
            }
    
            // Insert the shift data into the database
            $model->insert((array) $input);
    
            // Return success response
            return $this->respond([
                'status' => true,
                'message' => 'Shift Added Successfully',
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
    

    public function updateShift()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the shift
        $rules = [
            'shiftId' => ['rules' => 'required|numeric'], // Ensure shiftId is provided and is numeric
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
            $model = new ShiftModel($db);

            // Retrieve the shift by shiftId
            $shiftId = $input->shiftId;
            $shift = $model->find($shiftId); // Assuming find method retrieves the shift

            if (!$shift) {
                return $this->fail(['status' => false, 'message' => 'Shift not found'], 404);
            }

            // Prepare the data to be updated (exclude shiftId if it's included)
            $updateData = [
                'shiftName' => $input->shiftName,
                'startTime1' => $input->startTime1,
                'startTime2' => $input->startTime2,
                'endTime1' => $input->endTime1,
                'endTime2' => $input->endTime2,
                'emailTime' => $input->emailTime
              
            ];

            // Update the shift with new data
            $updated = $model->update($shiftId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'Shift Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update Shift'], 500);
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
        
        // Validation rules for the shift
        $rules = [
            'shiftId' => ['rules' => 'required'], // Ensure shiftId is provided and is numeric
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
            $model = new ShiftModel($db);

            // Retrieve the shift by shiftId
            $shiftId = $input->shiftId;
            $shift = $model->find($shiftId); // Assuming find method retrieves the shift

            if (!$shift) {
                return $this->fail(['status' => false, 'message' => 'Shift not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($shiftId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Shift Deleted Successfully'], 200);
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

    // Methods END SHIFT 
 

    // Subject METHOD START

    public function getAllSubject()
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
        $subjectModel = new SubjectModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $subjectModel->findAll()], 200);
    }

    public function createSubject()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for other fields
        $rules = [
           'subjectName' => ['rules' => 'required'],
       
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
            $model = new \App\Models\SubjectModel($db);
    
            // Handle image upload
            $image = $this->request->getFile('coverImage');
            $imageName = null;
    
            if ($image && $image->isValid() && !$image->hasMoved()) {
                // Define upload path
                $uploadPath = WRITEPATH . 'uploads/subject_images/';
    
                // Ensure the directory exists
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
    
                // Move the file to the desired directory with a unique name
                $imageName = $image->getRandomName();
                $image->move($uploadPath, $imageName);
    
                // Get the URL of the uploaded image
                $imageUrl = base_url() . '/uploads/subject_images/' . $imageName;
                $input->coverImage = $imageUrl;  // Save the image URL
            }
    
            // Insert the subject data into the database
            $model->insert((array) $input);
    
            // Return success response
            return $this->respond([
                'status' => true,
                'message' => 'Subject Added Successfully',
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
    

    public function updateSubject()
    {
        $input = $this->request->getJSON();
        
        // Validation rules for the subject
        $rules = [
            'subjectId' => ['rules' => 'required|numeric'], // Ensure subjectId is provided and is numeric
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
            $model = new SubjectModel($db);

            // Retrieve the subject by subjectId
            $subjectId = $input->subjectId;
            $subject = $model->find($subjectId); // Assuming find method retrieves the Subject

            if (!$subject) {
                return $this->fail(['status' => false, 'message' => 'Subject not found'], 404);
            }

            // Prepare the data to be updated (exclude subjectId if it's included)
            $updateData = [
                'subjectName' => $input->subjectName,
                'subjectDesc' => $input->subjectDesc,
              
            ];

            // Update the subject with new data
            $updated = $model->update($subjectId, $updateData);

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
        
        // Validation rules for the subject
        $rules = [
            'subjectId' => ['rules' => 'required'], // Ensure subjectId is provided and is numeric
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
            $model = new SubjectModel($db);

            // Retrieve the subject by subjectId
            $subjectId = $input->subjectId;
            $subject = $model->find($subjectId); // Assuming find method retrieves the Subject

            if (!$subject) {
                return $this->fail(['status' => false, 'message' => 'Subject not found'], 404);
            }

            $updateData = [
                'isDeleted' => 1,
            ];
            $deleted = $model->update($subjectId, $updateData);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Subject Deleted Successfully'], 200);
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
}
