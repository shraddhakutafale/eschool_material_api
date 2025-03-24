<?php

namespace App\Models;

use CodeIgniter\Model;

class AdmissionModel extends Model
{
    protected $table            = 'admission_details';
    protected $primaryKey       = 'admissionId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['admissionId', 'studentId', 'academicYearId', 'selectedCourses', 'rollNo', 'rfId', 'admissionDate', 'active'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    
    }
  

