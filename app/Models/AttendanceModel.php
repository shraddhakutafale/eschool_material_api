<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceModel extends Model
{
    protected $table            = 'attendance_mst';
    protected $primaryKey       = 'attendanceId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [ 'attendanceId', 'studentId', 'attendanceDate', 'inTime', 'outTime', 'deviceId', 'status', 'present', 'createdDate', 'modifiedDate', 'createdBy', 'modifiedBy'];

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
 