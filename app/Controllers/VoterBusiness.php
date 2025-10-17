<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\VoterBusinessModel;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;


class VoterBusiness extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $tenantService = new TenantService();

        // Connect to the tenant's database
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        // Load UserModel with the tenant database connection
        $voterBusinessModel = new VoterBusinessModel($db);
        $response = [
            "status" => true,
            "message" => "All Data Fetched",
            "data" => $voterBusinessModel->findAll(),
        ];
        return $this->respond($response, 200);
    }

  public function create()
    {
        $input = $this->request->getJSON(true); // assoc array

        // simple validation - mobileNumber or geoLocation or colorCodeId must be present
        if (empty($input['mobileNumber']) && empty($input['geoLocation']) && empty($input['colorCodeId'])) {
            return $this->failValidationErrors(['message' => 'At least one of mobileNumber, geoLocation or colorCodeId is required']);
        }

        // Get userId & businessId from token (if present) - make optional for public forms
        $key = "Exiaa@11";
        $header = $this->request->getHeader("Authorization");
        $token = null;
        if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
            $token = $matches[1];
        }

        $userId = null;
        $businessId = null;
        if ($token) {
            try {
                $decoded = JWT::decode($token, new Key($key, 'HS256'));
                $userId = $decoded->userId ?? null;
                $businessId = $decoded->businessId ?? null;
            } catch (\Exception $e) {
                // token invalid — you may allow anonymous saves by not returning error
                return $this->failUnauthorized('Invalid or expired token');
            }
        }

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new VoterBusinessModel($db);

        // prepare save data
        $saveData = [
            'businessId'  => $businessId ?? ($input['businessId'] ?? null),
            'userId'      => $userId ?? ($input['userId'] ?? null),
            'mobileNumber'=> $input['mobileNumber'] ?? null,
            'geoLocation' => $input['geoLocation'] ?? null,
            'colorCodeId' => $input['colorCodeId'] ?? null,
            'isActive'    => 1,
            'isDeleted'   => 0,
            'createdDate' => date('Y-m-d H:i:s')
        ];

        try {
            $insertId = $model->insert($saveData);
            if ($insertId) {
                return $this->respond([
                    'status' => true,
                    'message' => 'Voter business saved successfully',
                    'insertId' => $insertId
                ], 200);
            } else {
                log_message('error', 'VoterBusiness insert failed: ' . json_encode($model->errors()));
                return $this->failServerError('Failed to save record');
            }
        } catch (\Exception $e) {
            log_message('error', 'VoterBusiness create error: ' . $e->getMessage());
            return $this->failServerError('Something went wrong');
        }
    }

    // Optional: getAllByBusiness
 public function getAllByBusiness()
{
    $tenantService = new TenantService();
    $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
    $model = new VoterBusinessModel($db);

    // Get businessId from JWT
    $header = $this->request->getHeader("Authorization");
    $token = null;
    if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header->getValue(), $matches)) {
        $token = $matches[1];
    }
    $businessId = null;
    if ($token) {
        try {
            $decoded = JWT::decode($token, new Key("Exiaa@11", 'HS256'));
            $businessId = $decoded->businessId ?? null;
        } catch (\Exception $e) {
            // ignore
        }
    }

    // Build filter
    $body = $this->request->getJSON(true); // read POST body
    $where = [];
    if ($businessId) $where['businessId'] = $businessId;
    if (!empty($body['colorCodeId'])) $where['colorCodeId'] = $body['colorCodeId'];
    if (!empty($body['isActive'])) $where['isActive'] = $body['isActive'];

    $data = $model->where($where)->findAll();

    return $this->respond(['status' => true, 'data' => $data]);
}







}
