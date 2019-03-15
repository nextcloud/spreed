<?php

namespace OCA\Spreed\Db;

use OCP\IDBConnection;

class UserStatusDAO
{

    public static $STATUS_ONLINE = 'online';
    public static $STATUS_OFFLINE = 'offline';
    public static $STATUS_AWAY = 'away';

    private $db;

    public function __construct(IDBConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Find user status.
     *
     * @param $userId
     *
     * @return mixed
     */
    public function find($userId)
    {
        $tableName = 'talk_users_status';

        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
           ->from($tableName)
           ->where('user_id = :userId')
           ->setParameters([':userId' => $userId]);

        $cursor = $qb->execute();
        $row    = $cursor->fetch();
        $cursor->closeCursor();

        return $row;
    }

    /**
     * Get users status by user ids.
     *
     * @param $userIds
     *
     * @return array
     */
    public function findByUserIds($userIds)
    {
        $tableName = 'talk_users_status';

        $qb = $this->db->getQueryBuilder();

        $where       = [];
        $whereValues = [];
        $index       = 0;

        foreach ($userIds as $userId) {

            $fieldPlaceholder = ":userId".$index;

            $where[]                        = "user_id = ".$fieldPlaceholder;
            $whereValues[$fieldPlaceholder] = $userId;

            $index++;
        }

        if ( ! $where) {
            return [];
        }

        // Convert it to a string.
        $where = implode(" or ", $where);

        $qb->select('*')
           ->from($tableName)
           ->where($where)
           ->setParameters($whereValues)
           ->groupBy('user_id')
           ->orderBy('updated_at', 'desc');

        $cursor = $qb->execute();
        $result = $cursor->fetchAll();
        $cursor->closeCursor();

        return $result;
    }

    /**
     * Add status.
     *
     * @param $userId
     * @param $status
     *
     * @return mixed
     */
    public function addStatus($userId, $status)
    {
        $tableName = 'talk_users_status';

        $qb = $this->db->getQueryBuilder();

        $qb->insert($tableName)
           ->values([
               'user_id'    => $this->db->quote($userId),
               'status'     => $this->db->quote(strtolower($status)),
               'updated_at' => time(),
           ]);

        return $qb->execute();
    }

    /**
     * Update status.
     *
     * @param $userId
     * @param $status
     *
     * @return mixed
     */
    public function updateStatus($userId, $status)
    {
        $tableName = 'talk_users_status';

        $qb = $this->db->getQueryBuilder();

        $qb->delete($tableName)
           ->where('user_id = :userId')
           ->setParameters([':userId' => $userId]);

        $qb->execute();

        return $this->addStatus($userId, $status);
    }

    /**
     * Add or update status.
     *
     * @param $userId
     * @param $status
     *
     * @return mixed
     */
    public function addOrUpdateStatus($userId, $status)
    {
        $userStatus = $this->find($userId);

        if ( ! $userStatus) {
            $this->addStatus($userId, $status);
        } else {
            $this->updateStatus($userId, $status);
        }

        return $this->find($userId);
    }

    /**
     * Check if status is a valid value.
     *
     * @param $userId
     * @param $status
     *
     * @return bool
     */
    public function validate($userId, $status)
    {
        if ( ! $userId) {
            return false;
        }

        if ( ! in_array($status, ['online', 'offline', 'away'])) {
            return false;
        }

        return true;
    }
}