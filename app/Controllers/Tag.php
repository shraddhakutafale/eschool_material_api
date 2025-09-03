<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ItemModel;
use App\Models\ItemTypeModel;
use App\Models\ItemCategory;
use App\Models\ItemSubCategory;
use App\Models\ItemGroup;
use App\Models\BrandModel;
use App\Models\Unit;
use Config\Database;
use App\Libraries\TenantService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use App\Models\SlideModel;
use App\Models\TagModel;



class Tag extends BaseController
{
    use ResponseTrait;


public function index()
    {
        $params = $this->request->getJSON(true) ?: $this->request->getPost();
        $businessId = $params['businessId'] ?? null;
        if (!$businessId) return $this->failValidationErrors('businessId required');

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new TagModel($db);

        $tags = $model->where('businessId', $businessId)->findAll();
        return $this->respond(['status' => true, 'data' => $tags], 200);
    }

    public function create()
    {
        $input = $this->request->getJSON(true) ?: $this->request->getPost();
        $name = trim($input['name'] ?? '');
        if (!$name) return $this->failValidationErrors('Tag name required');

        $key = "Exiaa@11";
        $header = $this->request->getHeaderLine('Authorization');
        if (!preg_match('/Bearer\s(\S+)/', $header, $m)) return $this->failUnauthorized('Token missing');

        try {
            $decoded = JWT::decode($m[1], new Key($key, 'HS256'));
        } catch (\Exception $e) {
            return $this->failUnauthorized('Invalid token');
        }

        $userId = $decoded->userId ?? null;
        $businessId = $input['businessId'] ?? ($decoded->businessId ?? null);
        if (!$businessId) return $this->failValidationErrors('businessId required');

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new TagModel($db);

        $exist = $model->where(['businessId' => $businessId, 'name' => $name])->first();
        if ($exist) {
            return $this->respond(['status' => true, 'message' => 'Tag exists', 'data' => $exist], 200);
        }

        $model->insert([
            'businessId' => $businessId,
            'name'       => $name,
            'createdBy'  => $userId
        ]);

        $id  = $model->getInsertID();
        $tag = $model->find($id);

        return $this->respond(['status' => true, 'message' => 'Tag created', 'data' => $tag], 200);
    }

    public function getAll()
    {
        $input = $this->request->getJSON(true) ?: $this->request->getPost();
        $businessId = $input['businessId'] ?? null;
        if (!$businessId) {
            return $this->failValidationErrors('businessId required');
        }

        $tenantService = new TenantService();
        $db = $tenantService->getTenantConfig($this->request->getHeaderLine('X-Tenant-Config'));
        $model = new TagModel($db);

        $tags = $model->where('businessId', $businessId)->findAll();

        return $this->respond([
            'status' => true,
            'data'   => $tags
        ], 200);
    }
}