<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdmissionDetailTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'admissionId' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true,
                'constraint' => '25'
            ],
            'studentId' => [
                'type' => 'INT',
                'constraint' => '25'
            ],
            'academicYearId' => [
                'type' => 'INT',
                'constraint' => '25'
            ],
            'classId' => [
                'type' => 'VARCHAR',
            ]
        ]);
        $this->forge->addKey('admissionId', true);
        $this->forge->createTable('admission_details');
        
    }

    public function down()
    {
        $this->forge->dropTable('admission_details');
    }
}
