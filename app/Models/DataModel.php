<?php

namespace App\Models;

use CodeIgniter\Model;

class DataModel extends Model
{
    protected $table            = 'data_mst';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id', 'fullName', 'gender', 'dob', 'age', 'mobileNo', 'email', 'address', 'villageTown', 'talukaBlock', 'district', 'state', 'pincode', 'voterIdNo', 'wardBoothNo', 'serialNo', 'assemblyNo', 'aadharNo', 'voterCategory', 'voterSubCategory', 'locationCoord', 'profilePic', 'businessId', 'status', 'notify', 'dueDate', 'assignee', 'subheadingFields', 'paragraphFields', 'headerFields', 'isActive', 'createdAt', 'updatedAt', 'createdDate', 'modifiedDate', 'createdBy', 'isDeleted'];

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
