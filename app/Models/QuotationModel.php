<?php

namespace App\Models;

use CodeIgniter\Model;

class QuotationModel extends Model
{
    protected $table            = 'quote_mst';
    protected $primaryKey       = 'quoteId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [ 'quoteId', 'quoteNo', 'quoteDate', 'validDate', 'quoteTitle', 'quoteSubTitle', 'quoteFrom', 'quoteTo', 'taxType', 'amountWithoutTax', 'discount', 'totalAmount', 'taxAmount', 'extraAmount', 'signatureUrl', 'note', 'modifiedBy', 'modifiedDate', 'createdBy', 'createdDate', 'isActive', 'isDeleted', 'businessNameFrom', 'phoneFrom', 'addressFrom', 'emailFrom', 'PanFrom', 'businessNameFor', 'phoneFor', 'addressFor', 'emailFor', 'PanCardFor'];

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
