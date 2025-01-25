<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table            = 'service_mst';
    protected $primaryKey       = 'serviceId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['serviceId', 'serviceName', 'serviceCategory', 'servicePrice', 'serviceDesc', 'createdBy', 'createdDate', 'modifiedBy', 'modifiedDate', 'isActive', 'isDeleted'];




    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

  
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'createdDate';
    protected $updatedField  = 'modifiedDate';
    protected $deletedField  = 'deletedDate';

  
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

   
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    public function __construct($db = null)
    {
        parent::__construct();

        if ($db) {
            $this->db = $db; 
        }
    }
}
