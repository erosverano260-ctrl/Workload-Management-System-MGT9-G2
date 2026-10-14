<?php
class database {
    private $host     = "localhost";
    private $user     = "root";
    private $password = "";
    private $db       = "workload_system";

    public $connection;

    public function connect() {
        // Throw exceptions on any SQL error (same as PHP 8.1+ default), on every PHP version.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->connection = mysqli_connect($this->host, $this->user, $this->password, $this->db);
        } catch (mysqli_sql_exception $e) {
            // Don't leak credentials in a stack trace.
            throw new RuntimeException('Database connection failed. Check includes/db.php and that MySQL is running.');
        }
        return $this->connection;
    }
}
