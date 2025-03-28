<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ExamModel;
use App\Models\ExamTimetable;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

use Config\Database;


class Exam extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load VendorModel with the tenant database connection
        $ExamModel = new ExamModel($db);
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $ExamModel->findAll()], 200);
    }

    public function getExamsPaging()
    {
        $input = $this->request->getJSON();
    
        // Get the page number from the input, default to 1 if not provided
        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'examId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
        $search = isset($input->search) ? $input->search : '';
        $filter = $input->filter;
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        // Load VendorModel with the tenant database connection
        $examModel = new ExamModel($db);
        $query = $examModel;
    
        // Apply search filter for name and mobile number
        if (!empty($search)) {
            $query->groupStart()
                  ->like('name', $search)
                  ->orLike('mobileNo', $search)
                  ->groupEnd();
        }
    
       // Apply filtering
        if (!empty($filter)) {
            $filter = json_decode(json_encode($filter), true);

            foreach ($filter as $key => $value) {
                if (in_array($key, [])) {
                    $query->like($key, $value);
                } else if ($key === 'createdDate' && !empty($value)) {
                    $query->where($key, $value);
                }
            }

            // Apply Date Range Filter using startDate and endDate
            if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
                $query->where('createdDate >=', $filter['startDate'])
                ->where('createdDate <=', $filter['endDate']);
            }
        }

        $query->where('isDeleted',0);
        // Apply Sorting
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }
    
        // Get Paginated Results
        $exams = $query->paginate($perPage, 'default', $page);
        $pager = $examModel->pager;
    
        $response = [
            "status" => true,
            "message" => "All Exam Data Fetched",
            "data" => $exams,
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
        // Retrieve the input data from the request
        $input = $this->request->getJSON();
        
        // Define validation rules for required fields
        $rules = [
            'examName' => ['rules' => 'required'],
        ];
    
        if ($this->validate($rules)) {
           
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new ExamModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Exam Added Successfully'], 200);
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
        $input = $this->request->getJSON();
        
        // Validation rules for the vendor
        $rules = [
            'examId' => ['rules' => 'required|numeric'], // Ensure vendorId is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ExamModel($db);

            // Retrieve the vendor by vendorId
            $examId = $input['examId'];
            $exam = $model->find($examId); // Assuming find method retrieves the vendor
            



            if (!$exam) {
                return $this->fail(['status' => false, 'message' => 'Exam not found'], 404);
            }

            // Prepare the data to be updated (exclude vendorId if it's included)
            $updateData = [
                'name' =>$input['name'],
                'vendorCode' =>$input['vendorCode'],
                'mobileNo' => $input['mobileNo'],
                'alternateMobileNo' => $input['alternateMobileNo'],  // Corrected here
                'emailId' => $input['emailId'],  // Corrected here
                'dateOfBirth' => $input['dateOfBirth'],  // Corrected here
                'gender' => $input['gender'],  // Corrected here
            ];

            // Update the vendor with new data
            $updated = $model->update($examId, $updateData);


            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'exam Updated Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to update exam'], 500);
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
        
        // Validation rules for the vendor

        // Validation rules for the lead
        $rules = [
            'vendorId' => ['rules' => 'required'], // Ensure vendorId is provided
        ];
    

        // Validate the input
        if ($this->validate($rules)) {
            $tenantService = new TenantService();
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new VendorModel($db);
    
            // Retrieve the vendor by vendorId
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new VendorModel($db);

            // Retrieve the lead by leadId
            $vendorId = $input->vendorId;
            $vendor = $model->find($vendorId); // Assuming the find method retrieves the vendor
    
            
            $vendor = $model->find($vendorId); // Assuming find method retrieves the lead

            if (!$vendor) {
                return $this->fail(['status' => false, 'message' => 'Vendor not found'], 404);
            }
    
            // Proceed to delete the vendor
            // Soft delete by marking 'isDeleted' as 1
            $updateData = [
                'isDeleted' => 1,
            ];
    

            // Proceed to delete the lead
            $deleted = $model->delete($vendorId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'Vendor Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete vendor'], 500);
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

    public function addAllExamTimetable(){
        $input = $this->request->getJSON();

        if(empty($input)){
            return $this->respond(['status' => false, 'message' => 'No data found'], 200);
        }
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ExamModel($db);

        $examTimetable = new ExamTimetable($db);

        $examTimetable->insertbatch($input);

        return $this->respond(['status' => true, 'message' => 'Subjects Added Successfully'], 200);
    }

    public function getSubjectsByExam(){
        $input = $this->request->getJSON();
        $examId = $input->examId;
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new ExamTimetable($db);

        $examTimetables = $model->where('examId', $examId)->where('isDeleted', 0)->findAll();
        return $this->respond(['status' => true, 'message' => 'Subjects fetched successfully', 'data' => $examTimetables], 200);
    }
}
