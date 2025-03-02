<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table            = 'order_mst';
    protected $primaryKey       = 'orderId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['orderId', 'orderNo', 'orderCode','mobileNo','email','address', 'orderDate', 'amount', 'discount', 'totalTax', 'finalAmount', 'customerId', 'shippingAddressId', 'deliveryDate', 'orderTrackingNo', 'isActive', 'isDeleted', 'createdBy', 'createdDate', 'modifiedBy', 'modifiedDate', 'businessNameFrom', 'phoneFrom', 'addressFrom', 'emailFrom', 'PanFrom', 'businessNameFor', 'phoneFor', 'addressFor', 'emailFor', 'PanCardFor'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'createdDate';
    protected $updatedField  = 'modifiedDate';
    protected $deletedField  = 'deletedDate';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
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
            $this->db = $db; // Assign the tenant's database connection
        }
    }
}
