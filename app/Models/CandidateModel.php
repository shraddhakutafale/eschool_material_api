<?php

namespace App\Models;

use CodeIgniter\Model;

class CandidateModel extends Model
{
    protected $table            = 'candidate_mst';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id', 'businessId', 'fullName', 'gender', 'dob', 'age', 'maritalStatus', 'religion', 'caste', 'motherTongue', 'height', 'weight', 'bloodGroup', 'education', 'profession', 'annualIncome', 'workLocation', 'address', 'state', 'district', 'talukaBlock', 'villageTown', 'pincode', 'contactNumber', 'alternateNumber', 'email', 'fatherName', 'motherName', 'familyDetails', 'partnerPreferences', 'idProofType', 'idProofNumber', 'idProofFile', 'resumeFile', 'profilePhoto', 'registrationDate', 'profileStatus', 'addedBy', 'isDeleted', 'isActive', 'createdBy', 'modifiedBy', 'createdAt', 'modifiedAt','createdDate','modifiedDate'];

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
