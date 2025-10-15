<?php

namespace App\Models;

use CodeIgniter\Model;

class DistrictModel extends Model
{
    protected $table            = 'tbl_district_mst';
    protected $primaryKey       = 'district_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['district_id', 'center_id', 'state_name', 'dist_name', 'addedBy', 'is_active', 'is_deleted', 'created_date', 'modified_date', 'modified_by'];

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
 }
}
