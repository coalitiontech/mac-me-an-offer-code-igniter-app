<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_EE extends CI_Migration {

   public function up()
   {
    $this->dbforge->add_field(array(
            'id' => array(
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => TRUE,
                    'auto_increment' => TRUE
            ),
            'title' => array(
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => FALSE,
            )
    ));
   $this->dbforge->add_key('id', TRUE);
   $this->dbforge->create_table('EE');
   }

   public function down()
   {
         $this->dbforge->drop_table('EE');
   }


}
