<?php
class database{
    private $host="localhost";
    private $user="root";
    private $password="";
    private $db="workload_system";

    public $connection;


    public function connect(){
        $this->connection=mysqli_connect(
            $this->host,
            $this->user,
            $this->password,
            $this->db
        );

            if (!$this->connection){
            die("Connection failed");
        } 
        return $this->connection;
    }


    
}
?>