<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StudentModel;
use App\Models\AdmissionModel;
use App\Models\AttendanceModel;
use App\Models\ItemModel;
use App\Models\ItemFeeMapModel;
use App\Models\FeeModel;
use App\Models\PaymentDetailModel;
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
        $students = $studentModel
           
            ->where('student_mst.isDeleted', 0)
            ->findAll();
        return $this->respond(["status" => true, "message" => "All Data Fetched", "data" => $studentModel->findAll()], 200);
    }

  public function getStudentsPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? $input->page : 1;
    $perPage = isset($input->perPage) ? $input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'student_mst.studentId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? $input->search : '';
    $filter = (array)($input->filter ?? []);

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $studentModel = new StudentModel($db);
    $query = $studentModel;

    $attendanceDate = $filter['attendanceDate'] ?? date('Y-m-d');

    // Join tables
    $query->join('admission_details', 'admission_details.studentId = student_mst.studentId', 'left');
    $query->join('attendance_mst', "attendance_mst.studentId = student_mst.studentId AND attendance_mst.attendanceDate = '$attendanceDate'", 'left');

    // Academic Year filter
    if (!empty($filter['academicYear'])) {
        $query->where('admission_details.academicYearId', $filter['academicYear']);
    }

    // ✅ Course filter (itemId is now directly stored in admission_details)
    if (!empty($filter['itemId'])) {
        $query->where('admission_details.itemId', $filter['itemId']);
    }

    // Other filters
    foreach ($filter as $key => $value) {
        if (in_array($key, ['student_mst.studentCode', 'student_mst.generalRegisterNo', 'student_mst.firstName', 'student_mst.lastName', 'student_mst.medium', 'student_mst.registeredDate'])) {
            $query->like($key, $value);
        } elseif ($key === 'student_mst.createdDate') {
            $query->where($key, $value);
        }
    }

    // Date range filters
    if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
        $query->where('student_mst.createdDate >=', $filter['startDate']);
        $query->where('student_mst.createdDate <=', $filter['endDate']);
    }

    if (!empty($filter['dateRange'])) {
        if ($filter['dateRange'] === 'last7days') {
            $query->where('student_mst.createdDate >=', date('Y-m-d', strtotime('-7 days')));
        } elseif ($filter['dateRange'] === 'last30days') {
            $query->where('student_mst.createdDate >=', date('Y-m-d', strtotime('-30 days')));
        }
    }

    // Common filters
    $query->where('student_mst.isDeleted', 0)
        ->where('student_mst.businessId', $input->businessId);

    if (!empty($sortField)) {
        $query->orderBy($sortField, $sortOrder);
    }

    // Select fields
    $query->select('student_mst.*, 
                    admission_details.academicYearId,
                    admission_details.itemId,
                    attendance_mst.attendanceId,
                    attendance_mst.present,
                    attendance_mst.status,
                    attendance_mst.inTime,
                    attendance_mst.outTime,
                    attendance_mst.attendanceDate');

    $students = $query->paginate($perPage, 'default', $page);
    $pager = $studentModel->pager;

    return $this->respond([
        "status" => true,
        "message" => "All Student Data Fetched with Attendance",
        "data" => $students,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ]);
}


