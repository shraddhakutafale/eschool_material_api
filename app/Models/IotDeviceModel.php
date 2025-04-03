<?php

namespace App\Models;

use CodeIgniter\Model;

class IotDeviceModel extends Model
{
    protected $table            = 'device_parameter';
    protected $primaryKey       = 'iotId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['iotId', 'ipAddress', 'postId', 'deviceId', 'sensorDatatype', 'localMillis', 'rtcTimestamp', 'deviceTemperature', 'imuPitch', 'imuRoll', 'imuYaw', 'ambianceHumidity', 'ambianceTemperature', 'heatIndex', 'surfaceTemperature', 'deepSoilMoisture', 'strainMeasurement', 'createdBy', 'createdDate', 'modifiedBy', 'modifiedDate', 'isActive', 'isDeleted'];

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
     }}
 