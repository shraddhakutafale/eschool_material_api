<?php

namespace App\Models;

use CodeIgniter\Model;

class SlideModel extends Model
{
    protected $table            = 'slide_mst';
    protected $primaryKey       = 'slideId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['slideId', 'title', 'profilePic', 'content', 'buttonUrl', 'buttonText', 'businessId','isActive', 'isDeleted', 'createdBy', 'createdDate', 'modifiedBy', 'modifiedDate'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
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
