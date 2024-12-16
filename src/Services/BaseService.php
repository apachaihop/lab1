<?php

namespace App\Services;

abstract class BaseService
{
    protected $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    protected function beginTransaction()
    {
        $this->conn->begin_transaction();
    }

    protected function commit()
    {
        $this->conn->commit();
    }

    protected function rollback()
    {
        $this->conn->rollback();
    }
}
