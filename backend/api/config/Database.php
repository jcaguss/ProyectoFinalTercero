<?php

class Database {

    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // Configuración de la base de datos directamente en la clase
        $this->host = 'localhost';
        $this->db_name = 'draftosaurus';
        $this->username = 'itiuser';
        $this->password = 'Prueba1234';
    }

    public function connect() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);

        if ($this->conn->connect_error) {
            throw new Exception('Error de conexión a la base de datos: ' . $this->conn->connect_error);
        }
        
        // establecer el conjunto de caracteres a UTF-8
        $this->conn->set_charset("utf8");
        
        return $this->conn;
    }
}

?>

