<?php declare(strict_types = 1);

use Mikk3lRo\atomix\databases\Db;

class SetGmtOnConnect extends Db
{
    protected function postConnect(): void
    {
        $this->query("SET time_zone='+00:00'");
    }
}