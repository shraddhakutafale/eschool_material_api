<?php

namespace App\Models;

use CodeIgniter\Model;

class FormDataModel extends Model
{
    protected $table            = 'form_data';
    protected $primaryKey       = 'formDataId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['formDataId', 'formId', 'formDataJson', 'userId', 'isActive', 'isDeleted', 'modifiedBy', 'modifiedDate', 'createdBy', 'createdDate', 'cardId', 'submitType', 'tags', 'description', 'status', 'notify', 'dueDate', 'assignee', 'subheadingFields', 'paragraphFields', 'headerFields'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

      // Dates
      protected $useTimestamps = true;
      protected $dateFormat    = 'datetime';
      protected $createdField  = 'createdDate';
      protected $updatedField  = 'modifiedDate';
    //   protected $beforeInsert = ['addCreatedBy'];
    //   protected $beforeUpdate = ['addModifiedBy'];
  
      protected function addCreatedBy(array $data)
      {
          helper('jwt_helper'); // Ensure the JWT helper is loaded
          $userId = getUserIdFromToken();
          if ($userId) {
              $data['data']['createdBy'] = $userId;
          }else{
              $data['data']['createdBy'] = 1;
          }
          return $data;
      }
  
      protected function addModifiedBy(array $data)
      {
          helper('jwt_helper'); // Ensure the JWT helper is loaded
          $userId = getUserIdFromToken();
          if ($userId) {
              $data['data']['modifiedBy'] = $userId;
          }else{
              $data['data']['modifiedBy'] = 1;
          }
          return $data;
      }
  
     
}
