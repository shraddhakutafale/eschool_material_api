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
    protected $allowedFields    = ['quoteId', 'quoteNo', 'quoteDate', 'validDate', 'quoteTitle', 'quoteSubTitle', 'quoteFrom', 'quoteTo', 'taxType', 'amountWithoutTax', 'discount', 'totalAmount','totalItem', 'finalAmount', 'taxAmount', 'extraAmount', 'signatureUrl', 'note', 'businessId','modifiedBy', 'modifiedDate', 'createdBy', 'createdDate', 'isActive', 'isDeleted', 'businessNameFrom', 'phoneFrom', 'addressFrom', 'emailFrom', 'PanFrom', 'businessNameFor', 'phoneFor', 'addressFor', 'emailFor', 'PanCardFor'];

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
