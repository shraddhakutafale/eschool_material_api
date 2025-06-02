<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table            = 'student_mst';
    protected $primaryKey       = 'studentId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['studentId', 'studentCode', 'generalRegisterNo', 'mobileNO', 'firstName', 'middleName', 'lastName', 'motherName', 'gender', 'birthDate', 'birthPlace', 'nationality', 'religion', 'category', 'cast', 'subCast', 'motherTongue', 'bloodGroup', 'aadharNo', 'medium', 'physicallyHandicapped', 'educationalGap', 'profilePic', 'registeredDate', 'isLeft', 'isActive', 'isDeleted', 'createdBy', 'createdDate', 'modifiedBy', 'modifiedDate'];

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
