<?php

namespace App\Models;

use CodeIgniter\Model;

class VoterModel extends Model
{
    protected $table            = 'voters';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields = [
        'id','epic_no','full_name','m_full_name','husband_father_name','m_husband_father_name',
        'relation_type','m_relation_type','age','gender','m_gender','address','m_address','booth_no',
        'serial_no','part_no','part_name','assembly_code','ward_no','source_page',
        'state_name','district_name','language','extraction_date','constituencyId',
        'created_at','updated_at','createdDate','modifiedDate','isActive','createdBy','isDeleted','modifiedBy'
    ];

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
