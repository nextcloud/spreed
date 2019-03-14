<?php

namespace OCA\Spreed\Db;

use OCP\AppFramework\Db\Entity;

class UserStatus extends Entity
{

    public $id;
    public $status;
    public $updateAt;

    public function __construct()
    {
        $this->addType('update_at', 'integer');
    }
}