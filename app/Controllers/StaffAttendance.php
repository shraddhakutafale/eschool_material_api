<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StaffModel;
use App\Models\StaffAttendanceModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class StaffAttendance extends BaseController
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
        // Load StaffModel with the tenant database connection
        $staffModel = new StaffModel($db);

        return $this->respond(["status" => true, "message" => "All Staff Data Fetched", "data" => $staffModel->findAll()], 200);
    }

    // public function getStaffPaging() {
    //     $input = $this->request->getJSON();
    
    //     // Get the page number from the input, default to 1 if not provided
    //     $page = isset($input->page) ? $input->page : 1;
    //     $perPage = isset($input->perPage) ? $input->perPage : 10;
    //     $sortField = isset($input->sortField) ? $input->sortField : 'staffId';
    //     $sortOrder = isset($input->sortOrder) ? $input->sortOrder : 'asc';
    //     $search = isset($input->search) ? $input->search : '';
    //     $filter = $input->filter;
    
    //     $tenantService = new TenantService();
    //     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    //     // Load StaffModel with the tenant database connection
    //     $staffModel = new StaffModel($db);
    
    //     $query = $staffModel;
    
    //     if (!empty($filter)) {
    //         $filter = json_decode(json_encode($filter), true);
    
    //         foreach ($filter as $key => $value) {
    //             if (in_array($key, ['empName', 'empCategory', 'empCode', 'empSal'])) {
    //                 $query->like($key, $value); // LIKE filter for specific fields
    //             } else if ($key === 'createdDate') {
    //                 $query->where($key, $value); // Exact match filter for createdDate
    //             }
    //         }
    
    //         // Apply Date Range Filter (startDate and endDate)
    //         if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
    //             $query->where('createdDate >=', $filter['startDate'])
    //                   ->where('createdDate <=', $filter['endDate']);
    //         }
    
    //         // Apply Last 7 Days Filter if requested
    //         if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
    //             $last7DaysStart = date('Y-m-d', strtotime('-7 days'));  // 7 days ago from today
    //             $query->where('createdDate >=', $last7DaysStart);
    //         }
    
    //         // Apply Last 30 Days Filter if requested
    //         if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
    //             $last30DaysStart = date('Y-m-d', strtotime('-30 days'));  // 30 days ago from today
    //             $query->where('createdDate >=', $last30DaysStart);
    //         }
    //     }
    
    //     // Ensure that the "deleted" status is 0 (active records)
    //     $query->where('isDeleted', 0);
    
    //     // Apply Sorting
    //     if (!empty($sortField) && in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
    //         $query->orderBy($sortField, $sortOrder);
    //     }
    
    //     // Get Paginated Results
    //     $staffs = $query->paginate($perPage, 'default', $page);
    //     $pager = $staffModel->pager;
    
    //     $response = [
    //         "status" => true,
    //         "message" => "All Staff Data Fetched",
    //         "data" => $staffs,
    //         "pagination" => [
    //             "currentPage" => $pager->getCurrentPage(),
    //             "totalPages" => $pager->getPageCount(),
    //             "totalItems" => $pager->getTotal(),
    //             "perPage" => $perPage
    //         ]
    //     ];
    
    //     return $this->respond($response, 200);
    // }
    
  public function getStaffPaging()
{
    $input = $this->request->getJSON();

    // Safe input extraction
    $page           = isset($input->page) ? (int)$input->page : 1;
    $perPage        = isset($input->perPage) ? (int)$input->perPage : 10;
    $sortFieldInput = isset($input->sortField) ? $input->sortField : 'staffId';
    $sortOrder      = isset($input->sortOrder) ? strtoupper($input->sortOrder) : 'ASC';
    $search         = isset($input->search) ? $input->search : '';
    $filter         = isset($input->filter) ? (array)$input->filter : [];

   
    $today = date('Y-m-d');

    // Allowed sort fields
    $allowedSortFields = [
        'staffId'         => 's.staffId',
        'empName'         => 's.empName',
        'empCategory'     => 's.empCategory',
        'empCode'         => 's.empCode',
        'empSal'          => 's.empSal',
        'attendanceDate'  => 'a.attendanceDate'
    ];
    $sortField = $allowedSortFields[$sortFieldInput] ?? 's.staffId';

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));

    // ✅ Ensure today's attendance exists for each staff
    $staffIds = $db->table('staff_mst')
        ->select('staffId')
        ->where('isDeleted', 0)
        ->get()
        ->getResultArray();

    foreach ($staffIds as $staff) {
        $exists = $db->table('staff_attendance')
            ->where('staffId', $staff['staffId'])
            ->where('attendanceDate', $today)
            ->countAllResults();

        if ($exists == 0) {
            $db->table('staff_attendance')->insert([
                'staffId'        => $staff['staffId'],
                'attendanceDate' => $today,
                'present'        => 0,
                'inTime'         => null,
                'outTime'        => null,
                'status'         => '',
                'deviceId'       => null
            ]);
        }
    }

    // Build main query
    $builder = $db->table('staff_mst s')
        ->select('s.*, a.attendanceId, a.attendanceDate, a.present, a.inTime, a.outTime, a.status as attendanceStatus')
        ->join('staff_attendance a', 'a.staffId = s.staffId AND a.attendanceDate = ' . $db->escape($today), 'left')
        ->where('s.isDeleted', 0);

    // Filters
    foreach ($filter as $key => $value) {
        if (in_array($key, ['empName', 'empCategory', 'empCode', 'empSal'])) {
            $builder->like("s.$key", $value);
        } elseif ($key === 'createdDate') {
            $builder->where("s.$key", $value);
        }
    }

    if (!empty($filter['startDate']) && !empty($filter['endDate'])) {
        $builder->where('s.createdDate >=', $filter['startDate']);
        $builder->where('s.createdDate <=', $filter['endDate']);
    }

    if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last7days') {
        $builder->where('s.createdDate >=', date('Y-m-d', strtotime('-7 days')));
    }

    if (!empty($filter['dateRange']) && $filter['dateRange'] === 'last30days') {
        $builder->where('s.createdDate >=', date('Y-m-d', strtotime('-30 days')));
    }

    // Search
    if (!empty($search)) {
        $builder->groupStart()
            ->like('s.empName', $search)
            ->orLike('s.empCode', $search)
            ->groupEnd();
    }

    // Sorting
    $builder->orderBy($sortField, $sortOrder);

    // Total before pagination
    $total = $builder->countAllResults(false);

    // Pagination
    $staffs = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

    return $this->respond([
        'status' => true,
        'message' => 'Staff with Attendance fetched successfully',
        'data' => $staffs,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => ceil($total / $perPage),
            'totalItems' => $total,
            'perPage' => $perPage
        ]
    ], 200);
}





    public function create()
    {
        // Retrieve the input data from the request
        $input = $this->request->getPost();
        
        // Define validation rules for required fields
        $rules = [
            'empName' => ['rules' => 'required'],
            'empCode' => ['rules' => 'required'],
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
            $profilePic = $this->request->getFile('profilePic');
            $profilePicName = null;
    
            if ($profilePic && $profilePic->isValid() && !$profilePic->hasMoved()) {
                // Define the upload path for the cover image
                $profilePicPath = FCPATH . 'uploads/'. $decoded->tenantName .'/staffImages/';
                if (!is_dir($profilePicPath)) {
                    mkdir($profilePicPath, 0777, true); // Create directory if it doesn't exist
                }
    
                // Move the file to the desired directory with a unique name
                $profilePicName = $profilePic->getRandomName();
                $profilePic->move($profilePicPath, $profilePicName);
    
                // Get the URL of the uploaded cover image and remove the 'uploads/profilePics/' prefix
                $profilePicUrl = 'uploads/staffImages/' . $profilePicName;
                $profilePicUrl = str_replace('uploads/staffImages/', '', $profilePicUrl);
    
                // Add the cover image URL to the input data
                // $input['profilePic'] = $profilePicUrl; 
                $input['profilePic'] = $decoded->tenantName . '/staffImages/' .$profilePicUrl;
            }
    
           
    
            $tenantService = new TenantService();
            // Connect to the tenant's database
            $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
            $model = new StaffModel($db);
            $model->insert($input);
    
            return $this->respond(['status' => true, 'message' => 'Staff Added Successfully'], 200);
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


//      public function update()
// {
//     $input = $this->request->getJSON(true); // get JSON as associative array

//     if (!is_array($input)) {
//         return $this->fail([
//             'status' => false,
//             'message' => 'Invalid input format, expected array of attendance records'
//         ], 400);
//     }

//     $tenantService = new TenantService();
//     $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
//     $model = new StaffAttendanceModel($db);

//     $errors = [];
//     $updatedCount = 0;

//     foreach ($input as $index => $attendanceData) {
//         // Validate attendanceId exists and numeric
//         if (!isset($attendanceData['attendanceId']) || !is_numeric($attendanceData['attendanceId'])) {
//             $errors[] = "Record $index: attendanceId is required and must be numeric.";
//             continue;
//         }

//         $attendanceId = $attendanceData['attendanceId'];

//         // Find existing record
//         $attendance = $model->find($attendanceId);
//         if (!$attendance) {
//             $errors[] = "Record $index: Attendance record with ID $attendanceId not found.";
//             continue;
//         }

//         // Prepare update data, keep old values if keys missing
//         $updateData = [
//             'inTime'   => $attendanceData['inTime']   ?? $attendance['inTime'],
//             'outTime'  => $attendanceData['outTime']  ?? $attendance['outTime'],
//             'deviceId' => $attendanceData['deviceId'] ?? $attendance['deviceId'],
//             'status'   => $attendanceData['status']   ?? $attendance['status'],
//             'present'  => $attendanceData['present']  ?? $attendance['present'],
//         ];

//         // Update record
//         if ($model->update($attendanceId, $updateData)) {
//             $updatedCount++;
//         } else {
//             $errors[] = "Record $index: Failed to update attendance ID $attendanceId.";
//         }
//     }

//     if (count($errors) > 0) {
//         return $this->respond([
//             'status' => false,
//             'message' => 'Some records failed to update.',
//             'updatedCount' => $updatedCount,
//             'errors' => $errors,
//         ], 207); // 207 Multi-Status
//     }

//     return $this->respond([
//         'status' => true,
//         'message' => "All $updatedCount attendance records updated successfully.",
//     ], 200);
// }



public function update()
{
    $input = $this->request->getJSON(true); // Array of attendance data

    if (!is_array($input)) {
        return $this->fail([
            'status' => false,
            'message' => 'Invalid input format, expected array of attendance records'
        ], 400);
    }

    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new StaffAttendanceModel($db);

    $errors = [];
    $updatedCount = 0;
    $updatedRecords = [];

    foreach ($input as $index => $attendanceData) {
        if (!isset($attendanceData['attendanceId']) || !is_numeric($attendanceData['attendanceId'])) {
            $errors[] = "Record $index: attendanceId is required and must be numeric.";
            continue;
        }

        $attendanceId = $attendanceData['attendanceId'];

        $attendance = $model->find($attendanceId);
        if (!$attendance) {
            $errors[] = "Record $index: Attendance record with ID $attendanceId not found.";
            continue;
        }

      $updateData = [
    'inTime'   => $attendanceData['inTime']   ?? $attendance['inTime'],
    'outTime'  => $attendanceData['outTime']  ?? $attendance['outTime'],
    'deviceId' => $attendanceData['deviceId'] ?? $attendance['deviceId'],
    'status'   => $attendanceData['status']   ?? $attendance['status'],
    'present'  => isset($attendanceData['present']) ? $attendanceData['present'] : $attendance['present'],
    ];


        if ($model->update($attendanceId, $updateData)) {
            $updatedCount++;
            // Fetch updated record to send in response
            $updatedRecord = $model->find($attendanceId);
            $updatedRecords[] = [
                'attendanceId' => $attendanceId,
                'status' => $updatedRecord['status'],
                'present' => $updatedRecord['present'],
            ];
        } else {
            $errors[] = "Record $index: Failed to update attendance ID $attendanceId.";
        }
    }

    if (count($errors) > 0) {
        return $this->respond([
            'status' => false,
            'message' => 'Some records failed to update.',
            'updatedCount' => $updatedCount,
            'updatedRecords' => $updatedRecords,
            'errors' => $errors,
        ], 207);
    }

    return $this->respond([
        'status' => true,
        'message' => "All $updatedCount attendance records updated successfully.",
        'updatedRecords' => $updatedRecords,
    ], 200);
}




    public function delete()
    {
        $input = $this->request->getJSON();

        // Validation rules for the staff
        $rules = [
            'staffId' => ['rules' => 'required'], // Ensure staffID is provided and is numeric
        ];

        // Validate the input
        if ($this->validate($rules)) {
                // Insert the product data into the database
        $tenantService = new TenantService();
        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));   $model = new StaffModel($db);

            // Retrieve the staff by staffId
            $staffId = $input->staffId;
            $staff = $model->find($staffId); // Assuming find method retrieves the staff

            if (!$staff) {
                return $this->fail(['status' => false, 'message' => 'staff not found'], 404);
            }

            // Proceed to delete the staff
            $deleted = $model->delete($staffId);

            if ($deleted) {
                return $this->respond(['status' => true, 'message' => 'staff Deleted Successfully'], 200);
            } else {
                return $this->fail(['status' => false, 'message' => 'Failed to delete staff'], 500);
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
