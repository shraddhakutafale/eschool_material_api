<?php

namespace App\Models;

use CodeIgniter\Model;

class ContentModel extends Model
{
    protected $table            = 'content_menu';
    protected $primaryKey       = 'contentId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['contentId', 'menuId', 'businessId', 'title', 'content', 'createdBy', 'createdDate', 'modifiedBy', 'modifiedDate', 'isActive', 'isDeleted'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'createdDate';
    protected $updatedField  = 'modifiedDate';
    protected $beforeInsert = ['addCreatedBy'];
    protected $beforeUpdate = ['addModifiedBy'];
 
    protected function addCreatedBy(array $data)
    {
        helper('jwt_helper'); // Ensure the JWT helper is loaded
        $userId = getUserIdFromToken();
        if ($userId) {
            $data['data']['createdBy'] = $userId;
        }
        return $data;
    }
 
    protected function addModifiedBy(array $data)
    {
        helper('jwt_helper'); // Ensure the JWT helper is loaded
        $userId = getUserIdFromToken();
        if ($userId) {
            $data['data']['modifiedBy'] = $userId;
        }
        return $data;
    }}