public function getStudentsAdmissionPaging()
{
    $input = $this->request->getJSON();

    $page = isset($input->page) ? $input->page : 1;
    $perPage = isset($input->perPage) ? $input->perPage : 10;
    $sortField = isset($input->sortField) ? $input->sortField : 'student_mst.studentId';
    $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    $search = isset($input->search) ? $input->search : '';
    $filter = isset($input->filter) ? (array)$input->filter : [];

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $studentModel = new StudentModel($db);
    $admissionModel = new AdmissionModel($db);
    $itemFeeMapModel = new ItemFeeMapModel($db);
    $feeModel = new FeeModel($db);
    $paymentDetailModel = new PaymentDetailModel($db);

   $query = $studentModel
    ->select('
        student_mst.*,
        admission_details.admissionId,
        admission_details.itemId,
        item_mst.itemName
    ')
    ->join('admission_details', 'admission_details.studentId = student_mst.studentId', 'left')
    ->join('item_mst', 'item_mst.itemId = admission_details.itemId', 'left')
    ->where('student_mst.isDeleted', 0)
    ->where('student_mst.businessId', $input->businessId);

    // Apply filters
    if (!empty($filter['academicYear'])) {
        $query->where('admission_details.academicYearId', $filter['academicYear']);
    }

    if (!empty($filter['itemId'])) {
        $query->where('admission_details.itemId', $filter['itemId']);
    }

    if (!empty($filter['firstName'])) {
        $query->like('student_mst.firstName', $filter['firstName']);
    }

    if (!empty($filter['lastName'])) {
        $query->like('student_mst.lastName', $filter['lastName']);
    }

    if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
        $query->where('student_mst.createdDate >=', $filter['startDate']);
        $query->where('student_mst.createdDate <=', $filter['endDate']);
    }

    if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
        $query->where('student_mst.createdDate >=', date('Y-m-d', strtotime('-7 days')));
    }

    if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
        $query->where('student_mst.createdDate >=', date('Y-m-d', strtotime('-30 days')));
    }

    if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
        $query->orderBy($sortField, $sortOrder);
    }

    // Get paginated results
    $students = $query->paginate($perPage, 'default', $page);
    $pager = $studentModel->pager;

    $finalData = [];

    foreach ($students as $student) {
        $fees = [];
        $totalFee = 0;
        $paidAmount = 0;

        // Get item fees
        $itemFeeMapArray = $itemFeeMapModel
            ->where('itemId', $student['itemId'])
            ->where('isDeleted', 0)
            ->findAll();

        foreach ($itemFeeMapArray as $feeMap) {
            $fee = $feeModel
                ->where('feeId', $feeMap['feeId'])
                ->where('isDeleted', 0)
                ->first();
            if ($fee) {
                $fees[] = $fee['amount'];
                $totalFee += (int)$fee['amount'];
            }
        }

        // Get payments
        $payments = $paymentDetailModel
            ->where('admissionId', $student['admissionId'])
            ->where('isDeleted', 0)
            ->findAll();

        $paidAmount = array_sum(array_column($payments, 'paidAmount'));

        if ($paidAmount >= $totalFee && $totalFee > 0) {
            $paymentStatus = 'Paid';
        } elseif ($paidAmount > 0 && $paidAmount < $totalFee) {
            $paymentStatus = 'Installment';
        } elseif (!empty($payments)) {
            $paymentStatus = 'Installment';
        } else {
            $paymentStatus = 'Unpaid';
        }

        // ✅ Get all shift timings for this student
        $admissions = $admissionModel
            ->select('shift_mst.startTime, shift_mst.endTime')
            ->join('shift_mst', 'shift_mst.shiftId = admission_details.shiftId', 'left')
            ->where('admission_details.studentId', $student['studentId'])
            ->findAll();

        $shifts = [];

        foreach ($admissions as $admission) {
            if (!empty($admission['startTime']) && !empty($admission['endTime'])) {
                $shifts[] = $admission['startTime'] . '-' . $admission['endTime'];
            }
        }

        $finalData[] = [
            'studentId' => $student['studentId'],
            'studentCode' => $student['studentCode'],
            'firstName' => $student['firstName'],
            'middleName' => $student['middleName'],
            'lastName' => $student['lastName'],
            'mobileNo' => $student['mobileNo'],
            'motherName' => $student['motherName'],
            'gender' => $student['gender'],
            'birthDate' => $student['birthDate'],
            'birthPlace' => $student['birthPlace'],
            'nationality' => $student['nationality'],
            'religion' => $student['religion'],
            'cast' => $student['cast'],
            'subCast' => $student['subCast'],
            'motherTongue' => $student['motherTongue'],
            'category' => $student['category'],
            'bloodGroup' => $student['bloodGroup'],
            'aadharNo' => $student['aadharNo'],
            'medium' => $student['medium'],
            'physicallyHandicapped' => $student['physicallyHandicapped'],
            'educationalGap' => $student['educationalGap'],
            'selectedCourses' => $student['selectedCourses'],
            'registeredDate' => $student['registeredDate'],
            'dueDate' => $student['dueDate'],
            'generalRegisterNo' => $student['generalRegisterNo'],
            'admissionId' => $student['admissionId'],
            'itemId' => $student['itemId'],
            'itemName' => $student['itemName'],
            'fees' => $fees,
            'totalFee' => $totalFee,
            'paidAmount' => $paidAmount,
            'paymentStatus' => $paymentStatus,
            'shifts' => $shifts, // ✅ multiple shifts
        ];
    }

    return $this->respond([
        "status" => true,
        "message" => "All Student Data Fetched",
        "data" => $finalData,
        "pagination" => [
            "currentPage" => $pager->getCurrentPage(),
            "totalPages" => $pager->getPageCount(),
            "totalItems" => $pager->getTotal(),
            "perPage" => $perPage
        ]
    ], 200);
}



 public function assignShiftToStudent()
{
    $input = $this->request->getJSON();

    if (!isset($input->studentId) || !isset($input->shiftId)) {
        return $this->failValidationErrors('studentId and shiftId are required.');
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    $admissionBuilder = $db->table('admission_details');

    // 🔍 Get latest admission for the student
    $admission = $admissionBuilder
        ->select('itemId')
        ->where('studentId', $input->studentId)
        ->orderBy('admissionId', 'DESC')
        ->get()
        ->getRow();

    if (!$admission || !isset($admission->itemId)) {
        return $this->failNotFound('No admission record found for this student.');
    }

    $updated = $admissionBuilder
        ->where('studentId', $input->studentId)
        ->where('itemId', $admission->itemId)
        ->update(['shiftId' => $input->shiftId]);

    if (!$updated) {
        return $this->failServerError('Shift update failed.');
    }

    // 🔍 Get shift timings from shift_mst
    $shift = $db->table('shift_mst')
        ->select('startTime, endTime')
        ->where('shiftId', $input->shiftId)
        ->get()
        ->getRow();

    if (!$shift) {
        return $this->failNotFound('Shift not found in shift_mst.');
    }

    $today = date('Y-m-d');

    // 🔄 Update or Insert in attendance_mst
    $attendanceBuilder = $db->table('attendance_mst');

    // Check if attendance record exists
    $existingAttendance = $attendanceBuilder
        ->where('studentId', $input->studentId)
        ->where('attendanceDate', $today)
        ->get()
        ->getRow();

    $attendanceData = [
        'inTime' => $shift->startTime,
        'outTime' => $shift->endTime,
    ];

    if ($existingAttendance) {
        // Update existing attendance
        $attendanceBuilder
            ->where('studentId', $input->studentId)
            ->where('attendanceDate', $today)
            ->update($attendanceData);
    } else {
        // Insert new attendance entry
        $attendanceData['studentId'] = $input->studentId;
        $attendanceData['attendanceDate'] = $today;
        $attendanceBuilder->insert($attendanceData);
    }

    return $this->respond([
        'status' => true,
        'message' => 'Shift assigned and attendance updated successfully',
        'inTime' => $shift->startTime,
        'outTime' => $shift->endTime,
    ]);
}


    public function getStudentsPaymentPaging()
    {
        $input = $this->request->getJSON();

        $page = isset($input->page) ? $input->page : 1;
        $perPage = isset($input->perPage) ? $input->perPage : 10;
        $sortField = isset($input->sortField) ? $input->sortField : 'paymentId';
        $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'desc';
        $search = isset($input->search) ? $input->search : '';
        $filter = isset($input->filter) ? (array)$input->filter : [];

        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        $businessId = $decoded->businessId;

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $studentModel = new StudentModel($db);
        $admissionModel = new AdmissionModel($db);
        $paymentDetailModel = new PaymentDetailModel($db);
        $feeModel = new FeeModel($db);
        $itemFeeMapModel = new ItemFeeMapModel($db);

        $query = $paymentDetailModel;

        // Join admission & student
        $query->join('admission_details', 'admission_details.admissionId = payment_details.admissionId', 'left');
        $query->join('student_mst', 'student_mst.studentId = admission_details.studentId', 'left');

        // ✅ Restrict by tenant's businessId
        $query->where('student_mst.businessId', $businessId);

        // 🔍 Filters
        if (!empty($filter)) {
            if (!empty($filter['academicYear'])) {
                $query->where('admission_details.academicYearId', $filter['academicYear']);
            }

            foreach ($filter as $key => $value) {
                if (in_array($key, ['student_mst.studentCode','student_mst.generalRegisterNo','student_mst.firstName', 'student_mst.lastName', 'student_mst.medium', 'student_mst.registeredDate'])) {
                    $query->like($key, $value);
                } elseif ($key === 'student_mst.createdDate') {
                    $query->where($key, $value);
                }
            }

            if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
                $query->where('student_mst.createdDate >=', $filter['startDate']);
                $query->where('student_mst.createdDate <=', $filter['endDate']);
            }

            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
                $query->where('student_mst.createdDate >=', date('Y-m-d', strtotime('-7 days')));
            }

            if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
                $query->where('student_mst.createdDate >=', date('Y-m-d', strtotime('-30 days')));
            }
        }

        // Sort
        if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Paginate results
        $payments = $query->paginate($perPage, 'default', $page);
        $pager = $paymentDetailModel->pager;

        // 💰 Attach fee breakdown
        foreach ($payments as $key => $payment) {
            $totalFee = 0;
            $fees = [];
            $itemFeeMapArray = $itemFeeMapModel
                    ->where('itemId', $payment['itemId'])
                    ->where('isDeleted', 0)
                    ->findAll();

            foreach ($itemFeeMapArray as $feeMap) {
                    $fee = $feeModel
                        ->where('feeId', $feeMap['feeId'])
                        ->where('isDeleted', 0)
                        ->first();

                    if ($fee && isset($fee['amount'])) {
                        $fees[] = $fee['amount'];
                        $totalFee += (float)$fee['amount'];
                    }
                }

            $payments[$key]['fees'] = $fees;
            $payments[$key]['totalFee'] = $totalFee;
            $payments[$key]['isPaid'] = (isset($payment['status']) && $payment['status'] === 'paid');
        }

        return $this->respond([
            "status" => true,
            "message" => "All Student Payment Data Fetched",
            "data" => $payments,
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
    $input = $this->request->getPost();

    // Validation rules
    $rules = [
        'firstName' => ['rules' => 'required'],
        'lastName'  => ['rules' => 'required'],
            'generalRegisterNo' => []  // 👈 Add this line

    ];

    if ($this->validate($rules)) {
        // 🔐 Decode JWT to get businessId
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;

        if (!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
        }

        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        $businessId = $decoded->businessId; // ✅ Get businessId from token

        // Handle profilePic upload
        $profilePic = $this->request->getFile('profilePic');
        if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
            $profilePicPath = FCPATH . 'uploads/' . $decoded->tenantName . '/studentImages/';
            if (!is_dir($profilePicPath)) {
                mkdir($profilePicPath, 0777, true);
            }

            $profilePicName = $profilePic->getRandomName();
            $profilePic->move($profilePicPath, $profilePicName);
            $input['profilePic'] = $decoded->tenantName . '/studentImages/' . $profilePicName;
        }

        // ✅ Add businessId to student insert
        $input['businessId'] = $businessId;

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

        $studentModel    = new StudentModel($db);
        $admissionModel  = new AdmissionModel($db);
        $attendanceModel = new AttendanceModel($db);

        $studentId = $studentModel->insert($input);

        // Admission data
        $courseDataArray = [];
        if (!empty($input['selectedCourses'])) {
            $courses = strpos($input['selectedCourses'], ',') !== false
                ? explode(',', $input['selectedCourses'])
                : [$input['selectedCourses']];

            foreach ($courses as $course) {
                $courseDataArray[] = [
                    'studentId'       => $studentId,
                    'itemId'          => $course,
                    'academicYearId'  => $input['academicYearId'],
                    'registeredDate'  => date('Y-m-d H:i:s'),
                    'businessId'      => $businessId  // ✅ Optional but recommended
                ];
            }

            if (!empty($courseDataArray)) {
                $admissionModel->insertBatch($courseDataArray);
            }
        }

        // ✅ Add businessId in attendance data
        $attendanceData = [
            'studentId'      => $studentId,
            'status'         => 'Absent',
            'present'        => 0,
            'attendanceDate' => date('Y-m-d'),
            'businessId'     => $businessId  // ✅ required
        ];

        $attendanceModel->insert($attendanceData);

        return $this->respond([
            'status'  => true,
            'message' => 'Student, Admission & Attendance Details Added Successfully',
            'data'    => $studentId
        ], 200);
    } else {
        return $this->fail([
            'status'  => false,
            'errors'  => $this->validator->getErrors(),
            'message' => 'Invalid Inputs'
        ], 409);
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
                return $this->fail(['status' => false, 'message' => 'student not found'], 404);
            }

            // Prepare the data to be updated (exclude studentId if it's included)
            $updateData = [
                'studentCode' => $input['studentCode'],  // Corrected here
                'generalRegisterNo' => $input['generalRegisterNo'],
                'mobileNo' => $input['mobileNo'],
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
                'registeredDate' => $input['registeredDate'],

                'cast' => $input['cast'],
                'subCast' => $input['subCast'],
                'motherTongue' => $input['motherTongue'],
                'bloodGroup' => $input['bloodGroup'],
                'aadharNo' => $input['aadharNo'],
                'medium' => $input['medium']
                
               
            ];

            // Update the student with new data
            $updated = $model->update($studentId, $updateData);

            if ($updated) {
                return $this->respond(['status' => true, 'message' => 'student Updated Successfully'], 200);
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

public function addAllPayment() {
    $input = $this->request->getJSON();

    // 🔐 Extract businessId from token
    $key = "Exiaa@11";
    $header = $this->request->getHeader("Authorization");
    $token = null;

    if (!empty($header)) {
        if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }
    }

    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $businessId = $decoded->businessId;

    // 🔗 Connect to tenant DB
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $paymentDetailModel = new PaymentDetailModel($db);

    // ✅ Add businessId to each payment record
    $paymentDataArray = [];
    foreach ($input as $payment) {
        $paymentArr = (array)$payment;
        $paymentArr['businessId'] = $businessId;
        $paymentDataArray[] = $paymentArr;
    }

    // 🔄 Batch Insert
    $paymentDetailModel->insertBatch($paymentDataArray);

    return $this->respond([
        'status' => 201,
        'message' => 'All Payments added successfully',
        'data' => $paymentDataArray
    ]);
}


    public function addpayment()
    {
        $input = $this->request->getJSON();
    
        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    
        $paymentDetailModel = new PaymentDetailModel($db);
        $admissionModel = new AdmissionModel($db);
    
        // Get admissionId from studentId
        $admission = $admissionModel
            ->select('admissionId')
            ->where('studentId', $input->studentId)
            ->orderBy('admissionId', 'DESC') // optional: to get the latest admission
            ->first();
    
        if (!$admission || !isset($admission['admissionId'])) {
            return $this->respond([
                'status' => 404,
                'message' => 'Admission record not found for studentId: ' . $input->studentId
            ], 404);
        }
    
        // Assign admissionId into input
        $input->admissionId = $admission['admissionId'];
    
        // Convert to array for insert/update
        $paymentData = (array) $input;
    
        if (isset($input->paymentId) && !empty($input->paymentId)) {
            $paymentDetailModel->update($input->paymentId, $paymentData);
            return $this->respond([
                'status' => 201,
                'message' => 'Payment updated successfully',
                'data' => $paymentData
            ]);
        } else {
            $paymentDetailModel->insert($paymentData);
            return $this->respond([
                'status' => 201,
                'message' => 'Payment added successfully',
                'data' => $paymentData
            ]);
        }
    }
    

    // public function addallpayment()
    // {
    //     $input = $this->request->getPost(); // Use getJSON(true) to get an array instead of stdClass
        
    //     // Validate input
    //     if (!is_array($input) || empty($input)) {
    //         return $this->respond([
    //             'status' => 400,
    //             'message' => 'Invalid input data'
    //         ], 400);
    //     }
    
    //     $tenantService = new TenantService();
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    //     $paymentDetailModel = new PaymentDetailModel($db);
    
    //     try {
    //         // Process each payment to ensure proper format
    //         $payments = array_map(function($payment) {
    //             return [
    //                 'student_id' => $payment['studentId'] ?? null,
    //                 'total_amount' => $payment['totalAmount'] ?? 0,
    //                 'paid_amount' => $payment['paidAmount'] ?? 0,
    //                 'due_date' => $payment['dueDate'] ?? null,
    //                 'label' => $payment['label'] ?? null,
    //                 'payment_method' => $payment['paymentMethod'] ?? 'cash',
    //                 'transaction_id' => $payment['transactionId'] ?? null,
    //                 'payment_date' => $payment['paymentDate'] ?? date('Y-m-d'),
    //                 'status' => $payment['status'] ?? 'Paid'
    //             ];
    //         }, $input);
    
    //         // Insert batch
    //         $result = $paymentDetailModel->insertBatch($payments);
    
    //         return $this->respond([
    //             'status' => 201,
    //             'message' => 'Payments added successfully',
    //             'data' => $result
    //         ]);
    //     } catch (\Exception $e) {
    //         return $this->respond([
    //             'status' => 500,
    //             'message' => 'Error processing payments: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
}